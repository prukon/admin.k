{{--
    Среднее Канзаса: целое без ховера (в расчёт идёт то же число).
    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
        'int' => $group['base_avg_students_int'],
    ])
--}}
@php
    $avgInt = (string) ($int ?? '');
@endphp
<div class="trainer-salary-avg-cell">
    <span class="trainer-salary-readonly trainer-salary-count">{{ $avgInt }}</span>
</div>
