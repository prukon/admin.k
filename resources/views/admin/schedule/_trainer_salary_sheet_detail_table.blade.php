@php
    $formatMoney = static function ($value): string {
        return number_format((float) $value, 2, '.', ' ');
    };
@endphp

<table class="trainer-salary-table trainer-salary-table--readonly">
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
                <span class="trainer-salary-value">{{ $formatMoney($row['base_salary']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-center">
                <span class="trainer-salary-count">{{ (int) $row['trainings_count'] }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $formatMoney($row['rate_per_training']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $formatMoney($row['trainings_amount']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $formatMoney($row['bonuses']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body text-end">
                <span class="trainer-salary-value">{{ $formatMoney($row['deductions']) }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--body trainer-salary-cell--comment">
                <span class="trainer-salary-readonly">{{ $row['comment'] ?? '—' }}</span>
            </td>
            <td class="trainer-salary-cell trainer-salary-data trainer-salary-data--total text-end">
                <span class="trainer-salary-value trainer-salary-value--total">{{ $formatMoney($row['total']) }}</span>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
