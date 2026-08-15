{{--
    Среднее Канзаса: в ячейке целое, ховер с десятыми на самом числе (БД не округляем).
    @include('admin.schedule.trainer-salary.kansas._avg_cell', [
        'full' => $group['base_avg_students'],
        'int' => $group['base_avg_students_int'],
    ])
--}}
@php
    $avgFull = (string) ($full ?? '');
    $avgInt = (string) ($int ?? '');
@endphp
<div class="trainer-salary-avg-cell">
    @if($avgFull !== '')
        <span class="trainer-salary-readonly trainer-salary-count"
              tabindex="0"
              data-kids-tooltip-hint
              data-bs-toggle="tooltip"
              data-bs-placement="top"
              title="{{ $avgFull }}"
              aria-label="{{ $avgFull }}">{{ $avgInt }}</span>
    @else
        <span class="trainer-salary-readonly trainer-salary-count">{{ $avgInt }}</span>
    @endif
</div>
