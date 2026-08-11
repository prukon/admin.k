<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TestSendPaymentNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('setPrices.paymentNotifications.manage');
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'to_email' => ['required', 'email:rfc', 'max:255'],
            'subject_template' => ['required', 'string', 'max:255'],
            'body_html_template' => ['required', 'string', 'max:50000'],
            'users_price_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('users_prices', 'id')->where(function ($q) use ($partnerId) {
                    $q->whereIn('user_id', function ($sub) use ($partnerId) {
                        $sub->select('id')->from('users')->where('partner_id', $partnerId);
                    });
                }),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'to_email' => 'email получателя',
            'subject_template' => 'тема письма',
            'body_html_template' => 'текст письма',
            'users_price_id' => 'начисление',
        ];
    }

    public function messages(): array
    {
        return [
            'to_email.required' => 'Укажите email для тестовой отправки.',
            'to_email.email' => 'Укажите корректный email.',
            'subject_template.required' => 'Укажите тему письма.',
            'body_html_template.required' => 'Укажите текст письма.',
            'users_price_id.exists' => 'Начисление не найдено или недоступно для текущего партнёра.',
        ];
    }
}
