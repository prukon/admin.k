<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;

class SmPatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_type'        => ['required', 'string', 'in:individual_entrepreneur,company,physical_person,non_commercial_organization'],
            'title'                => ['required', 'string', 'max:255'],
            'organization_name'    => ['required', 'string', 'max:255'],
            'email'                => ['required', 'email'],
            'tax_id'               => ['required', 'string', 'max:20'],
            'registration_number'  => ['required', 'string', 'max:20'],
            'address'              => ['required', 'string', 'max:255'],
            'city'                 => ['required', 'string', 'max:100'],
            'zip'                  => ['required', 'string', 'max:20'],

            'bank_name'            => ['required', 'string', 'max:255'],
            'bank_bik'             => ['required', 'string', 'max:20'],
            'bank_account'         => ['required', 'string', 'max:32'],
            'sm_details_template'  => ['required', 'string', 'max:500'],

            'phone'                => ['nullable', 'string', 'max:32'],
            'website'              => ['nullable', 'url', 'max:255'],
            'kpp'                  => ['nullable', 'string', 'max:12'],
        ];
    }
}

