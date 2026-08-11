<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Tracking requires authentication and results are scoped to the signed-in customer's
        // own repairs, so no contact detail is collected or needed.
        return [
            'repair_number' => ['required', 'string', 'max:40'],
        ];
    }
}
