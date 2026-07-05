<?php

namespace App\Http\Controllers;

use App\Support\MobileSentrixDeviceFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertifiedPreOwnedDeviceController extends Controller
{
    public function index(Request $request, MobileSentrixDeviceFilters $filters): View|JsonResponse
    {
        $devices = $filters->query($request, customer: true)
            ->paginate($filters->perPage($request))
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'html' => view('shop.certified-pre-owned-devices.partials.table', [
                    'devices' => $devices,
                    'filterOptions' => $filters->filterOptions(customer: true),
                ])->render(),
                'chips_html' => view('shop.certified-pre-owned-devices.partials.chips', [
                    'selectedFilterGroups' => $filters->selectedFilterGroups($request),
                ])->render(),
                'total' => $devices->total(),
                'first_item' => $devices->firstItem(),
                'last_item' => $devices->lastItem(),
            ]);
        }

        return view('shop.certified-pre-owned-devices.index', [
            'devices' => $devices,
            'filterOptions' => $filters->filterOptions(customer: true),
            'selectedFilterGroups' => $filters->selectedFilterGroups($request),
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function export(Request $request, MobileSentrixDeviceFilters $filters): StreamedResponse
    {
        $rows = $filters->csvRows($filters->query($request, customer: true));
        $filename = 'certified-pre-owned-devices-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Make', 'Model', 'Size', 'Color', 'Condition', 'Carrier', 'Available Qty', 'Price', 'SKU', 'Entity ID']);

            foreach ($rows as $row) {
                unset($row['Synced At']);
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
