@php
    $weekdays = $weekdays ?? [];
    $trainers = $trainers ?? [];
    $cells = $cells ?? [];
    $rowTotals = $rowTotals ?? [];
    $columnTotals = $columnTotals ?? [];
    $grandTotal = $grandTotal ?? [];
    $showGroups = $showGroups ?? false;
    $weekendDays = [6, 7];
@endphp
<table class="trainer-workload-table">
    <thead>
    <tr>
        <th class="trainer-workload-corner" scope="col">
            <span class="trainer-workload-corner-label">Тренер</span>
        </th>
        @foreach($weekdays as $weekdayNum => $weekdayLabel)
            <th class="trainer-workload-day-head text-center {{ in_array($weekdayNum, $weekendDays, true) ? 'is-weekend' : '' }}"
                scope="col">
                <span class="trainer-workload-day-label">{{ $weekdayLabel }}</span>
            </th>
        @endforeach
        <th class="trainer-workload-day-head trainer-workload-day-head--total text-center" scope="col">
            <span class="trainer-workload-day-label">Итого</span>
        </th>
    </tr>
    </thead>
    <tbody>
    @forelse($trainers as $trainer)
        @php
            $trainerId = (int) $trainer['id'];
            $trainerCells = $cells[$trainerId] ?? [];
        @endphp
        <tr class="trainer-workload-row">
            <th scope="row" class="trainer-workload-trainer-name">
                {{ $trainer['name'] }}
            </th>
            @foreach($weekdays as $weekdayNum => $weekdayLabel)
                @php
                    $items = $trainerCells[$weekdayNum] ?? [];
                @endphp
                <td class="trainer-workload-data trainer-workload-data--body {{ in_array($weekdayNum, $weekendDays, true) ? 'is-weekend' : '' }} {{ count($items) === 0 ? 'is-empty' : '' }}">
                    @include('admin.schedule._trainer_workload_cell', ['items' => $items, 'showGroups' => $showGroups])
                </td>
            @endforeach
            <td class="trainer-workload-data trainer-workload-data--row-total {{ count($rowTotals[$trainerId] ?? []) === 0 ? 'is-empty' : '' }}">
                @include('admin.schedule._trainer_workload_cell', [
                    'items' => $rowTotals[$trainerId] ?? [],
                    'variant' => 'total',
                    'showGroups' => $showGroups,
                ])
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($weekdays) + 2 }}" class="trainer-workload-empty-state">
                Нет активных тренеров
            </td>
        </tr>
    @endforelse
    @if(count($trainers) > 0)
        <tr class="trainer-workload-row trainer-workload-row--footer">
            <th scope="row" class="trainer-workload-trainer-name trainer-workload-trainer-name--footer">
                Итого
            </th>
            @foreach($weekdays as $weekdayNum => $weekdayLabel)
                @php
                    $footerItems = $columnTotals[$weekdayNum] ?? [];
                @endphp
                <td class="trainer-workload-data trainer-workload-data--col-total {{ in_array($weekdayNum, $weekendDays, true) ? 'is-weekend' : '' }} {{ count($footerItems) === 0 ? 'is-empty' : '' }}">
                    @include('admin.schedule._trainer_workload_cell', [
                        'items' => $footerItems,
                        'variant' => 'total',
                        'showGroups' => $showGroups,
                    ])
                </td>
            @endforeach
            <td class="trainer-workload-data trainer-workload-data--grand-total {{ count($grandTotal) === 0 ? 'is-empty' : '' }}">
                @include('admin.schedule._trainer_workload_cell', [
                    'items' => $grandTotal,
                    'variant' => 'grand',
                    'showGroups' => $showGroups,
                ])
            </td>
        </tr>
    @endif
    </tbody>
</table>
