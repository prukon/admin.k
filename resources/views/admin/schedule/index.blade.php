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

@push('scripts')
    @vite(['resources/css/schedule.css'])
    @if(($activeTab ?? 'journal') === 'journal')
        @include('partials.select2.generic-multiselect')
        <script>
            window.SCHEDULE_VISITED_STATUS_ID = @json($visitedStatusId ?? null);
        </script>
        @vite(['resources/js/schedule.js'])
    @elseif(($activeTab ?? '') === 'trainer-workload')
        @vite(['resources/js/trainer-workload.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary')
        @vite(['resources/js/trainer-salary.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
        @vite(['resources/js/trainer-salary-sheets.js'])
    @endif
@endpush
