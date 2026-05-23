@php
    $report = $report ?? [];
    $dateFrom = $dateFrom ?? ($report['date_from'] ?? '');
    $dateTo = $dateTo ?? ($report['date_to'] ?? '');
    $weekdays = $report['weekdays'] ?? [];
    $trainers = $report['trainers'] ?? [];
    $cells = $report['cells'] ?? [];
    $rowTotals = $report['row_totals'] ?? [];
    $columnTotals = $report['column_totals'] ?? [];
    $grandTotal = $report['grand_total'] ?? [];
    $showGroups = $showGroups ?? ($report['show_groups'] ?? false);

    $monthNames = [
        '01' => 'Январь',
        '02' => 'Февраль',
        '03' => 'Март',
        '04' => 'Апрель',
        '05' => 'Май',
        '06' => 'Июнь',
        '07' => 'Июль',
        '08' => 'Август',
        '09' => 'Сентябрь',
        '10' => 'Октябрь',
        '11' => 'Ноябрь',
        '12' => 'Декабрь',
    ];
    $monthPresets = [];
    for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
        $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
        $monthPresets[] = [
            'label' => $monthNames[$monthStart->format('m')] ?? $monthStart->format('m'),
            'date_from' => $monthStart->toDateString(),
            'date_to' => $monthStart->copy()->endOfMonth()->toDateString(),
        ];
    }
@endphp

<div class="trainer-workload-report"
     id="trainer-workload-app"
     data-data-url="{{ route('schedule.trainer-workload.data') }}">
    <div class="card trainer-workload-surface border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="trainer-workload-header mb-3 mb-md-4">
                <h1 class="h5 mb-1 fw-semibold text-body trainer-workload-title">Нагрузка тренеров</h1>
                <p class="text-muted small mb-0 trainer-workload-subtitle">
                    Посещения со статусом «Посетил» и назначенным тренером.
                    Число в ячейке — количество дат за период.
                </p>
            </div>

            <div class="trainer-workload-filters mb-3 mb-md-4" id="trainer-workload-filters">
                <div class="row g-2 g-md-3 align-items-end">
                    <div class="col-6 col-sm-auto">
                        <label class="form-label small text-muted mb-1" for="trainer-workload-date-from">С</label>
                        <input type="date"
                               class="form-control form-control-sm"
                               id="trainer-workload-date-from"
                               name="date_from"
                               value="{{ $dateFrom }}">
                        <div class="invalid-feedback d-none" id="trainer-workload-error-date-from"></div>
                    </div>
                    <div class="col-6 col-sm-auto">
                        <label class="form-label small text-muted mb-1" for="trainer-workload-date-to">По</label>
                        <input type="date"
                               class="form-control form-control-sm"
                               id="trainer-workload-date-to"
                               name="date_to"
                               value="{{ $dateTo }}">
                        <div class="invalid-feedback d-none" id="trainer-workload-error-date-to"></div>
                    </div>
                    <div class="col-12 col-sm-auto d-flex align-items-center">
                        <div class="form-check form-switch mb-0 trainer-workload-show-groups-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="trainer-workload-show-groups"
                                   value="1"
                                   @checked($showGroups)>
                            <label class="form-check-label small text-nowrap" for="trainer-workload-show-groups">
                                Показывать группы
                            </label>
                        </div>
                    </div>
                </div>
                <div class="trainer-workload-month-presets" id="trainer-workload-month-presets">
                    <div class="trainer-workload-month-presets-inner">
                        @foreach ($monthPresets as $preset)
                            @if (! $loop->first)
                                <span class="trainer-workload-month-presets-sep" aria-hidden="true">·</span>
                            @endif
                            @php
                                $isActiveMonth = $dateFrom === $preset['date_from'] && $dateTo === $preset['date_to'];
                            @endphp
                            <a href="#"
                               class="trainer-workload-month-link{{ $isActiveMonth ? ' is-active' : '' }}"
                               data-trainer-workload-month
                               data-date-from="{{ $preset['date_from'] }}"
                               data-date-to="{{ $preset['date_to'] }}"
                               @if ($isActiveMonth) aria-current="true" @endif>{{ $preset['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="trainer-workload-table-scroll" id="trainer-workload-table-host">
                @include('admin.schedule._trainer_workload_table', [
                    'weekdays' => $weekdays,
                    'trainers' => $trainers,
                    'cells' => $cells,
                    'rowTotals' => $rowTotals,
                    'columnTotals' => $columnTotals,
                    'grandTotal' => $grandTotal,
                    'showGroups' => $showGroups,
                ])
            </div>
        </div>
    </div>
</div>
