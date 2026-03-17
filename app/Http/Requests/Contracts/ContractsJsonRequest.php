<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Базовый FormRequest для AJAX/JSON эндпоинтов в разделе "Договоры".
 * Возвращает единый формат ошибки: { success:false, message:string, errors:object }.
 */
abstract class ContractsJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first() ?: 'Некорректные данные.';

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}

