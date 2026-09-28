<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'min:8',
            ],

            /*
             * Company ownership is derived from the authenticated user.
             * It must never be accepted from the request payload.
             */
            'company_id' => [
                'prohibited',
            ],

            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'company_id',
                            $companyId
                        )
                    ),
            ],

            'role' => [
                'required',
            ],

            'is_active' => [
                'nullable',
            ],
        ];
    }
}