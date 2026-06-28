<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MobileSentrix\SyncMobileSentrixCategoriesJob;
use App\Jobs\MobileSentrix\SyncMobileSentrixPartsJob;
use App\Models\MobileSentrixSyncLog;
use App\Models\Part;
use App\Models\PartCategory;
use App\Services\MobileSentrix\MobileSentrixAuthService;
use App\Services\MobileSentrix\MobileSentrixClient;
use App\Services\MobileSentrix\MobileSentrixException;
use App\Services\MobileSentrix\MobileSentrixSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class MobileSentrixController extends Controller
{
    public function index(MobileSentrixClient $client): View
    {
        $configStatus = $client->redactedConfigStatus();
        $preflight = $this->preflightStatus($configStatus);

        return view('admin.parts.mobilesentrix', [
            'configStatus' => $configStatus,
            'preflight' => $preflight,
            'safeSupportMessage' => $this->safeSupportMessage($configStatus),
            'browserSecretRedirectAllowed' => (bool) config('mobilesentrix.allow_browser_secret_redirect'),
            'missingCredentials' => $client->missingCredentialNames(),
            'latestLogs' => MobileSentrixSyncLog::query()->latest()->limit(12)->get(),
            'lastCategoryLog' => MobileSentrixSyncLog::query()->where('sync_type', 'categories')->latest()->first(),
            'lastPartLog' => MobileSentrixSyncLog::query()->whereIn('sync_type', ['parts_full', 'parts_category', 'parts', 'single_part'])->latest()->first(),
            'partsCount' => Part::query()->where('is_api_item', true)->count(),
            'categoriesCount' => PartCategory::query()->whereNotNull('raw_payload')->count(),
            'queueConfigured' => $this->queueConfigured(),
        ]);
    }

    public function authenticateServer(MobileSentrixAuthService $auth): RedirectResponse
    {
        try {
            $temporaryCredentials = $auth->requestTemporaryCredentials();

            if (! $temporaryCredentials) {
                return back()
                    ->withErrors(['mobilesentrix' => 'This MobileSentrix OAuth flow requires browser authorization. Browser redirects containing sensitive credentials are disabled by default. Contact MobileSentrix to confirm the correct OAuth flow.'])
                    ->with('mobilesentrix_connection_status', 'Browser authorization required')
                    ->with('mobilesentrix_failed_at', now()->toDateTimeString());
            }

            $auth->exchangeToken($temporaryCredentials['oauth_token'], $temporaryCredentials['oauth_verifier']);

            return back()
                ->with('status', 'MobileSentrix OAuth authentication completed. Access tokens were stored securely.')
                ->with('mobilesentrix_connection_status', 'Authenticated');
        } catch (MobileSentrixException $exception) {
            return $this->redirectWithMobileSentrixException($exception);
        }
    }

    public function startAuthorization(Request $request, MobileSentrixAuthService $auth): RedirectResponse
    {
        try {
            $authorizationUrl = $auth->authorizationUrl();

            if ($this->oauthUrlContainsSensitiveCredentials($authorizationUrl) && ! (bool) config('mobilesentrix.allow_browser_secret_redirect')) {
                return back()
                    ->withErrors(['mobilesentrix' => 'MobileSentrix authentication cannot be opened in the browser because the authorization URL includes sensitive credentials. Use the secure server-side authentication command or contact MobileSentrix to confirm the correct OAuth flow.'])
                    ->with('mobilesentrix_connection_status', 'Browser authentication blocked');
            }

            if ($this->oauthUrlContainsSensitiveCredentials($authorizationUrl) && ! $request->boolean('confirm_secret_redirect')) {
                return back()
                    ->withErrors(['mobilesentrix' => 'Warning: MobileSentrix browser authentication will expose Consumer Key and Consumer Secret in the browser URL. Continue only if MobileSentrix has confirmed this is required.'])
                    ->with('mobilesentrix_connection_status', 'Confirmation required');
            }

            return redirect()->away($authorizationUrl);
        } catch (MobileSentrixException $exception) {
            return $this->redirectWithMobileSentrixException($exception);
        }
    }

    public function callback(Request $request, MobileSentrixAuthService $auth): RedirectResponse
    {
        $validated = $request->validate([
            'oauth_token' => ['required', 'string'],
            'oauth_verifier' => ['required', 'string'],
        ]);

        try {
            $auth->exchangeToken($validated['oauth_token'], $validated['oauth_verifier']);
        } catch (\Throwable $exception) {
            $httpStatus = $exception instanceof MobileSentrixException
                ? $exception->httpStatus()
                : null;

            return redirect()
                ->route('admin.parts.mobilesentrix.index')
                ->withErrors(['mobilesentrix' => $exception->getMessage()])
                ->with('mobilesentrix_connection_status', 'Failed')
                ->with('mobilesentrix_http_status', $httpStatus ?? 'Not captured')
                ->with('mobilesentrix_failed_at', now()->toDateTimeString());
        }

        return redirect()
            ->route('admin.parts.mobilesentrix.index')
            ->with('status', 'MobileSentrix OAuth exchange succeeded. Access tokens were stored securely.')
            ->with('mobilesentrix_connection_status', 'Authenticated');
    }

    public function test(MobileSentrixClient $client): RedirectResponse
    {
        try {
            $result = $client->testConnection();

            return back()
                ->with('status', $result['message'])
                ->with('mobilesentrix_connection_status', 'Success');
        } catch (\Throwable $exception) {
            $httpStatus = $exception instanceof MobileSentrixException
                ? $exception->httpStatus()
                : null;
            $message = $exception instanceof MobileSentrixException && $exception->httpStatus() === 401
                ? $exception->getMessage()
                : 'MobileSentrix API connection failed. Please verify credentials and authenticate again.';

            return back()
                ->withErrors(['mobilesentrix' => $message])
                ->with('mobilesentrix_connection_status', 'Failed')
                ->with('mobilesentrix_http_status', $httpStatus ?? 'Not captured')
                ->with('mobilesentrix_failed_at', now()->toDateTimeString());
        }
    }

    public function syncCategories(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'max:120'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        if (! $this->queueConfigured()) {
            return back()->withErrors([
                'mobilesentrix' => 'Queue is not configured for long MobileSentrix syncs. Run from terminal: '.$this->categorySyncCommand($validated['category_id'] ?? null, $validated['depth'] ?? null),
            ]);
        }

        SyncMobileSentrixCategoriesJob::dispatch($validated['category_id'] ?? null, $validated['depth'] ?? null);

        return back()->with('status', 'MobileSentrix category sync has been queued. Check Sync Logs for progress.');
    }

    public function syncParts(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $categoryId = $validated['category_id'] ?? null;
        $limit = $validated['limit'] ?? null;

        if (! $this->queueConfigured()) {
            return back()->withErrors([
                'mobilesentrix' => 'Queue is not configured for long MobileSentrix syncs. Run from terminal: '.$this->partsSyncCommand($categoryId, $limit),
            ]);
        }

        SyncMobileSentrixPartsJob::dispatch($categoryId, $limit);

        return back()->with('status', $categoryId
            ? 'MobileSentrix parts sync for category '.$categoryId.' has been queued. Check Sync Logs for progress.'
            : 'Full MobileSentrix parts sync has been queued. Check Sync Logs for progress.');
    }

    public function refreshPart(Request $request, MobileSentrixSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:120'],
        ]);

        $result = $syncService->refreshPart($validated['sku']);

        return $this->redirectWithResult($result);
    }

    private function redirectWithResult(array $result): RedirectResponse
    {
        $message = sprintf(
            '%s Created: %d. Updated: %d. Skipped: %d. Failed: %d.',
            $result['message'] ?? 'MobileSentrix sync finished.',
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['skipped_count'] ?? 0,
            $result['failed_count'] ?? 0,
        );

        $redirect = redirect()->route('admin.parts.mobilesentrix.index');

        if (($result['status'] ?? null) === 'failed') {
            return $redirect->withErrors(['mobilesentrix' => $message]);
        }

        return $redirect->with('status', $message);
    }

    private function queueConfigured(): bool
    {
        return config('queue.default') !== 'sync';
    }

    private function categorySyncCommand(?string $categoryId = null, ?int $depth = null): string
    {
        $command = 'php -d max_execution_time=0 artisan mobilesentrix:sync-categories';

        if (filled($categoryId)) {
            $command .= ' --category='.escapeshellarg($categoryId);
        }

        if ($depth) {
            $command .= ' --depth='.$depth;
        }

        return $command;
    }

    private function partsSyncCommand(?string $categoryId = null, ?int $limit = null): string
    {
        $command = 'php -d max_execution_time=0 artisan mobilesentrix:sync-parts';

        if (filled($categoryId)) {
            $command .= ' --category='.escapeshellarg($categoryId);
        }

        if ($limit) {
            $command .= ' --limit='.$limit;
        }

        return $command;
    }

    private function preflightStatus(array $configStatus): array
    {
        $callbackUrl = (string) ($configStatus['callback_url'] ?? '');

        return [
            'base_url_configured' => filled($configStatus['base_url'] ?? null),
            'environment' => $configStatus['environment'] ?? 'staging',
            'consumer_name_configured' => (bool) ($configStatus['consumer_name'] ?? false),
            'consumer_key_configured' => (bool) ($configStatus['consumer_key'] ?? false),
            'consumer_secret_configured' => (bool) ($configStatus['consumer_secret'] ?? false),
            'callback_url_configured' => filled($callbackUrl),
            'callback_route_exists' => Route::has('admin.parts.mobilesentrix.callback'),
            'callback_url_allowed' => $this->callbackUrlAllowed($callbackUrl),
            'access_token_configured' => (bool) ($configStatus['access_token'] ?? false),
            'access_token_secret_configured' => (bool) ($configStatus['access_token_secret'] ?? false),
            'last_authenticated_at' => $configStatus['last_authenticated_at'] ?? null,
        ];
    }

    private function callbackUrlAllowed(string $callbackUrl): bool
    {
        if ($callbackUrl === '') {
            return false;
        }

        $scheme = parse_url($callbackUrl, PHP_URL_SCHEME);
        $host = parse_url($callbackUrl, PHP_URL_HOST);

        if ($scheme === 'https') {
            return true;
        }

        if ($scheme !== 'http' || ! is_string($host)) {
            return false;
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test');
    }

    private function oauthUrlContainsSensitiveCredentials(string $url): bool
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return collect(['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'])
            ->contains(fn (string $parameter): bool => filled($query[$parameter] ?? null));
    }

    private function safeSupportMessage(array $configStatus): string
    {
        $yesNo = fn (bool $value): string => $value ? 'Yes' : 'No';

        return implode(PHP_EOL, [
            'Hello MobileSentrix Support,',
            '',
            'We are integrating the MobileSentrix API for Eclise Technology.',
            '',
            'The OAuth documentation appears to require the first authentication request to be opened as a GET URL with consumer_key and consumer_secret in the query string:',
            '',
            '/oauth/authorize/identifier',
            '',
            'For security reasons, we do not want to expose consumer_secret in the browser address bar.',
            '',
            'Please confirm:',
            '',
            '1. Is there a server-side authentication endpoint that does not expose consumer_secret in the browser URL?',
            '2. Can the identifier endpoint be called securely from the backend only?',
            '3. Is consumer_secret required in the browser URL for Canada preprod?',
            '4. Can we use POST instead of GET for the first OAuth step?',
            '5. Do you support OAuth Authorization header instead of query parameters?',
            '6. Is our Consumer Key/Secret enabled for Canada preprod?',
            '7. Does our public/server IP need to be whitelisted?',
            '8. What exact callback URL is registered for this application?',
            '',
            'Also, please rotate/regenerate our Consumer Key and Consumer Secret because they were exposed during browser-based testing.',
            '',
            'Safe configuration summary:',
            'Base URL: '.(($configStatus['base_url'] ?? null) ?: 'Not configured'),
            'Environment: '.(($configStatus['environment'] ?? null) ?: 'Not configured'),
            'Callback URL: '.(($configStatus['callback_url'] ?? null) ?: 'Not configured'),
            'HTTP status code: '.session('mobilesentrix_http_status', 'Not captured'),
            'Failed attempt at: '.session('mobilesentrix_failed_at', 'Not captured'),
            'Consumer Name configured: '.$yesNo((bool) ($configStatus['consumer_name'] ?? false)),
            'Consumer Key configured: '.$yesNo((bool) ($configStatus['consumer_key'] ?? false)),
            'Consumer Secret configured: '.$yesNo((bool) ($configStatus['consumer_secret'] ?? false)),
            'Cloudflare Ray ID: <paste Ray ID from the block page>',
        ]);
    }

    private function redirectWithMobileSentrixException(MobileSentrixException $exception): RedirectResponse
    {
        return back()
            ->withErrors(['mobilesentrix' => $exception->getMessage()])
            ->with('mobilesentrix_connection_status', 'Failed')
            ->with('mobilesentrix_http_status', $exception->httpStatus() ?? 'Not captured')
            ->with('mobilesentrix_failed_at', now()->toDateTimeString());
    }
}
