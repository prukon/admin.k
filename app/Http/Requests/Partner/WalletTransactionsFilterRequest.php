<?php

declare(strict_types=1);

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WalletTransactionsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('partnerWallet.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'succeeded', 'canceled', 'failed'])],
            'provider' => ['nullable', 'string', Rule::in(['tinkoff', 'yookassa', 'manual', 'refund'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'дата начала',
            'date_to' => 'дата конца',
            'type' => 'тип',
            'status' => 'статус',
            'provider' => 'провайдер',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date' => 'Укажите дату начала периода.',
            'date_to.date' => 'Укажите дату конца периода.',
            'date_to.after_or_equal' => 'Дата конца не может быть раньше даты начала.',
            'type.in' => 'Некорректный тип операции.',
            'status.in' => 'Некорректный статус.',
            'provider.in' => 'Некорректный провайдер.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        foreach (['date_from', 'date_to', 'type', 'status', 'provider'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }
            $value = $this->input($field);
            $merged[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
