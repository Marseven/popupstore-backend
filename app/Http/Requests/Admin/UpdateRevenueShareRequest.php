<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRevenueShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiary_label' => 'sometimes|string|max:255',
            'payout_phone' => 'sometimes|string|max:20',
            'payout_provider' => 'sometimes|string|in:airtelmoney,moovmoney4',
            'percentage' => 'sometimes|numeric|min:0.01|max:100',
        ];
    }
}
