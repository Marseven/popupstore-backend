<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'is_active' => 'sometimes|boolean',
            'thumbnail' => 'nullable|image|max:5120',
        ];
    }
}
