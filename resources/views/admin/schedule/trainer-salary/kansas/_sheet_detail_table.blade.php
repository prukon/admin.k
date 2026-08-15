{{-- Значения приходят уже отформатированными сервисом (App\Support\Money::formatRub / десятые). --}}
@php
    $visibleRows = array_values(array_filter(
        $rows ?? [],
        static fn ($row): bool => ($row['groups'] ?? []) !== [],
    ));
@endphp
<table class="trainer-salary-table trainer-salary-table--readonly trainer-salary-table--kansas">
    <thead>
    <tr>
        <th class="trainer-salary-corner" scope="col">
            <span class="trainer-salary-corner-label">Группа</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col" title="Оклад за тренировку">
            <span class="trainer-salary-th-label">Оклад<br>за трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Базовая<br>премия</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col">
            <span class="trainer-salary-th-label">Баз.<br>среднее</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col">
            <span class="trainer-salary-th-label">Факт<br>среднее</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col">
            <span class="trainer-salary-th-label">Разница</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Премия</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--money" scope="col">
            <span class="trainer-salary-th-label">Итого<br>за трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--count" scope="col">
            <span class="trainer-salary-th-label">Кол-во<br>трен.</span>
        </th>
        <th class="trainer-salary-col trainer-salary-col--total trainer-salary-head--total" scope="col">
            <span class="trainer-salary-th-label">Итого</span>
        </th>
    </tr>
    </thead>
    @forelse($visibleRows as $row)
        <tbody class="trainer-salary-kansas-block">
        <tr class="trainer-salary-row trainer-salary-kansas-head">
            <td colspan="10" class="trainer-salary-kansas-head-cell">
                <div class="trainer-salary-kansas-head-bar">
                    <div class="trainer-salary-kansas-head-name">
                        <div class="trainer-salary-kansas-head-title">
                            {{ $row['trainer_name'] }}
                            @if(!empty($row['trainer_type_name']))
                                <span class="trainer-salary-kansas-head-type">({{ $row['trainer_type_name'] }})</span>
                            @endif
                        </div>
                        @if(isset($row['version']))
                            <div class="small text-muted">v{{ (int) $row['version'] }}</div>
                        @endif
                        @if(!empty($row['premium_increment']))
                            <div class="small text-muted">X: {{ $row['premium_increment'] }}</div>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
        @foreach(($row['groups'] ?? []) as $group)
            <tr class="trainer-salary-row trainer-salary-kansas-group">
                <th scope="row" class="trainer-salary-trainer-name trainer-salary-kansas-group-name">
                    {{ $group['team_title'] }}
                </th>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end trainer-salary-kansas-type-money">
                    <span class="trainer-salary-value">{{ $row['rate_per_training'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end trainer-salary-kansas-type-money">
                    <span class="trainer-salary-value">{{ $row['base_premium'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'full' => $group['base_avg_students'],
                        'int' => $group['base_avg_students_int'] ?? $group['base_avg_students'],
                    ])
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'full' => $group['fact_avg_students'],
                        'int' => $group['fact_avg_students_int'] ?? $group['fact_avg_students'],
                    ])
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
                        'full' => $group['diff_students'],
                        'int' => $group['diff_students_int'] ?? $group['diff_students'],
                    ])
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    <span class="trainer-salary-value">{{ $group['premium'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                    <span class="trainer-salary-value">{{ $group['pay_per_training'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-count">{{ (int) $group['trainings_count'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                    <span class="trainer-salary-value">{{ $group['group_total'] }}</span>
                </td>
            </tr>
        @endforeach
        <tr class="trainer-salary-row trainer-salary-kansas-foot">
            <td colspan="9" class="trainer-salary-kansas-foot-pad"></td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end trainer-salary-kansas-foot-total">
                <span class="trainer-salary-kansas-foot-caption">Итого:</span>
                <span class="trainer-salary-value trainer-salary-value--total">{{ $row['total'] }}</span>
            </td>
        </tr>
        </tbody>
    @empty
        <tbody>
        <tr>
            <td colspan="10" class="trainer-salary-empty-state">
                Нет групп в слепке
            </td>
        </tr>
        </tbody>
    @endforelse
</table>
