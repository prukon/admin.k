<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolWidgetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_leads_telegram_chat_id' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^-?\d+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'school_leads_telegram_chat_id.regex' => 'Chat ID Telegram должен содержать только цифры (допускается минус в начале).',
            'school_leads_telegram_chat_id.max'   => 'Chat ID Telegram слишком длинный.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('school_leads_telegram_chat_id');
        if (is_string($value)) {
            $value = trim($value);
            $this->merge([
                'school_leads_telegram_chat_id' => $value === '' ? null : $value,
            ]);
        }
    }
}
