{{-- Значения приходят уже отформатированными сервисом (App\Support\Money::formatRub / десятые). --}}
<table class="trainer-salary-table trainer-salary-table--readonly trainer-salary-table--kansas">
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
    @foreach($rows as $row)
        <tbody class="trainer-salary-kansas-block">
        <tr class="trainer-salary-row trainer-salary-kansas-head">
            <th scope="row" class="trainer-salary-trainer-name">
                <div>{{ $row['trainer_name'] }}</div>
                @if(isset($row['version']))
                    <div class="small text-muted">v{{ (int) $row['version'] }}</div>
                @endif
                @if(!empty($row['premium_increment']))
                    <div class="small text-muted">X: {{ $row['premium_increment'] }}</div>
                @endif
            </th>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['rate_per_training'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['base_premium'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body" colspan="6"></td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                <span class="trainer-salary-value trainer-salary-value--total">{{ $row['total'] }}</span>
            </td>
        </tr>
        @forelse(($row['groups'] ?? []) as $group)
            <tr class="trainer-salary-row trainer-salary-kansas-group">
                <th scope="row" class="trainer-salary-trainer-name trainer-salary-kansas-group-name">
                    {{ $group['team_title'] }}
                </th>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body"></td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body"></td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-count">{{ $group['base_avg_students'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-count">{{ $group['fact_avg_students'] }}</span>
                </td>
                <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                    <span class="trainer-salary-count">{{ $group['diff_students'] }}</span>
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
        @empty
            <tr class="trainer-salary-row">
                <td colspan="10" class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-muted small">
                    Нет групп в слепке
                </td>
            </tr>
        @endforelse
        </tbody>
    @endforeach
</table>
