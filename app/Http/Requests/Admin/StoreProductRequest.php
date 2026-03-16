<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'collection_id' => 'nullable|integer|exists:collections,id',
            'media_content_id' => 'nullable|integer|exists:media_contents,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|max:5120',
            'primary_image_index' => 'nullable|integer|min:0',
            'stocks' => 'nullable|array',
            'stocks.*.size_id' => 'required_with:stocks|integer|exists:sizes,id',
            'stocks.*.quantity' => 'required_with:stocks|integer|min:0',
            'stocks.*.low_stock_threshold' => 'nullable|integer|min:0',
        ];
    }
}
