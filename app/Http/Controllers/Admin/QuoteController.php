<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteBookingCreatedMail;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductBrand;
use App\Models\ProductModel;
use App\Models\Quote;
use App\Models\Repair;
use App\Services\RepairNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $quotes = Quote::query()
            ->with('customer', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'repair')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('id', $search)
                        ->orWhere('quote_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('device_model', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search): void {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
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
            'quote' => $quote->load('customer', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory', 'repair'),
            'statuses' => Quote::STATUSES,
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Quote::STATUSES))],
            'admin_note' => ['nullable', 'string'],
        ]);

        abort_if($quote->converted_to_repair && $data['status'] !== 'converted_to_repair', 422, 'Converted quotes cannot be moved back.');

        $quote->update($data);

        return redirect()->route('admin.quotes.show', $quote)->with('status', 'Quote updated.');
    }

    public function createBooking(Quote $quote)
    {
        abort_if($quote->converted_to_repair, 422, 'This quote has already been converted.');
        abort_if($quote->status === 'rejected', 422, 'Rejected quotes cannot be converted.');

        return view('admin.quotes.convert', [
            'quote' => $quote->load('customer', 'deviceType', 'deviceBrand', 'deviceModel', 'issueCategory'),
            'deviceTypes' => DeviceType::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'issueCategories' => IssueCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeBooking(Request $request, Quote $quote, RepairNumberGenerator $repairNumbers)
    {
        abort_if($quote->converted_to_repair, 422, 'This quote has already been converted.');
        abort_if($quote->status === 'rejected', 422, 'Rejected quotes cannot be converted.');
        abort_unless($quote->customer, 422, 'This quote is not linked to a customer.');

        $data = $request->validate([
            'device_type_id' => ['required', 'exists:repair_device_types,id'],
            'product_brand_id' => ['nullable', 'exists:product_brands,id'],
            'product_model_id' => ['nullable', 'exists:product_models,id'],
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

        $repair = DB::transaction(function () use ($quote, $data, $repairItems, $subtotal, $taxAmount, $total, $request, $repairNumbers): Repair {
            $quote = Quote::query()->lockForUpdate()->with('customer')->findOrFail($quote->id);

            if ($quote->converted_to_repair || $quote->repair()->exists()) {
                return $quote->repair()->firstOrFail();
            }

            $deviceType = DeviceType::query()->find($data['device_type_id']);
            $productBrand = ! empty($data['product_brand_id']) ? ProductBrand::query()->find($data['product_brand_id']) : null;
            $productModel = ! empty($data['product_model_id']) ? ProductModel::query()->find($data['product_model_id']) : null;
            $issueCategory = IssueCategory::query()->find($data['issue_category_id']);
            $repairNumber = $repairNumbers->next();

            $repair = Repair::query()->create([
                'customer_id' => $quote->customer_id,
                'user_id' => $quote->customer?->user_id,
                'quote_id' => $quote->id,
                'repair_number' => $repairNumber,
                'tracking_number' => $repairNumber,
                'customer_name' => $quote->customer_name,
                'email' => $quote->email,
                'phone' => $quote->phone_number,
                'device_type_id' => $data['device_type_id'],
                'device_type' => $deviceType?->name ?: 'Device',
                'product_brand_id' => $data['product_brand_id'] ?? null,
                'device_brand' => $productBrand?->name,
                'product_model_id' => $data['product_model_id'] ?? null,
                'device_model' => $productModel?->name ?? $data['device_model'] ?? $quote->device_model,
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

            $repair->statusUpdates()->create([
                'status' => $repair->repair_status,
                'note' => 'Repair created from quote #'.$quote->id.'.',
                'is_customer_visible' => true,
                'created_by' => $request->user()->id,
            ]);

            $quote->update([
                'status' => 'converted_to_repair',
                'converted_to_repair' => true,
                'converted_to_booking' => true,
            ]);

            return $repair;
        });

        Mail::to($repair->email)->send(new QuoteBookingCreatedMail($repair->fresh('quote')));

        return redirect()->route('admin.repairs.show', $repair)->with('status', 'Quote converted to repair.');
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
}
