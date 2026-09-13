<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

final class AccountUserPhoneSendCodeRequest extends AccountUserPhoneVerifyRequest
{
    public function rules(): array
    {
        return $this->phoneRules();
    }

    public function attributes(): array
    {
        return $this->phoneAttributes();
    }

    public function messages(): array
    {
        return $this->phoneMessages();
    }
}
