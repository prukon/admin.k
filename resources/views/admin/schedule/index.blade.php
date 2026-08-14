@extends('layouts.admin2')

@section('content')
    <div class="main-content schedule-section mt-3">
        @include('admin.schedule._schedule_section_tabs', ['activeTab' => $activeTab ?? 'journal'])

        <div class="tab-content">
            @if(($activeTab ?? 'journal') === 'journal')
                @include('admin.schedule.journal')
            @elseif(($activeTab ?? '') === 'occurrence-statuses')
                @include('admin.shared.occurrence_statuses_crud')
            @elseif(($activeTab ?? '') === 'trainer-workload')
                @include('admin.schedule.trainer_workload')
            @elseif(($activeTab ?? '') === 'trainer-salary')
                @include('admin.schedule.trainer_salary', [
                    'year' => $year ?? null,
                    'month' => $month ?? null,
                    'rows' => $rows ?? [],
                    'table_view' => $table_view ?? null,
                    'scheme_code' => $scheme_code ?? null,
                    'draft_subtitle' => $draft_subtitle ?? null,
                    'draft_view_data' => $draft_view_data ?? [],
                    'canManageTrainerSalary' => $canManageTrainerSalary ?? false,
                ])
            @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
                @include('admin.schedule.trainer_salary_sheets', [
                    'year' => $year ?? null,
                    'month' => $month ?? null,
                    'latest_only' => $latest_only ?? false,
                    'sheets' => $sheets ?? [],
                    'latest_by_trainer' => $latest_by_trainer ?? [],
                ])
            @endif
        </div>
    </div>
@endsection

{{-- Как на /admin/districts: include на корне view, чтобы @push styles/scripts попали в layout stacks --}}
@if(($activeTab ?? 'journal') === 'journal')
    @include('partials.select2.generic-multiselect')
@endif

@push('scripts')
    @vite(['resources/css/schedule.css'])
    {{-- Hotfix до следующей Vite-сборки: см. public/css/schedule-journal-cells.css --}}
    <link rel="stylesheet" href="{{ asset('css/schedule-journal-cells.css') }}">
    @if(($activeTab ?? 'journal') === 'journal')
        @include('partials.ui.discount-percent-badge-styles')
        <script>
            window.SCHEDULE_VISITED_STATUS_ID = @json($visitedStatusId ?? null);
        </script>
        @include('partials.ui.discount-percent-js')
        {{-- Hotfix: актуальный schedule.js (мультитренеры) до Vite-сборки; старый build всё ещё на #cell-trainer-profile-id --}}
        <script src="{{ asset('js/schedule-journal.js') }}?v={{ @filemtime(public_path('js/schedule-journal.js')) ?: time() }}"></script>
    @elseif(($activeTab ?? '') === 'trainer-workload')
        @vite(['resources/js/trainer-workload.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary')
        {{-- Hotfix до следующей Vite-сборки: актуальный trainer-salary.js (схема kansas) --}}
        <link rel="stylesheet" href="{{ asset('css/trainer-salary.css') }}?v={{ @filemtime(public_path('css/trainer-salary.css')) ?: time() }}">
        <script src="{{ asset('js/trainer-salary.js') }}?v={{ @filemtime(public_path('js/trainer-salary.js')) ?: time() }}"></script>
    @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
        @vite(['resources/js/trainer-salary-sheets.js'])
    @endif
@endpush
