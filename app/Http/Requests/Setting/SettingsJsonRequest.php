<?php

namespace App\Http\Requests\Setting;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Базовый FormRequest для AJAX эндпоинтов раздела "Настройки".
 * Возвращает единый формат ошибки:
 * { success:false, message:string, errors:object }
 *
 * Важно: ключи errors приводим к формату name="" инпутов (с квадратными скобками),
 * чтобы фронт мог показать ошибки прямо под соответствующими полями.
 *
 * Пример: menu_items.123.name -> menu_items[123][name]
 */
abstract class SettingsJsonRequest extends FormRequest
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
                'errors'  => $this->bracketizeValidationErrors($validator->errors()->toArray()),
            ], 422)
        );
    }

    /**
     * Приводим ключи ошибок в формат name="" инпутов (с квадратными скобками).
     *
     * Пример: menu_items.123.name -> menu_items[123][name]
     */
    protected function bracketizeValidationErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $key => $messages) {
            $parts = explode('.', (string) $key);
            if (count($parts) >= 3) {
                $root = array_shift($parts);
                $bracket = $root;
                foreach ($parts as $p) {
                    $bracket .= '[' . $p . ']';
                }
                $out[$bracket] = $messages;
            } else {
                $out[$key] = $messages;
            }
        }
        return $out;
    }
}

