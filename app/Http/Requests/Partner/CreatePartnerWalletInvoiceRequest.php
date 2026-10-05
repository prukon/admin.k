<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Services\PartnerContext;
use App\Services\PartnerWallet\PartnerWalletInvoiceService;
use App\Support\PlatformPaymentMethods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreatePartnerWalletInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('partnerWallet.view');
    }

    public function rules(): array
    {
        $currentPartnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        $allowed = $this->allowedMethods();

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
            'payment_method' => array_values(array_filter([
                'required',
                'string',
                $allowed !== [] ? Rule::in($allowed) : null,
            ])),
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
            'partner_id' => 'партнёр',
            'payment_method' => 'способ оплаты',
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
            'partner_id.in' => 'Нельзя выставить счёт для другой школы.',

            'payment_method.required' => 'Укажите способ оплаты.',
            'payment_method.string' => 'Некорректный способ оплаты.',
            'payment_method.in' => 'Некорректный способ оплаты.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if ($user === null || ! $user->can(PlatformPaymentMethods::PERM_INVOICE_IP)) {
                $validator->errors()->add('payment_method', 'Нет доступного способа оплаты.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $partner = app(PartnerContext::class)->partner();
            if ($partner === null || app(PartnerWalletInvoiceService::class)->buyer($partner) === null) {
                $validator->errors()->add('payment_method', PartnerWalletInvoiceService::NO_LEGAL_ENTITY_MESSAGE);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function allowedMethods(): array
    {
        $user = $this->user();
        if ($user === null || ! $user->can(PlatformPaymentMethods::PERM_INVOICE_IP)) {
            return [];
        }

        return [PlatformPaymentMethods::METHOD_INVOICE_IP];
    }
}
