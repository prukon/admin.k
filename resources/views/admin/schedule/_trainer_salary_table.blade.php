@php
    $formatMoneyRubles = static function ($value): string {
        return number_format((float) round((float) $value), 0, '.', ' ');
    };
    $inputRubles = static function ($value): string {
        return (string) (int) round((float) $value);
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
        <th class="trainer-salary-col trainer-salary-col--count" scope="col" title="Количество тренировок">
            <span class="trainer-salary-th-label">Кол-во<br>трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Ставка за тренировку">
            <span class="trainer-salary-th-label">Ставка<br>трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Сумма за тренировки">
            <span class="trainer-salary-th-label">Сумма<br>трен.</span>
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
                           step="1"
                           value="{{ $inputRubles($row['base_salary']) }}">
                    <div class="invalid-feedback d-none" data-error-for="base_salary"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['base_salary']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                <span class="trainer-salary-readonly trainer-salary-count trainer-salary-trainings-count">{{ (int) $row['trainings_count'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="rate_per_training"
                           min="0"
                           step="1"
                           value="{{ $inputRubles($row['rate_per_training']) }}">
                    <div class="invalid-feedback d-none" data-error-for="rate_per_training"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['rate_per_training']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-trainings-amount">{{ $formatMoneyRubles($row['trainings_amount']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="bonuses"
                           min="0"
                           step="1"
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
                           step="1"
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
            <td colspan="{{ $canManage ? 10 : 9 }}" class="trainer-salary-empty-state">
                Нет активных тренеров
            </td>
        </tr>
    @endforelse
    </tbody>
</table>
