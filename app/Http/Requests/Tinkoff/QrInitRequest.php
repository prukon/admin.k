<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;

class QrInitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // partner_id не принимаем из запроса — берётся только из app('current_partner')->id (безопасность)
            'outSum' => ['required', 'string', 'max:32'],
        ];
    }
}

