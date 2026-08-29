{{-- Значения приходят уже отформатированными сервисом (App\Support\Money::formatRub). --}}
<table class="trainer-salary-table trainer-salary-table--readonly">
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
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr class="trainer-salary-row">
            <th scope="row" class="trainer-salary-trainer-name">
                <div>{{ $row['trainer_name'] }}</div>
                @if(isset($row['version']))
                    <div class="small text-muted">v{{ (int) $row['version'] }}</div>
                @endif
            </th>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['base_salary'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                <span class="trainer-salary-count">{{ (int) ($row['sales_percent'] ?? 0) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['paid_months'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['paid_packages'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['sales_base'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['commission'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['bonuses'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $row['deductions'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body trainer-salary-cell--comment">
                <span class="trainer-salary-readonly">{{ $row['comment'] ?? '—' }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                <span class="trainer-salary-value trainer-salary-value--total">{{ $row['total'] }}</span>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
