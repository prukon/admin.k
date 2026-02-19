<?php

namespace App\Http\Requests\Tinkoff\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TinkoffPayoutsDataTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:200'],

            'partner_id' => ['nullable'],
            'status' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:20'],
            'deal_id' => ['nullable', 'string', 'max:128'],
            'tinkoff_payout_payment_id' => ['nullable', 'string', 'max:64'],
            'tinkoff_payment_id' => ['nullable', 'integer', 'min:1'],

            'payer_query' => ['nullable', 'string', 'max:255'],
            'initiator_query' => ['nullable', 'string', 'max:255'],

            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'run_from' => ['nullable', 'date'],
            'run_to' => ['nullable', 'date'],
            'completed_from' => ['nullable', 'date'],
            'completed_to' => ['nullable', 'date'],

            'gross_min' => ['nullable', 'numeric', 'min:0'],
            'gross_max' => ['nullable', 'numeric', 'min:0'],
            'net_min' => ['nullable', 'numeric', 'min:0'],
            'net_max' => ['nullable', 'numeric', 'min:0'],

            'stuck_only' => ['nullable'],
            'stuck_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'Партнёр',
            'status' => 'Статус',
            'source' => 'Источник',
            'deal_id' => 'DealId',
            'tinkoff_payout_payment_id' => 'T‑Bank payout PaymentId',
            'tinkoff_payment_id' => 'ID платежа (в системе)',
            'payer_query' => 'Плательщик',
            'initiator_query' => 'Инициатор',
            'created_from' => 'Создана с',
            'created_to' => 'Создана по',
            'run_from' => 'Запланирована с',
            'run_to' => 'Запланирована по',
            'completed_from' => 'Завершена с',
            'completed_to' => 'Завершена по',
            'gross_min' => 'Gross от',
            'gross_max' => 'Gross до',
            'net_min' => 'Net от',
            'net_max' => 'Net до',
            'stuck_only' => 'Только “застрявшие”',
            'stuck_minutes' => 'Минут без обновления',
        ];
    }

    public function messages(): array
    {
        return [
            'integer' => 'Поле «:attribute» должно быть целым числом.',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'min' => 'Поле «:attribute» должно быть не меньше :min.',
            'max' => 'Поле «:attribute» должно быть не больше :max.',
            'date' => 'Поле «:attribute» должно быть корректной датой.',
            'string' => 'Поле «:attribute» должно быть строкой.',
        ];
    }
}

