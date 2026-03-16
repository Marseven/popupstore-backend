<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $productId,
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'media_content_id' => 'nullable|integer|exists:media_contents,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'primary_image_index' => 'nullable|integer|min:0',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer|exists:product_images,id',
        ];
    }
}
