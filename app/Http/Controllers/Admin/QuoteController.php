<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteBookingCreatedMail;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\Quote;
use App\Models\RepairBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $quotes = Quote::query()
            ->with('deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'repairBooking')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('quote_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('device_model', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'statuses' => Quote::STATUSES,
        ]);
    }

    public function show(Quote $quote)
    {
        return view('admin.quotes.show', [
            'quote' => $quote->load('deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'repairBooking'),
            'statuses' => Quote::STATUSES,
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Quote::STATUSES))],
            'admin_note' => ['nullable', 'string'],
        ]);

        abort_if($quote->converted_to_booking && $data['status'] !== 'converted_to_booking', 422, 'Converted quotes cannot be moved back.');

        $quote->update($data);

        return redirect()->route('admin.quotes.show', $quote)->with('status', 'Quote updated.');
    }

    public function createBooking(Quote $quote)
    {
        abort_if($quote->converted_to_booking, 422, 'This quote has already been converted.');
        abort_if($quote->status === 'rejected', 422, 'Rejected quotes cannot be converted.');

        return view('admin.quotes.convert', [
            'quote' => $quote->load('deviceType', 'deviceBrand', 'deviceModel', 'issueCategory'),
            'deviceTypes' => DeviceType::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceBrands' => DeviceBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceModels' => DeviceModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'issueCategories' => IssueCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeBooking(Request $request, Quote $quote)
    {
        abort_if($quote->converted_to_booking, 422, 'This quote has already been converted.');
        abort_if($quote->status === 'rejected', 422, 'Rejected quotes cannot be converted.');

        $data = $request->validate([
            'device_type_id' => ['required', 'exists:repair_device_types,id'],
            'device_brand_id' => ['nullable', 'exists:device_brands,id'],
            'device_model_id' => ['nullable', 'exists:device_models,id'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'issue_category_id' => ['required', 'exists:issue_categories,id'],
            'preferred_appointment_date' => ['nullable', 'date'],
            'preferred_appointment_time' => ['nullable', 'date_format:H:i'],
            'repair_item_type' => ['required', 'array', 'min:1'],
            'repair_item_type.*' => ['nullable', Rule::in(['workmanship', 'part', 'other'])],
            'repair_item_name' => ['required', 'array', 'min:1'],
            'repair_item_name.*' => ['nullable', 'string', 'max:255'],
            'repair_item_quantity' => ['required', 'array', 'min:1'],
            'repair_item_quantity.*' => ['nullable', 'numeric', 'min:0.01'],
            'repair_item_unit_price' => ['required', 'array', 'min:1'],
            'repair_item_unit_price.*' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $repairItems = $this->repairItems($data);
        abort_if($repairItems === [], 422, 'Add at least one repair pricing item.');

        $subtotal = collect($repairItems)->sum('total');
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $total = round($subtotal + $taxAmount, 2);

        $booking = DB::transaction(function () use ($quote, $data, $repairItems, $subtotal, $taxAmount, $total, $request): RepairBooking {
            $deviceType = DeviceType::query()->find($data['device_type_id']);
            $deviceBrand = ! empty($data['device_brand_id']) ? DeviceBrand::query()->find($data['device_brand_id']) : null;
            $deviceModel = ! empty($data['device_model_id']) ? DeviceModel::query()->find($data['device_model_id']) : null;
            $issueCategory = IssueCategory::query()->find($data['issue_category_id']);

            $booking = RepairBooking::query()->create([
                'quote_id' => $quote->id,
                'tracking_number' => $this->generateTrackingNumber(),
                'customer_name' => $quote->customer_name,
                'email' => $quote->email,
                'phone' => $quote->phone_number,
                'device_type_id' => $data['device_type_id'],
                'device_type' => $deviceType?->name ?: 'Device',
                'device_brand_id' => $data['device_brand_id'] ?? null,
                'device_brand' => $deviceBrand?->name,
                'device_model_id' => $data['device_model_id'] ?? null,
                'device_model' => $deviceModel?->name ?? $data['device_model'] ?? $quote->device_model,
                'issue_category_id' => $data['issue_category_id'],
                'issue_category' => $issueCategory?->name ?: 'General Diagnosis',
                'issue_description' => $quote->issue_description,
                'preferred_appointment_date' => $data['preferred_appointment_date'] ?? $quote->preferred_date,
                'preferred_appointment_time' => $data['preferred_appointment_time'] ?? $quote->preferred_time,
                'device_image_path' => $quote->device_image,
                'repair_items' => $repairItems,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => 0,
                'shipping_cost' => 0,
                'repair_total' => $total,
                'total_amount' => $total,
                'amount_paid' => 0,
                'balance_due' => $total,
                'payment_status' => $total > 0 ? 'unpaid' : 'paid',
                'repair_status' => $total > 0 ? 'awaiting_customer_payment' : 'awaiting_device',
                'status' => $total > 0 ? 'awaiting_customer_payment' : 'awaiting_device',
                'fulfillment_method' => 'pickup',
                'pickup_or_shipping_option' => 'pickup',
                'terms_accepted' => false,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            $booking->statusUpdates()->create([
                'status' => $booking->repair_status,
                'note' => 'Repair booking created from quote '.$quote->quote_number.'.',
                'is_customer_visible' => true,
                'created_by' => $request->user()->id,
            ]);

            $quote->update([
                'status' => 'converted_to_booking',
                'converted_to_booking' => true,
            ]);

            return $booking;
        });

        Mail::to($booking->email)->send(new QuoteBookingCreatedMail($booking->fresh('quote')));

        return redirect()->route('admin.repairs.show', $booking)->with('status', 'Quote converted to repair booking.');
    }

    private function repairItems(array $data): array
    {
        return collect($data['repair_item_name'])
            ->filter(fn (?string $name): bool => filled($name))
            ->map(function (string $name, int $index) use ($data): array {
                $quantity = round((float) ($data['repair_item_quantity'][$index] ?? 1), 2);
                $unitPrice = round((float) ($data['repair_item_unit_price'][$index] ?? 0), 2);

                return [
                    'type' => $data['repair_item_type'][$index] ?? 'other',
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                ];
            })
            ->values()
            ->all();
    }

    private function generateTrackingNumber(): string
    {
        $year = now()->year;
        $next = RepairBooking::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $trackingNumber = sprintf('ECL-REP-%s-%04d', $year, $next++);
        } while (RepairBooking::query()->where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }
}
