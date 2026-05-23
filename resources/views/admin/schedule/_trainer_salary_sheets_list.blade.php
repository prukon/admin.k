@php
    $formatMoney = static function ($value): string {
        return number_format((float) $value, 2, '.', ' ');
    };
@endphp

<table class="trainer-salary-sheets-table">
    <thead>
    <tr>
        <th scope="col">Дата формирования</th>
        <th scope="col">Период</th>
        <th scope="col">Тип</th>
        <th scope="col">Тренер / пакет</th>
        <th class="text-center" scope="col">Тренеров</th>
        <th class="text-end" scope="col">Сумма итого</th>
        <th scope="col">Сформировал</th>
        <th scope="col">Версия</th>
        <th class="text-center" scope="col"></th>
    </tr>
    </thead>
    <tbody>
    @forelse($sheets as $sheet)
        <tr class="trainer-salary-sheets-row {{ !empty($sheet['is_latest_for_trainer']) || !empty($sheet['is_latest_full_batch']) ? 'is-latest' : '' }}">
            <td class="text-nowrap">{{ $sheet['formed_at_display'] ?? '—' }}</td>
            <td>{{ $sheet['month_label'] ?? '' }}</td>
            <td>{{ $sheet['type_label'] ?? '' }}</td>
            <td>
                @if(($sheet['kind'] ?? '') === 'snapshot')
                    {{ $sheet['trainer_name'] ?? '—' }}
                @else
                    <span class="text-muted">Все активные</span>
                @endif
            </td>
            <td class="text-center">{{ (int) ($sheet['trainers_count'] ?? 0) }}</td>
            <td class="text-end text-nowrap fw-semibold">{{ $formatMoney($sheet['grand_total'] ?? 0) }}</td>
            <td>{{ $sheet['formed_by_name'] ?? '—' }}</td>
            <td>
                {{ $sheet['version_label'] ?? '' }}
                @if(!empty($sheet['is_latest_for_trainer']))
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle ms-1">Актуальная</span>
                @elseif(!empty($sheet['is_latest_full_batch']))
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1">Последний пакет</span>
                @endif
            </td>
            <td class="text-center text-nowrap">
                <a href="{{ $sheet['show_url'] ?? '#' }}" class="btn btn-sm btn-outline-primary">Открыть</a>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="9" class="trainer-salary-sheets-empty-state">
                За выбранный месяц слепков ЗП нет. Сформируйте их на вкладке «ЗП тренеров».
            </td>
        </tr>
    @endforelse
    </tbody>
</table>
