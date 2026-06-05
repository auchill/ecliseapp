<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairBooking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairController extends Controller
{
    public function index(Request $request)
    {
        $repairs = RepairBooking::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('device_brand', 'like', "%{$search}%")
                        ->orWhere('device_model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.repairs.index', [
            'repairs' => $repairs,
            'statuses' => RepairBooking::STATUSES,
        ]);
    }

    public function show(RepairBooking $repair)
    {
        return view('admin.repairs.show', [
            'repair' => $repair->load('statusUpdates', 'user'),
            'statuses' => RepairBooking::STATUSES,
        ]);
    }

    public function update(Request $request, RepairBooking $repair)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RepairBooking::STATUSES)],
            'estimated_completion_date' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'status_note' => ['nullable', 'string'],
            'is_customer_visible' => ['nullable', 'boolean'],
        ]);

        $statusChanged = $repair->status !== $data['status'];

        $repair->update([
            'status' => $data['status'],
            'estimated_completion_date' => $data['estimated_completion_date'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);

        if ($statusChanged || filled($data['status_note'] ?? null)) {
            $repair->statusUpdates()->create([
                'status' => $data['status'],
                'note' => $data['status_note'] ?? $data['customer_notes'] ?? null,
                'is_customer_visible' => $request->boolean('is_customer_visible', true),
            ]);
        }

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Repair updated.');
    }
}
