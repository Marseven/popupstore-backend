<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'shipping_name' => 'required|string|max:255',
            'shipping_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'customer_notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|string|max:50',
        ];

        if (!$this->user()) {
            $rules['guest_phone'] = 'required|string|max:20';
            $rules['guest_email'] = 'nullable|email|max:255';
        }

        return $rules;
    }
}
