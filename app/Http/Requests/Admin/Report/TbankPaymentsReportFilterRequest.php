<?php

namespace App\Http\Requests\Admin\Report;

use App\Models\TinkoffPayment;
use Illuminate\Foundation\Http\FormRequest;

class TbankPaymentsReportFilterRequest extends FormRequest
{
    public const STATUSES = ['NEW', 'FORM', 'CONFIRMED', 'REJECTED', 'CANCELED'];

    public const METHODS = TinkoffPayment::METHODS;

    public const VIEWS = ['payments', 'days', 'months'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        if ($status === 'all' || $status === '') {
            $this->merge(['status' => null]);
        }

        $method = $this->input('method');
        if ($method === 'all' || $method === '') {
            $this->merge(['method' => null]);
        }

        $withoutPayout = $this->input('without_payout');
        if ($withoutPayout === 'all' || $withoutPayout === '') {
            $this->merge(['without_payout' => null]);
        }

        $view = $this->input('view');
        if ($view === '' || $view === null) {
            $this->merge(['view' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'method' => ['nullable', 'string', 'in:'.implode(',', self::METHODS)],
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'without_payout' => ['nullable', 'boolean'],
            'view' => ['nullable', 'string', 'in:'.implode(',', self::VIEWS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Статус',
            'method' => 'Способ',
            'partner_id' => 'Партнер',
            'created_from' => 'Создано с',
            'created_to' => 'Создано по',
            'without_payout' => 'Не было выплаты',
            'view' => 'Вид',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Поле «:attribute» содержит недопустимое значение.',
            'method.in' => 'Поле «:attribute» содержит недопустимое значение.',
            'partner_id.integer' => 'Поле «:attribute» должно быть числом.',
            'partner_id.min' => 'Поле «:attribute» должно быть больше нуля.',
            'created_from.date' => 'Поле «:attribute» должно быть датой.',
            'created_to.date' => 'Поле «:attribute» должно быть датой.',
            'created_to.after_or_equal' => 'Поле «:attribute» не может быть раньше даты «Создано с».',
            'without_payout.boolean' => 'Поле «:attribute» содержит недопустимое значение.',
            'view.in' => 'Поле «:attribute» содержит недопустимое значение.',
        ];
    }

    /**
     * @return array{status: ?string, method: ?string, partner_id: ?int, created_from: ?string, created_to: ?string, without_payout: bool}
     */
    public function filters(): array
    {
        $data = $this->validated();

        $partnerId = $data['partner_id'] ?? null;

        return [
            'status' => isset($data['status']) && $data['status'] !== '' && $data['status'] !== 'all'
                ? (string) $data['status']
                : null,
            'method' => isset($data['method']) && $data['method'] !== '' && $data['method'] !== 'all'
                ? (string) $data['method']
                : null,
            'partner_id' => $partnerId !== null ? (int) $partnerId : null,
            'created_from' => isset($data['created_from']) && $data['created_from'] !== '' ? (string) $data['created_from'] : null,
            'created_to' => isset($data['created_to']) && $data['created_to'] !== '' ? (string) $data['created_to'] : null,
            'without_payout' => $this->boolean('without_payout'),
        ];
    }

    public function view(): string
    {
        $data = $this->validated();
        $view = $data['view'] ?? null;
        if ($view === 'days' || $view === 'months') {
            return $view;
        }

        return 'payments';
    }
}
