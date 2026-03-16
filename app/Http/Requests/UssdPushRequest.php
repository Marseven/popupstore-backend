<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UssdPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_id' => 'required|string',
            'phone' => 'required|string|max:20',
            'provider' => 'required|string|in:airtel,moov',
        ];
    }
}
