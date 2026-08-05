<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:150'
            ],

            'email' => [
                'required',
                'email',
                'unique:users,email'
            ],

            'password' => [
                'required',
                'min:8'
            ],

            'company_id' => [
                'nullable',
                'exists:companies,id'
            ],

            'branch_id' => [
                'nullable',
                'exists:branches,id'
            ],

            'role' => [
                'required'
            ],

            'is_active' => [
                'nullable'
            ],

        ];
    }
}