@php
    $formatMoneyRubles = static function ($value): string {
        return \App\Support\Money::formatRub(\App\Support\Money::toCentsOrFail($value));
    };
    $inputRubles = static function ($value): string {
        return (string) $value;
    };
    $saveTrainerId = (int) (($rows[0]['trainer_profile_id'] ?? 0));
    $colCount = ($canManage ?? false) ? 11 : 10;
@endphp

<div class="trainer-salary-kansas-x mb-3"
     @if($saveTrainerId > 0) data-save-trainer-id="{{ $saveTrainerId }}" @endif>
    <label class="form-label small text-muted mb-1" for="trainer-salary-kansas-premium-increment">
        X — базовая надбавка к премии (на школу за месяц)
    </label>
    <div class="trainer-salary-kansas-x-field">
        @if($canManage ?? false)
            <input type="number"
                   class="form-control form-control-sm trainer-salary-input text-end"
                   id="trainer-salary-kansas-premium-increment"
                   data-field="premium_increment"
                   @if($saveTrainerId > 0) data-save-trainer-id="{{ $saveTrainerId }}" @endif
                   min="0"
                   step="0.01"
                   value="{{ $inputRubles($premium_increment ?? '0.00') }}">
            <div class="invalid-feedback d-none" data-error-for="premium_increment"></div>
        @else
            <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($premium_increment ?? '0.00') }}</span>
        @endif
    </div>
</div>

<table class="trainer-salary-table trainer-salary-table--kansas">
    <thead>
    <tr>
        <th class="trainer-salary-corner" scope="col">
            <span class="trainer-salary-corner-label">Тренер / группа</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Оклад за тренировку">
            <span class="trainer-salary-th-label">Оклад<br>за трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Базовая<br>премия</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col" title="Базовое среднее учеников">
            <span class="trainer-salary-th-label">Баз.<br>среднее</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col" title="Фактическое среднее учеников">
            <span class="trainer-salary-th-label">Факт<br>среднее</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col">
            <span class="trainer-salary-th-label">Разница</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Премия</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Итого за тренировку">
            <span class="trainer-salary-th-label">Итого<br>за трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col" title="Количество тренировок">
            <span class="trainer-salary-th-label">Кол-во<br>трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--total trainer-salary-head--total" scope="col" title="Итого за группу / тренера">
            <span class="trainer-salary-th-label">Итого</span>
        </th>
        @if($canManage ?? false)
            <th class="trainer-salary-col trainer-salary-col--action" scope="col" title="Расчет ЗП">
                <span class="trainer-salary-th-label">Расчет</span>
            </th>
        @endif
    </tr>
    </thead>
    @forelse($rows as $row)
        @php
            $trainerId = (int) $row['trainer_profile_id'];
            $snapshot = $row['latest_snapshot'] ?? null;
            $groups = $row['groups'] ?? [];
        @endphp
        <tbody class="trainer-salary-kansas-block">
        <tr class="trainer-salary-row trainer-salary-kansas-head" data-trainer-id="{{ $trainerId }}">
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
                @if($canManage ?? false)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="rate_per_training"
                           min="0"
                           step="0.01"
                           value="{{ $inputRubles($row['rate_per_training']) }}">
                    <div class="invalid-feedback d-none" data-error-for="rate_per_training"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['rate_per_training']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                @if($canManage ?? false)
                    <input type="number"
                           class="form-control form-control-sm trainer-salary-input text-end"
                           data-field="base_premium"
                           min="0"
                           step="0.01"
                           value="{{ $inputRubles($row['base_premium']) }}">
                    <div class="invalid-feedback d-none" data-error-for="base_premium"></div>
                @else
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['base_premium']) }}</span>
                @endif
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body" colspan="6"></td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                <span class="trainer-salary-readonly trainer-salary-value trainer-salary-value--total trainer-salary-total">{{ $formatMoneyRubles($row['total']) }}</span>
            </td>
            @if($canManage ?? false)
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
        @forelse($groups as $group)
            <tr class="trainer-salary-row trainer-salary-kansas-group"
                data-trainer-id="{{ $trainerId }}"
                data-team-id="{{ (int) $group['team_id'] }}">
                <th scope="row" class="trainer-salary-trainer-name trainer-salary-kansas-group-name">
                    {{ $group['team_title'] }}
                </th>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body"></td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body"></td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @if($canManage ?? false)
                        <input type="number"
                               class="form-control form-control-sm trainer-salary-input text-end"
                               data-field="base_avg_students"
                               min="0"
                               max="999.9"
                               step="0.1"
                               value="{{ $group['base_avg_students'] }}">
                        <div class="invalid-feedback d-none" data-error-for="base_avg_students"></div>
                        <div class="invalid-feedback d-none" data-error-for="team_id"></div>
                    @else
                        <span class="trainer-salary-readonly trainer-salary-count">{{ $group['base_avg_students'] }}</span>
                    @endif
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-readonly trainer-salary-count">{{ $group['fact_avg_students'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-readonly trainer-salary-count">{{ $group['diff_students'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($group['premium']) }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($group['pay_per_training']) }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-readonly trainer-salary-count trainer-salary-trainings-count">{{ (int) $group['trainings_count'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($group['group_total']) }}</span>
                </td>
                @if($canManage ?? false)
                    <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body"></td>
                @endif
            </tr>
        @empty
            <tr class="trainer-salary-row trainer-salary-kansas-group trainer-salary-kansas-group--empty" data-trainer-id="{{ $trainerId }}">
                <td colspan="{{ $colCount }}" class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-muted small">
                    Нет тренировок с визитами «Посетил» в этом месяце
                </td>
            </tr>
        @endforelse
        </tbody>
    @empty
        <tbody>
        <tr>
            <td colspan="{{ $colCount }}" class="trainer-salary-empty-state">
                Нет активных тренеров
            </td>
        </tr>
        </tbody>
    @endforelse
</table>
