<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'missingCredentials' => $client->missingCredentialNames(),
            'latestLogs' => MobileSentrixSyncLog::query()->latest()->limit(12)->get(),
            'lastCategoryLog' => MobileSentrixSyncLog::query()->where('sync_type', 'categories')->latest()->first(),
            'lastPartLog' => MobileSentrixSyncLog::query()->whereIn('sync_type', ['parts', 'single_part'])->latest()->first(),
            'partsCount' => Part::query()->where('is_api_item', true)->count(),
            'categoriesCount' => PartCategory::query()->whereNotNull('mobilesentrix_category_id')->count(),
        ]);
    }

    public function startAuthorization(MobileSentrixAuthService $auth): RedirectResponse
    {
        try {
            return redirect()->away($auth->authorizationUrl());
        } catch (MobileSentrixException $exception) {
            return back()
                ->withErrors(['mobilesentrix' => $exception->getMessage()])
                ->with('mobilesentrix_connection_status', 'Failed')
                ->with('mobilesentrix_http_status', $exception->httpStatus() ?? 'Not captured')
                ->with('mobilesentrix_failed_at', now()->toDateTimeString());
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

            return back()
                ->withErrors(['mobilesentrix' => 'MobileSentrix API connection failed. Please verify credentials and authenticate again.'])
                ->with('mobilesentrix_connection_status', 'Failed')
                ->with('mobilesentrix_http_status', $httpStatus ?? 'Not captured')
                ->with('mobilesentrix_failed_at', now()->toDateTimeString());
        }
    }

    public function syncCategories(Request $request, MobileSentrixSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $syncService->syncCategories($validated['category_id'] ?? null);

        return $this->redirectWithResult($result);
    }

    public function syncParts(Request $request, MobileSentrixSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $syncService->syncParts($validated['category_id'] ?? null);

        return $this->redirectWithResult($result);
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

    private function safeSupportMessage(array $configStatus): string
    {
        $yesNo = fn (bool $value): string => $value ? 'Yes' : 'No';

        return implode(PHP_EOL, [
            'MobileSentrix OAuth support request',
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
}
