<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
            ],

            'item_id' => [
                'required',
                'integer',
                'exists:items,id',
            ],

            'date_from' => [
                'required',
                'date',
            ],

            'date_to' => [
                'required',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' =>
                'Gudang wajib dipilih.',

            'warehouse_id.exists' =>
                'Gudang tidak ditemukan.',

            'item_id.required' =>
                'Item wajib dipilih.',

            'item_id.exists' =>
                'Item tidak ditemukan.',

            'date_from.required' =>
                'Tanggal awal wajib diisi.',

            'date_to.required' =>
                'Tanggal akhir wajib diisi.',

            'date_to.after_or_equal' =>
                'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }
}