@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <div class="d-flex align-items-center justify-content-between pt-3 pb-3">
            <h4 class="text-start mb-0">Расписание школы (слоты)</h4>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th style="width: 60px">#</th>
                            <th style="width: 80px">День</th>
                            <th style="width: 140px">Время</th>
                            <th>Группа</th>
                            @can('locations.view')
                                <th style="width: 220px">Локация</th>
                            @endcan
                            <th style="width: 230px">Период</th>
                            <th style="width: 100px" class="text-center">Активен</th>
                            @can('scheduleSlots.manage')
                                <th style="width: 140px"></th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($slots as $s)
                            <tr>
                                <td>{{ $s->id }}</td>
                                <td>{{ $weekdays[$s->weekday] ?? $s->weekday }}</td>
                                <td>{{ substr((string) $s->time_start, 0, 5) }}–{{ substr((string) $s->time_end, 0, 5) }}</td>
                                <td>{{ $s->team?->title }}</td>
                                @can('locations.view')
                                    <td>{{ $s->location?->name ?? '—' }}</td>
                                @endcan
                                <td>
                                    {{ $s->date_start?->format('d.m.Y') }}
                                    —
                                    {{ ($s->date_end?->format('Y-m-d') === '9999-12-31') ? '…' : $s->date_end?->format('d.m.Y') }}
                                </td>
                                <td class="text-center">{{ $s->is_enabled ? 'Да' : 'Нет' }}</td>
                                @can('scheduleSlots.manage')
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary js-slot-edit"
                                                data-id="{{ $s->id }}">
                                            Редактировать
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $slots->links() }}
                </div>
            </div>
        </div>
    </div>

    @can('scheduleSlots.manage')
        @include('admin.teamScheduleSlots.partials.slotModals')
    @endcan
@endsection

