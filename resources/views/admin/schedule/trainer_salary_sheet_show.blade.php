@extends('layouts.admin2')

@php
    $formatMoney = static function ($value): string {
        return number_format((float) $value, 2, '.', ' ');
    };
@endphp

@section('content')
    <div class="main-content schedule-section mt-3">
        @include('admin.schedule._schedule_section_tabs', ['activeTab' => $activeTab ?? 'trainer-salary-sheets'])

        <div class="trainer-salary-sheet-show trainer-salary-report">
            <div class="mb-3">
                <a href="{{ $listUrl ?? route('schedule.trainer-salary-sheets') }}" class="btn btn-sm btn-outline-secondary">
                    ← К списку листов
                </a>
            </div>

            <div class="card trainer-salary-surface border-0 shadow-sm">
                <div class="card-body">
                    <div class="trainer-salary-header mb-3 mb-md-4">
                        <h1 class="h5 mb-1 fw-semibold text-body">
                            Лист ЗП · {{ $sheet['month_label'] ?? '' }}
                        </h1>
                        <p class="text-muted small mb-2">
                            {{ $sheet['type_label'] ?? '' }}
                            @if(!empty($sheet['trainer_name']))
                                · {{ $sheet['trainer_name'] }}
                            @endif
                            · {{ $sheet['version_label'] ?? '' }}
                        </p>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li>Сформирован: {{ $sheet['formed_at_display'] ?? '—' }}</li>
                            <li>Кем: {{ $sheet['formed_by_name'] ?? '—' }}</li>
                            <li>Тренеров в листе: {{ (int) ($sheet['trainers_count'] ?? 0) }}</li>
                            <li class="fw-semibold text-body">Сумма итого: {{ $formatMoney($sheet['grand_total'] ?? 0) }}</li>
                        </ul>
                    </div>

                    <div class="trainer-salary-table-scroll">
                        @include('admin.schedule._trainer_salary_sheet_detail_table', [
                            'rows' => $sheet['rows'] ?? [],
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/css/schedule.css'])
@endpush
