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

@push('styles')
    @vite(['resources/css/schedule.css'])
    {{-- Hotfix до следующей Vite-сборки: см. public/css/schedule-journal-cells.css --}}
    <link rel="stylesheet" href="{{ asset('css/schedule-journal-cells.css') }}?v={{ @filemtime(public_path('css/schedule-journal-cells.css')) ?: time() }}">
    @if(($activeTab ?? 'journal') === 'journal')
        <style>
            #schedule-journal-stage {
                position: relative;
            }
            #schedule-journal-stage:not(.is-ready) {
                min-height: 12rem;
                height: 12rem;
                overflow: hidden;
            }
            #schedule-journal-stage:not(.is-ready) #schedule-table,
            #schedule-journal-stage:not(.is-ready) .dataTables_wrapper {
                visibility: hidden;
            }
            /* d-flex в Bootstrap — display:flex !important */
            #schedule-journal-stage:not(.is-ready) .schedule-journal-pagination {
                display: none !important;
            }
            .schedule-journal-preloader {
                position: absolute;
                inset: 0;
                z-index: 20;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f4f6f9;
                pointer-events: none;
            }
            #schedule-journal-stage.is-ready .schedule-journal-preloader {
                display: none;
            }
            .schedule-fullscreen-wrapper.fullscreen .schedule-journal-preloader {
                background: #fff;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready {
                display: flex;
                flex-direction: column;
                height: calc(100% - 50px);
                min-height: 0;
                overflow: hidden;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready .schedule-journal-table-stack {
                flex: 1 1 auto;
                min-height: 0;
                display: flex;
                flex-direction: column;
                width: 100%;
                max-width: 100%;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready .schedule-journal-pagination {
                flex: 0 0 auto;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready .schedule-table-container {
                flex: 1 1 auto;
                min-height: 0;
                height: auto;
                overflow: auto;
            }
            /* Не резинить колонки; не центрировать: DataTables table.dataTable { margin: 0 auto },
               style.css .dataTables_wrapper { min-width: 100% }.
               Пейджер по ширине таблицы, не экрана. */
            .schedule-journal-table-stack {
                display: inline-block;
                width: max-content;
                max-width: 100%;
                vertical-align: top;
            }
            .schedule-journal-table-stack .schedule-journal-pagination {
                width: 100%;
                box-sizing: border-box;
            }
            .schedule-table-container {
                text-align: left;
            }
            .schedule-table-container .dataTables_wrapper,
            #schedule-table_wrapper.dataTables_wrapper {
                width: max-content !important;
                min-width: 0 !important;
                margin: 0 !important;
            }
            #schedule-table,
            #schedule-table.table,
            table.dataTable#schedule-table {
                width: max-content !important;
                margin: 0 !important;
            }
            /* Overlay журнала и body.layout-wide: ячейки дней на всю ширину, ФИО не резинится. */
            .schedule-fullscreen-wrapper.fullscreen .schedule-journal-table-stack,
            body.layout-wide .schedule-journal-table-stack {
                width: 100% !important;
                max-width: 100%;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table_wrapper.dataTables_wrapper,
            .schedule-fullscreen-wrapper.fullscreen .schedule-table-container .dataTables_wrapper,
            body.layout-wide #schedule-table_wrapper.dataTables_wrapper,
            body.layout-wide .schedule-table-container .dataTables_wrapper {
                width: 100% !important;
                min-width: 100% !important;
                margin: 0 !important;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table,
            .schedule-fullscreen-wrapper.fullscreen #schedule-table.table,
            .schedule-fullscreen-wrapper.fullscreen table.dataTable#schedule-table,
            body.layout-wide #schedule-table,
            body.layout-wide #schedule-table.table,
            body.layout-wide table.dataTable#schedule-table {
                width: 100% !important;
                margin: 0 !important;
            }
            .schedule-fullscreen-wrapper.fullscreen .schedule-day-header,
            body.layout-wide .schedule-day-header {
                width: auto !important;
            }
            .schedule-fullscreen-wrapper.fullscreen th.col-name,
            .schedule-fullscreen-wrapper.fullscreen td.schedule-user-name,
            body.layout-wide th.col-name,
            body.layout-wide td.schedule-user-name {
                width: 1% !important;
                max-width: 140px !important;
                white-space: nowrap;
            }
            .schedule-fullscreen-wrapper.fullscreen td.schedule-cell,
            body.layout-wide td.schedule-cell {
                width: auto !important;
            }
        </style>
        <noscript>
            <style>
                #schedule-journal-stage:not(.is-ready) {
                    height: auto;
                    min-height: 0;
                    overflow: visible;
                }
                #schedule-journal-stage:not(.is-ready) #schedule-table,
                #schedule-journal-stage:not(.is-ready) .dataTables_wrapper {
                    visibility: visible;
                }
                #schedule-journal-stage:not(.is-ready) .schedule-journal-pagination {
                    display: flex !important;
                }
                .schedule-journal-preloader {
                    display: none !important;
                }
            </style>
        </noscript>
    @endif
@endpush

@push('scripts')
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
        @if($can_manage_trainer_types ?? false)
            @include('admin.trainers._trainer_types_assets')
            <script>
                window.__onTrainerTypesChanged = function (types, reason) {
                    if (reason === 'open') {
                        return;
                    }
                    if (typeof window.__reloadTrainerSalaryReport === 'function') {
                        window.__reloadTrainerSalaryReport();
                    }
                };
            </script>
        @endif
    @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
        @vite(['resources/js/trainer-salary-sheets.js'])
    @endif
@endpush
