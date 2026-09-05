<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Services\PartnerContext;
use App\Services\Tinkoff\TbankAcquiringTerminalConfig;
use App\Support\PlatformPaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $isSbp = $this->input('payment_method') === PlatformPaymentMethods::METHOD_TBANK_SBP;
        $allowedMethods = PlatformPaymentMethods::allowedMethods($this->user());

        return [
            'amount' => [
                'required',
                'numeric',
                $isSbp ? 'min:'.TbankAcquiringTerminalConfig::SBP_MIN_RUB : 'min:1',
                $isSbp ? 'max:'.TbankAcquiringTerminalConfig::SBP_MAX_RUB : 'max:99999999.99',
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
            'payment_method' => array_values(array_filter([
                'required',
                'string',
                $allowedMethods !== [] ? Rule::in($allowedMethods) : null,
            ])),
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
            'partner_id' => 'партнёр',
            'description' => 'описание',
            'payment_method' => 'способ оплаты',
        ];
    }

    public function messages(): array
    {
        $isSbp = $this->input('payment_method') === PlatformPaymentMethods::METHOD_TBANK_SBP;

        return [
            'amount.required' => 'Укажите сумму.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => $isSbp
                ? 'Сумма для СБП должна быть не меньше 10 ₽.'
                : 'Сумма должна быть не меньше 1 ₽.',
            'amount.max' => $isSbp
                ? 'Сумма для СБП не должна превышать 1 000 000 ₽.'
                : 'Сумма слишком большая.',

            'partner_id.required' => 'Не указана школа.',
            'partner_id.integer' => 'Некорректная школа.',
            'partner_id.min' => 'Некорректная школа.',
            'partner_id.exists' => 'Школа не найдена.',
            'partner_id.in' => 'Нельзя пополнить кошелёк другой школы.',

            'description.string' => 'Описание должно быть строкой.',
            'description.max' => 'Описание не должно превышать :max символов.',

            'payment_method.required' => 'Укажите способ оплаты.',
            'payment_method.string' => 'Некорректный способ оплаты.',
            'payment_method.in' => 'Некорректный способ оплаты.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (PlatformPaymentMethods::allowedMethods($this->user()) === []) {
                $validator->errors()->add('payment_method', 'Нет доступного способа оплаты.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('payment_method')) {
            return;
        }

        $default = PlatformPaymentMethods::defaultMethod($this->user());
        if ($default !== null) {
            $this->merge(['payment_method' => $default]);
        }
    }
}
