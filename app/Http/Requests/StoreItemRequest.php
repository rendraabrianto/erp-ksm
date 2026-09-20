<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId =
            (int) $this->user()->company_id;

        return [
            'item_category_id' => [
                'required',

                Rule::exists(
                    'item_categories',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'company_id',
                            $companyId
                        )
                ),
            ],

            'uom_id' => [
                'required',
                'exists:uoms,id',
            ],

            'code' => [
                'required',
                'max:30',

                Rule::unique(
                    'items',
                    'code'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'company_id',
                            $companyId
                        )
                ),
            ],

            'name' => [
                'required',
                'max:150',
            ],

            'description' => [
                'nullable',
            ],

            'minimum_stock' => [
                'nullable',
                'numeric',
            ],

            'maximum_stock' => [
                'nullable',
                'numeric',
            ],
        ];
    }
}