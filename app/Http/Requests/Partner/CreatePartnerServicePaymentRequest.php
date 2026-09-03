<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePartnerServicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('servicePayments.view');
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
            'days' => [
                'required',
                'integer',
                'min:1',
                'max:3650',
            ],
            'partner_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('partners', 'id'),
                Rule::in($currentPartnerId > 0 ? [$currentPartnerId] : [0]),
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
            'days' => 'срок',
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

            'days.required' => 'Укажите срок оплаты.',
            'days.integer' => 'Срок должен быть целым числом.',
            'days.min' => 'Срок должен быть не меньше 1 дня.',
            'days.max' => 'Срок слишком большой.',

            'partner_id.required' => 'Не указана школа.',
            'partner_id.integer' => 'Некорректная школа.',
            'partner_id.min' => 'Некорректная школа.',
            'partner_id.exists' => 'Школа не найдена.',
            'partner_id.in' => 'Нельзя оплатить сервис за другую школу.',

            'description.required' => 'Укажите описание.',
            'description.string' => 'Описание должно быть строкой.',
            'description.max' => 'Описание не должно превышать :max символов.',
        ];
    }
}
