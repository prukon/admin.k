<?php

namespace App\Http\Requests\Admin;

use App\Models\UserCustomPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserCustomPaymentUpdateRequest extends FormRequest
{
    private ?UserCustomPayment $payment = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function payment(): ?UserCustomPayment
    {
        if ($this->payment !== null) {
            return $this->payment;
        }

        $partnerId = (int) (app('current_partner')->id ?? 0);
        $id = (int) $this->route('id');

        if ($id <= 0 || $partnerId <= 0) {
            return null;
        }

        $this->payment = UserCustomPayment::query()
            ->whereKey($id)
            ->where('partner_id', $partnerId)
            ->first();

        return $this->payment;
    }

    public function rules(): array
    {
        $payment = $this->payment();
        $isPaid = $payment ? $payment->effective_is_paid : false;
        $currentPaid = $isPaid;

        return [
            'amount' => $isPaid
                ? ['prohibited']
                : ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_paid' => [
                'required',
                'boolean',
            ],
            'status_comment' => [
                Rule::requiredIf(function () use ($currentPaid) {
                    if (! $this->exists('is_paid')) {
                        return false;
                    }

                    return (bool) $this->boolean('is_paid') !== $currentPaid;
                }),
                'nullable',
                'string',
                'min:3',
                'max:5000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'сумма',
            'note' => 'описание',
            'is_paid' => 'статус оплаты',
            'status_comment' => 'комментарий к статусу оплаты',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите сумму.',
            'amount.prohibited' => 'Сумму оплаченного платежа менять нельзя.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть больше нуля.',
            'amount.max' => 'Сумма слишком большая.',

            'note.string' => 'Описание должно быть строкой.',
            'note.max' => 'Описание слишком длинное (максимум 255 символов).',

            'is_paid.required' => 'Укажите статус оплаты.',
            'is_paid.boolean' => 'Некорректный статус оплаты.',

            'status_comment.required' => 'Укажите комментарий к изменению статуса оплаты.',
            'status_comment.string' => 'Комментарий к статусу должен быть строкой.',
            'status_comment.min' => 'Комментарий к статусу слишком короткий (минимум 3 символа).',
            'status_comment.max' => 'Комментарий к статусу слишком длинный (максимум 5000 символов).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->payment() === null) {
                $validator->errors()->add('id', 'Дополнительный платеж не найден или недоступен в контексте текущего партнёра.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_paid')) {
            $raw = $this->input('is_paid');
            if ($raw === '1' || $raw === 1 || $raw === true || $raw === 'true' || $raw === 'on') {
                $this->merge(['is_paid' => true]);
            } elseif ($raw === '0' || $raw === 0 || $raw === false || $raw === 'false') {
                $this->merge(['is_paid' => false]);
            }
        }
    }
}
