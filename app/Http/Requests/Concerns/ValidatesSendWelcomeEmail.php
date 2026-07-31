<?php

namespace App\Http\Requests\Concerns;

trait ValidatesSendWelcomeEmail
{
    protected function prepareSendWelcomeEmailForValidation(): void
    {
        if ($this->boolean('send_welcome_email')) {
            $this->merge(['password' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendWelcomeEmailRules(): array
    {
        return [
            'send_welcome_email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sendWelcomeEmailAttributes(): array
    {
        return [
            'send_welcome_email' => 'Отправить письмо',
        ];
    }

    protected function validateSendWelcomeEmailRequiresAddress($validator): void
    {
        if (!$this->boolean('send_welcome_email')) {
            return;
        }

        if (method_exists($this, 'shouldSkipSendWelcomeEmailAddressRequirement')
            && $this->shouldSkipSendWelcomeEmailAddressRequirement()) {
            return;
        }

        if (trim((string) $this->input('email', '')) !== '') {
            return;
        }

        // Ученик: можно опереться на parent_email (логин семьи / sibling-письмо).
        if (trim((string) $this->input('parent_email', '')) !== '') {
            return;
        }

        $validator->errors()->add(
            'email',
            'Укажите email для отправки письма с данными для входа.'
        );
    }
}
