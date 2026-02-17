<?php

namespace App\Http\Requests\Contracts;

class ContractSendRequest extends ContractsJsonRequest
{
    public function rules(): array
    {
        return [
            'signer_lastname'   => ['required', 'string', 'max:100'],
            'signer_firstname'  => ['required', 'string', 'max:100'],
            'signer_middlename' => ['nullable', 'string', 'max:100'],
            'signer_phone'      => ['required', 'string', 'regex:/^7\d{10}$/'],
            'ttl_hours'         => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }

    public function attributes(): array
    {
        return [
            'signer_lastname'   => 'Фамилия',
            'signer_firstname'  => 'Имя',
            'signer_middlename' => 'Отчество',
            'signer_phone'      => 'Телефон',
            'ttl_hours'         => 'Срок действия (часы)',
        ];
    }

    public function messages(): array
    {
        return [
            'signer_lastname.required'  => 'Укажите фамилию подписанта.',
            'signer_lastname.string'    => 'Поле «:attribute» должно быть строкой.',
            'signer_lastname.max'       => 'Поле «:attribute» не должно превышать :max символов.',

            'signer_firstname.required' => 'Укажите имя подписанта.',
            'signer_firstname.string'   => 'Поле «:attribute» должно быть строкой.',
            'signer_firstname.max'      => 'Поле «:attribute» не должно превышать :max символов.',

            'signer_middlename.string'  => 'Поле «:attribute» должно быть строкой.',
            'signer_middlename.max'     => 'Поле «:attribute» не должно превышать :max символов.',

            'signer_phone.required' => 'Укажите номер телефона подписанта.',
            'signer_phone.string'   => 'Поле «:attribute» должно быть строкой.',
            'signer_phone.regex'    => 'Укажите номер в формате +7 (XXX) XXX-XX-XX.',

            'ttl_hours.integer' => 'Поле «:attribute» должно быть числом.',
            'ttl_hours.min'     => 'Поле «:attribute» не может быть меньше :min.',
            'ttl_hours.max'     => 'Поле «:attribute» не может быть больше :max.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Трим ФИО
        foreach (['signer_lastname', 'signer_firstname', 'signer_middlename'] as $k) {
            $v = $this->input($k);
            if (is_string($v)) {
                $this->merge([$k => trim(preg_replace('/\s+/', ' ', $v))]);
            }
        }

        // Нормализация телефона (RU): к 11 цифрам, ведущая 7
        $raw = $this->input('signer_phone');
        if (!is_string($raw)) {
            return;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits[0] = '7';
        }

        $this->merge(['signer_phone' => $digits]);
    }
}

