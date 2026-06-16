<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRevenueShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiary_label' => 'required|string|max:255',
            'payout_phone' => 'required|string|max:20',
            'payout_provider' => 'required|string|in:airtelmoney,moovmoney4',
            'percentage' => 'required|numeric|min:0.01|max:100',
        ];
    }
}
