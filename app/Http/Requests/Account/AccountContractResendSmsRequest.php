<?php

namespace App\Http\Requests\Account;

use App\Models\Contract;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Foundation\Http\FormRequest;

class AccountContractResendSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');
        $actor = $this->user();

        return $contract instanceof Contract
            && $actor instanceof User
            && app(FamilyStudentContextService::class)->canAccessContract($actor, $contract);
    }

    public function rules(): array
    {
        return [
            'signer_phone'      => ['prohibited'],
            'signer_lastname'   => ['prohibited'],
            'signer_firstname'  => ['prohibited'],
            'signer_middlename' => ['prohibited'],
            'sid'               => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'signer_phone'      => 'Телефон',
            'signer_lastname'   => 'Фамилия',
            'signer_firstname'  => 'Имя',
            'signer_middlename' => 'Отчество',
            'sid'               => 'SID подписанта',
        ];
    }

    public function messages(): array
    {
        return [
            'signer_phone.prohibited'      => 'Номер телефона при повторной отправке изменить нельзя.',
            'signer_lastname.prohibited'   => 'Данные подписанта при повторной отправке изменить нельзя.',
            'signer_firstname.prohibited'  => 'Данные подписанта при повторной отправке изменить нельзя.',
            'signer_middlename.prohibited' => 'Данные подписанта при повторной отправке изменить нельзя.',
            'sid.prohibited'               => 'Повторная отправка SMS не принимает дополнительных параметров.',
        ];
    }
}
