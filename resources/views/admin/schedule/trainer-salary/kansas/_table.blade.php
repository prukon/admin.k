@php
    $formatMoneyRubles = static function ($value): string {
        return \App\Support\Money::formatRub(\App\Support\Money::toCentsOrFail($value));
    };
    $colCount = 10;
    $visibleRows = array_values(array_filter(
        $rows ?? [],
        static fn ($row): bool => ($row['groups'] ?? []) !== [],
    ));
@endphp

@if(!($canManage ?? false))
    <div class="trainer-salary-kansas-x mb-3">
        <span class="small text-muted">Базовая надбавка к премии:</span>
        <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($premium_increment ?? '0.00') }}</span>
    </div>
@endif

<table class="trainer-salary-table trainer-salary-table--kansas">
    <thead>
    <tr>
        <th class="trainer-salary-corner" scope="col">
            <span class="trainer-salary-corner-label">Группа</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Оклад за тренировку. Задаётся в типе тренера.">
            <span class="trainer-salary-th-label">Оклад<br>за трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Базовая премия за тренировку. Задаётся в типе тренера.">
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
    </tr>
    </thead>
    @forelse($visibleRows as $row)
        @php
            $trainerId = (int) $row['trainer_profile_id'];
            $snapshot = $row['latest_snapshot'] ?? null;
            $groups = $row['groups'] ?? [];
        @endphp
        <tbody class="trainer-salary-kansas-block">
        <tr class="trainer-salary-row trainer-salary-kansas-head" data-trainer-id="{{ $trainerId }}">
            <td colspan="{{ $colCount }}" class="trainer-salary-kansas-head-cell">
                <div class="trainer-salary-kansas-head-bar">
                    <div class="trainer-salary-kansas-head-name">
                        <div class="trainer-salary-kansas-head-title">
                            {{ $row['trainer_name'] }}
                            @if(!empty($row['trainer_type_name']))
                                <span class="trainer-salary-kansas-head-type">({{ $row['trainer_type_name'] }})</span>
                            @endif
                        </div>
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
                    </div>
                </div>
            </td>
        </tr>
        @foreach($groups as $group)
            <tr class="trainer-salary-row trainer-salary-kansas-group"
                data-trainer-id="{{ $trainerId }}"
                data-team-id="{{ (int) $group['team_id'] }}">
                <th scope="row" class="trainer-salary-trainer-name trainer-salary-kansas-group-name">
                    {{ $group['team_title'] }}
                </th>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end trainer-salary-kansas-type-money">
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['rate_per_training']) }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end trainer-salary-kansas-type-money">
                    <span class="trainer-salary-readonly trainer-salary-value">{{ $formatMoneyRubles($row['base_premium']) }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'int' => $group['base_avg_students_int'] ?? $group['base_avg_students'],
                    ])
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'int' => $group['fact_avg_students_int'] ?? $group['fact_avg_students'],
                    ])
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'int' => $group['diff_students_int'] ?? $group['diff_students'],
                    ])
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
            </tr>
        @endforeach
        <tr class="trainer-salary-row trainer-salary-kansas-foot" data-trainer-id="{{ $trainerId }}">
            <td colspan="{{ $colCount }}" class="trainer-salary-kansas-foot-total">
                <div class="trainer-salary-kansas-foot-bar">
                    @if($canManage ?? false)
                        <button type="button"
                                class="btn btn-sm btn-primary trainer-salary-form-one-btn"
                                data-trainer-id="{{ $trainerId }}"
                                title="Расчет ЗП">Расчет</button>
                    @endif
                    <span class="trainer-salary-kansas-foot-sum">
                        <span class="trainer-salary-kansas-foot-caption">Итого:</span>
                        <span class="trainer-salary-readonly trainer-salary-value trainer-salary-value--total trainer-salary-total">{{ $formatMoneyRubles($row['total']) }}</span>
                    </span>
                </div>
            </td>
        </tr>
        </tbody>
    @empty
        <tbody>
        <tr>
            <td colspan="{{ $colCount }}" class="trainer-salary-empty-state">
                Нет тренеров с тренировками в этом месяце
            </td>
        </tr>
        </tbody>
    @endforelse
</table>
