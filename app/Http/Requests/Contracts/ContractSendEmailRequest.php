<?php

namespace App\Http\Requests\Contracts;

class ContractSendEmailRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'email'  => ['required', 'email', 'max:255'],
            'signed' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email'  => 'E-mail',
            'signed' => 'Отправлять подписанный',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Укажите e-mail получателя.',
            'email.email'    => 'Укажите корректный e-mail.',
            'email.max'      => 'E-mail не должен превышать :max символов.',
            'signed.boolean' => 'Поле «:attribute» должно быть true/false.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('signed')) {
            $this->merge([
                'signed' => filter_var($this->input('signed'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => trim($email)]);
        }
    }
}

