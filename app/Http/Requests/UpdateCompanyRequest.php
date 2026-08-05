<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'code' => [
                'required',
                'max:20',
                Rule::unique('companies', 'code')
                    ->ignore($companyId),
            ],

            'name' => [
                'required',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'max:50',
            ],

            'email' => [
                'nullable',
                'email',
            ],

            'address' => [
                'nullable',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }


}