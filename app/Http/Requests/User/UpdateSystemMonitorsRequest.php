<?php

namespace App\Http\Requests\User;

use App\Support\SystemMonitors;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSystemMonitorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(SystemMonitors::PERMISSION);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('system_monitors')) {
            return;
        }

        $raw = $this->input('system_monitors');
        if (is_bool($raw)) {
            return;
        }

        $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool !== null) {
            $this->merge([
                'system_monitors' => $bool,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'system_monitors' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'system_monitors' => 'Системные мониторы',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'system_monitors.required' => 'Укажите состояние системных мониторов.',
            'system_monitors.boolean' => 'Некорректное значение системных мониторов.',
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Системные мониторы доступны только с правом просмотра.',
        ], 403));
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
