<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'enquiry_type' => ['required', Rule::in(array_keys(config('eclise.enquiry_types', [])))],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'enquiry_type' => 'enquiry type',
        ];
    }

    public function contactMessageData(): array
    {
        $data = $this->validated();
        $enquiryType = (string) $data['enquiry_type'];
        $enquiryLabel = config("eclise.enquiry_types.{$enquiryType}", 'General Enquiry');

        unset($data['enquiry_type']);

        $data['subject'] = '['.$enquiryLabel.'] '.trim((string) $data['subject']);

        return $data;
    }
}
