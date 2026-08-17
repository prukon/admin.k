<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLayoutWideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('layout_wide')) {
            return;
        }

        $raw = $this->input('layout_wide');
        if (is_bool($raw)) {
            return;
        }

        $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool !== null) {
            $this->merge([
                'layout_wide' => $bool,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'layout_wide' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'layout_wide' => 'Ширина кабинета',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'layout_wide.required' => 'Укажите ширину кабинета.',
            'layout_wide.boolean' => 'Некорректное значение ширины кабинета.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! ($this->ajax() || $this->expectsJson())) {
            parent::failedValidation($validator);
        }

        $message = $validator->errors()->first() ?: 'Некорректные данные.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
