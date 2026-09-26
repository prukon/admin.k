<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

final class ShowPartnerWalletCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('partnerWallet.view');
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:99999999.99',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите сумму.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть не меньше 1 ₽.',
            'amount.max' => 'Сумма слишком большая.',
        ];
    }
}
