<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . $userId,
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $userId,
            'avatar' => 'sometimes|nullable|image|max:2048',
        ];
    }
}
