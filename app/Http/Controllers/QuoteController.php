<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Mail\AdminQuoteSubmittedMail;
use App\Mail\QuoteSubmittedCustomerMail;
use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductBrand;
use App\Models\ProductModel;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
    public function create()
    {
        return view('quotes.create', [
            'deviceTypes' => DeviceType::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'productModels' => ProductModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'issueCategories' => IssueCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreQuoteRequest $request)
    {
        $data = $request->validated();
        $customer = Customer::forUser($request->user());

        if ($request->hasFile('device_image')) {
            $data['device_image'] = $request->file('device_image')->store('quote-images', 'public');
        }

        $data['customer_id'] = $customer->id;
        $data['status'] = 'pending';
        $data['converted_to_repair'] = false;

        $quote = Quote::query()->create($data);

        Mail::to($customer->email)->send(new QuoteSubmittedCustomerMail($quote->load('customer')));

        User::query()
            ->admins()
            ->get()
            ->each(fn (User $admin) => Mail::to($admin->email)->send(new AdminQuoteSubmittedMail($quote)));

        return redirect()->route('quotes.create')->with('status', 'Quote request submitted. We will contact you after reviewing the details.');
    }
}
