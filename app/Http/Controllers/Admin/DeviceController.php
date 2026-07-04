<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncMobileSentrixDevicesJob;
use App\Support\MobileSentrixDeviceFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeviceController extends Controller
{
    public function index(Request $request, MobileSentrixDeviceFilters $filters): View
    {
        $devices = $filters->query($request)
            ->paginate($filters->perPage($request))
            ->withQueryString();

        return view('admin.devices.index', [
            'devices' => $devices,
            'filterOptions' => $filters->filterOptions(),
            'selectedChips' => $filters->selectedChips($request),
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function export(Request $request, MobileSentrixDeviceFilters $filters): StreamedResponse
    {
        $rows = $filters->csvRows($filters->query($request));
        $filename = 'mobilesentrix-devices-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Make', 'Model', 'Size', 'Color', 'Condition', 'Carrier', 'Available Qty', 'Price', 'SKU', 'Entity ID', 'Synced At']);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (config('queue.default') === 'sync') {
            return back()->withErrors([
                'devices' => 'Queue is not configured for long MobileSentrix device syncs. Run from terminal: php artisan mobilesentrix:sync-devices --limit='.($validated['limit'] ?? 30).' --page='.($validated['page'] ?? 1),
            ]);
        }

        SyncMobileSentrixDevicesJob::dispatch((int) ($validated['limit'] ?? 30), (int) ($validated['page'] ?? 1));

        return back()->with('status', 'Device sync has started. Please refresh shortly.');
    }
}
