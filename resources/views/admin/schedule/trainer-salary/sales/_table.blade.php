@php
    $formatMoneyRubles = static function ($value): string {
        return \App\Support\Money::formatRub(\App\Support\Money::toCentsOrFail($value));
    };
    $inputRubles = static function ($value): string {
        return (string) $value;
    };
@endphp

<table class="trainer-salary-table">
    <thead>
    <tr>
        <th class="trainer-salary-corner" scope="col">
            <span class="trainer-salary-corner-label">Тренер</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Оклад</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col" title="Процент от оплаченных месяцев и абонементов">
            <span class="trainer-salary-th-label">%</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Оплаченные месяца расчётного периода">
            <span class="trainer-salary-th-label">Оплаченные<br>месяца</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Оплаченные абонементы по дате оплаты">
            <span class="trainer-salary-th-label">Абонементы</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Сумма оплаченных месяцев и абонементов">
            <span class="trainer-salary-th-label">База<br>продаж</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Процент от базы продаж">
            <span class="trainer-salary-th-label">% от<br>продаж</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Бонусы</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Вычеты</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--comment" scope="col">
            <span class="trainer-salary-th-label">Коммент.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--total trainer-salary-head--total" scope="col">
            <span class="trainer-salary-th-label">Итого</span>
        </th>
        @if($canManage)
            <th class="trainer-salary-col trainer-salary-col--action" scope="col" title="Расчет ЗП">
                <span class="trainer-salary-th-label">Расчет</span>
            </th>
        @endif
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        @php
            $trainerId = (int) $row['trainer_profile_id'];
            $snapshot = $row['latest_snapshot'] ?? null;
        @endphp
        <tr class="trainer-salary-row" data-trainer-id="{{ $trainerId }}">
            <th scope="row" class="trainer-salary-trainer-name">
                <div>{{ $row['trainer_name'] }}</div>
                @if($snapshot)
                    <div class="trainer-salary-snapshot-hint small text-muted">
                        Слепок v{{ (int) $snapshot['version'] }}
                        @if(!empty($snapshot['formed_at']))
                            · {{ \Illuminate\Support\Carbon::parse($snapshot['formed_at'])->format('d.m.Y H:i') }}
                        @endif
                        @if(!empty($snapshot['formed_by_name']))
                            · {{ $snapshot['formed_by_name'] }}
                        @endif
                    </div>
                @endif
            </th>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="base_salary"
                           min="0"
                           step="0.01"
                           value="{{ $inputRubles($row['base_salary']) }}">
                    <div class="invalid-feedback d-none" data-error-for="base_salary"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['base_salary']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="sales_percent"
                           min="0"
                           max="100"
                           step="1"
                           value="{{ (int) ($row['sales_percent'] ?? 0) }}">
                    <div class="invalid-feedback d-none" data-error-for="sales_percent"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-count">{{ (int) ($row['sales_percent'] ?? 0) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-paid-months">{{ $formatMoneyRubles($row['paid_months']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-paid-packages">{{ $formatMoneyRubles($row['paid_packages']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-sales-base">{{ $formatMoneyRubles($row['sales_base']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-commission">{{ $formatMoneyRubles($row['commission']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="bonuses"
                           min="0"
                           step="0.01"
                           value="{{ $inputRubles($row['bonuses']) }}">
                    <div class="invalid-feedback d-none" data-error-for="bonuses"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['bonuses']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="deductions"
                           min="0"
                           step="0.01"
                           value="{{ $inputRubles($row['deductions']) }}">
                    <div class="invalid-feedback d-none" data-error-for="deductions"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['deductions']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body trainer-salary-cell--comment">
                @if($canManage)
                    <input type="text"
                           class="form-control form-control-sm trainer-salary-input"
                           data-field="comment"
                           maxlength="5000"
                           value="{{ $row['comment'] ?? '' }}">
                    <div class="invalid-feedback d-none" data-error-for="comment"></div>
                @else
                    <span class="trainer-salary-readonly">{{ $row['comment'] ?? '—' }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-value--total trainer-salary-total">{{ $formatMoneyRubles($row['total']) }}</span>
            </td>
            @if($canManage)
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <button type="button"
                            class="btn btn-sm btn-primary trainer-salary-form-one-btn"
                            data-trainer-id="{{ $trainerId }}"
                            title="Расчет ЗП">
                        Расчет
                    </button>
                </td>
            @endif
        </tr>
    @empty
        <tr>
            <td colspan="{{ $canManage ? 12 : 11 }}" class="trainer-salary-empty-state">
                Нет активных тренеров
            </td>
        </tr>
    @endforelse
    </tbody>
</table>
