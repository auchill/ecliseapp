<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Mail\AdminQuoteSubmittedMail;
use App\Mail\QuoteSubmittedCustomerMail;
use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
    public function create()
    {
        return view('quotes.create', [
            'deviceTypes' => DeviceType::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceBrands' => DeviceBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'deviceModels' => DeviceModel::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'issueCategories' => IssueCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreQuoteRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('device_image')) {
            $data['device_image'] = $request->file('device_image')->store('quote-images', 'public');
        }

        $data['quote_number'] = $this->generateQuoteNumber();
        $data['status'] = 'pending';

        $quote = Quote::query()->create($data);

        Mail::to($quote->email)->send(new QuoteSubmittedCustomerMail($quote));

        User::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (User $admin) => Mail::to($admin->email)->send(new AdminQuoteSubmittedMail($quote)));

        return redirect()->route('quotes.create')->with('status', 'Quote request submitted. We will contact you after reviewing the details.');
    }

    private function generateQuoteNumber(): string
    {
        $year = now()->year;
        $next = Quote::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $quoteNumber = sprintf('ECL-QTE-%s-%04d', $year, $next++);
        } while (Quote::query()->where('quote_number', $quoteNumber)->exists());

        return $quoteNumber;
    }
}
