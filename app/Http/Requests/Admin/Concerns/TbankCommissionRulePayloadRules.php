<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Rule;

trait TbankCommissionRulePayloadRules
{
    protected function prepareTbankCommissionRulePayload(): void
    {
        $partnerId = $this->input('partner_id');
        if ($partnerId === '' || $partnerId === null || (int) $partnerId <= 0) {
            $this->merge(['partner_id' => null]);
        }

        if ($this->input('method') === '') {
            $this->merge(['method' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function tbankCommissionRuleRules(): array
    {
        $rules = [
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'method' => ['nullable', Rule::in(['card', 'sbp', 'tpay'])],
            'acquiring_percent' => ['required', 'numeric', 'min:0'],
            'acquiring_min_fixed' => ['required', 'numeric', 'min:0'],
            'payout_percent' => ['required', 'numeric', 'min:0'],
            'payout_min_fixed' => ['required', 'numeric', 'min:0'],
            'platform_percent' => ['required', 'numeric', 'min:0'],
            'platform_min_fixed' => ['required', 'numeric', 'min:0'],
            'min_fixed' => ['sometimes', 'numeric', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'auto_payout_enabled' => ['nullable', 'boolean'],
            'auto_payout_delay_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ];

        if ($this->filled('partner_id') && (int) $this->input('partner_id') > 0) {
            $rules['auto_payout_delay_hours'] = ['required', 'integer', 'min:0', 'max:720'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function tbankCommissionRuleAttributes(): array
    {
        return [
            'partner_id' => 'партнёр',
            'method' => 'метод',
            'acquiring_percent' => 'процент эквайринга банка',
            'acquiring_min_fixed' => 'минимальная сумма эквайринга',
            'payout_percent' => 'процент выплаты банка',
            'payout_min_fixed' => 'минимальная сумма выплаты банка',
            'platform_percent' => 'комиссия платформы',
            'platform_min_fixed' => 'минимальная комиссия платформы',
            'min_fixed' => 'минимальная комиссия',
            'is_enabled' => 'активность',
            'auto_payout_enabled' => 'автовыплата',
            'auto_payout_delay_hours' => 'задержка автовыплаты',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function tbankCommissionRuleMessages(): array
    {
        return [
            'partner_id.exists' => 'Выберите существующего партнёра.',
            'method.in' => 'Метод должен быть card, sbp или tpay.',
            'acquiring_percent.required' => 'Укажите процент эквайринга банка.',
            'acquiring_percent.numeric' => 'Процент эквайринга банка должен быть числом.',
            'acquiring_percent.min' => 'Процент эквайринга банка не может быть отрицательным.',
            'acquiring_min_fixed.required' => 'Укажите минимальную сумму эквайринга.',
            'acquiring_min_fixed.numeric' => 'Минимальная сумма эквайринга должна быть числом.',
            'acquiring_min_fixed.min' => 'Минимальная сумма эквайринга не может быть отрицательной.',
            'payout_percent.required' => 'Укажите процент выплаты банка.',
            'payout_percent.numeric' => 'Процент выплаты банка должен быть числом.',
            'payout_percent.min' => 'Процент выплаты банка не может быть отрицательным.',
            'payout_min_fixed.required' => 'Укажите минимальную сумму выплаты банка.',
            'payout_min_fixed.numeric' => 'Минимальная сумма выплаты банка должна быть числом.',
            'payout_min_fixed.min' => 'Минимальная сумма выплаты банка не может быть отрицательной.',
            'platform_percent.required' => 'Укажите комиссию платформы.',
            'platform_percent.numeric' => 'Комиссия платформы должна быть числом.',
            'platform_percent.min' => 'Комиссия платформы не может быть отрицательной.',
            'platform_min_fixed.required' => 'Укажите минимальную комиссию платформы.',
            'platform_min_fixed.numeric' => 'Минимальная комиссия платформы должна быть числом.',
            'platform_min_fixed.min' => 'Минимальная комиссия платформы не может быть отрицательной.',
            'auto_payout_delay_hours.required' => 'Укажите задержку автовыплаты в часах.',
            'auto_payout_delay_hours.integer' => 'Задержка автовыплаты должна быть целым числом часов.',
            'auto_payout_delay_hours.min' => 'Задержка автовыплаты не может быть отрицательной.',
            'auto_payout_delay_hours.max' => 'Задержка автовыплаты не может быть больше 720 часов.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commissionRulePayload(): array
    {
        $data = $this->validated();
        $data['is_enabled'] = $this->boolean('is_enabled');
        $data['min_fixed'] = (float) ($data['min_fixed'] ?? 0);

        if (! empty($data['partner_id']) && (int) $data['partner_id'] > 0) {
            $data['partner_id'] = (int) $data['partner_id'];
            $data['auto_payout_enabled'] = $this->boolean('auto_payout_enabled');
            $data['auto_payout_delay_hours'] = (int) ($data['auto_payout_delay_hours'] ?? 0);
        } else {
            $data['partner_id'] = null;
            $data['auto_payout_enabled'] = false;
            $data['auto_payout_delay_hours'] = 0;
        }

        if (empty($data['method'])) {
            $data['method'] = null;
        }

        return $data;
    }
}
