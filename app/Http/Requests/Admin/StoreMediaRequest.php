<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:audio,video',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'file' => 'required|file|max:512000',
            'thumbnail' => 'nullable|image|max:5120',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
