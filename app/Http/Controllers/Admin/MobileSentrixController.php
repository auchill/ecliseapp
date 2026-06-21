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
use Illuminate\View\View;

class MobileSentrixController extends Controller
{
    public function index(MobileSentrixClient $client): View
    {
        return view('admin.parts.mobilesentrix', [
            'configStatus' => $client->redactedConfigStatus(),
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
                ->with('mobilesentrix_connection_status', 'Failed');
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
            return redirect()
                ->route('admin.parts.mobilesentrix.index')
                ->withErrors(['mobilesentrix' => $exception->getMessage()])
                ->with('mobilesentrix_connection_status', 'Failed');
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
            return back()
                ->withErrors(['mobilesentrix' => 'MobileSentrix API connection failed. Please verify credentials and authenticate again.'])
                ->with('mobilesentrix_connection_status', 'Failed');
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
}
