<?php

namespace App\Http\Requests\Tinkoff;

use Illuminate\Foundation\Http\FormRequest;

class PayoutDelayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ожидаем "YYYY-mm-dd HH:ii" (как сейчас в контроллере)
            'run_at' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }
}

