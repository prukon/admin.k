<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePartnerWalletTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('partnerWallet.view');
    }

    public function rules(): array
    {
        $currentPartnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999.99',
            ],
            'partner_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('partners', 'id'),
                Rule::in($currentPartnerId > 0 ? [$currentPartnerId] : [0]),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
            'partner_id' => 'партнёр',
            'description' => 'описание',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите сумму.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть не меньше 1 ₽.',
            'amount.max' => 'Сумма слишком большая.',

            'partner_id.required' => 'Не указана школа.',
            'partner_id.integer' => 'Некорректная школа.',
            'partner_id.min' => 'Некорректная школа.',
            'partner_id.exists' => 'Школа не найдена.',
            'partner_id.in' => 'Нельзя пополнить кошелёк другой школы.',

            'description.string' => 'Описание должно быть строкой.',
            'description.max' => 'Описание не должно превышать :max символов.',
        ];
    }
}
