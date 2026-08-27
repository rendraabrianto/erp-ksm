<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_category_id' => ['required','exists:item_categories,id'],
            'uom_id'           => ['required','exists:uoms,id'],
            'code'             => ['required','max:30','unique:items,code'],
            'name'             => ['required','max:150'],
            'description'      => ['nullable'],
            'minimum_stock'    => ['nullable','numeric'],
            'maximum_stock'    => ['nullable','numeric'],
        ];
    }
}