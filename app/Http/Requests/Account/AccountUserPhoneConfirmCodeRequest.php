<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

final class AccountUserPhoneConfirmCodeRequest extends AccountUserPhoneVerifyRequest
{
    public function rules(): array
    {
        return array_merge($this->phoneRules(), [
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge($this->phoneAttributes(), [
            'code' => 'Код',
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->phoneMessages(), [
            'code.required' => 'Введите код из SMS.',
            'code.string' => 'Поле «:attribute» должно быть строкой.',
            'code.regex' => 'Введите корректный код.',
        ]);
    }
}
