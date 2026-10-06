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
    @include('partials.ui.user-card-modal', [
        'userCardUrl' => url('/schedule/users'),
        'skipUserCardModalCss' => true,
    ])
@endif

@push('styles')
    @vite(['resources/css/schedule.css'])
    @if(($activeTab ?? 'journal') === 'journal')
        <style>
            .schedule-fullscreen-wrapper .wrap-filter-team {
                width: 320px;
                min-width: 320px;
                max-width: 320px;
                min-height: calc(2.25rem + 2px);
            }
            .schedule-fullscreen-wrapper .wrap-filter-team:not(:has(.select2-container)) {
                background: #f8fafc;
                border: 1px solid #ced4da;
                border-radius: var(--bs-border-radius, 0.375rem);
            }
            .schedule-fullscreen-wrapper .wrap-filter-team > select.schedule-filter-team:not(.select2-hidden-accessible) {
                height: calc(2.25rem + 2px) !important;
                min-height: calc(2.25rem + 2px) !important;
                max-height: calc(2.25rem + 2px) !important;
                overflow: hidden !important;
                opacity: 0;
                pointer-events: none;
            }
            .schedule-fullscreen-wrapper .wrap-filter-team .select2-container {
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100%;
            }
            .schedule-fullscreen-wrapper .wrap-filter-team.generic-multiselect-field .select2-container--bootstrap-5 .select2-selection.select2-selection--multiple {
                height: auto !important;
                min-height: calc(2.25rem + 2px) !important;
                overflow: visible !important;
            }
            .schedule-fullscreen-wrapper .wrap-filter-team .select2-container .select2-selection__rendered {
                flex-wrap: wrap !important;
                overflow: visible !important;
            }
            .schedule-fullscreen-wrapper .wrap-filter-team .select2-selection__choice {
                max-width: 100%;
                white-space: normal !important;
            }
            @media only screen and (max-width: 768px) {
                .schedule-fullscreen-wrapper .wrap-filter-team {
                    min-width: 160px;
                    width: 100%;
                    max-width: 100%;
                }
            }
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
            /* Vite-бандл ещё ставит .wrapper { max-width: 100% } на /schedule — вернуть 1280px. */
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .wrapper {
                max-width: 1280px;
                overflow-x: visible;
            }
            body.layout-wide:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .wrapper {
                max-width: none;
                width: 100%;
            }
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .content-wrapper,
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .content,
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .container-fluid,
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .schedule-section,
            body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .tab-content,
            .schedule-fullscreen-wrapper:not(.fullscreen) {
                max-width: none;
                overflow-x: visible;
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

            @media (max-width: 768px) {
                .schedule-fullscreen-wrapper:not(.fullscreen) .schedule-journal-table-stack {
                    display: block;
                    width: 100% !important;
                    max-width: 100%;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) .schedule-table-container {
                    overflow: auto !important;
                    max-height: calc(100dvh - 24rem);
                    -webkit-overflow-scrolling: touch;
                }

                #schedule-table_wrapper > .kids-dt-scroll-x {
                    overflow: visible !important;
                    width: max-content;
                    max-width: none;
                }

                #schedule-table {
                    --schedule-sticky-name-left: 2.5rem;
                }

                #schedule-table .sticky-col-1,
                #schedule-table .col-number {
                    position: sticky;
                    left: 0;
                    z-index: 5;
                    width: 2.5rem !important;
                    min-width: 2.5rem !important;
                    max-width: 2.5rem !important;
                    background-color: #fff;
                    background-clip: padding-box;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) #schedule-table thead th {
                    position: sticky;
                    top: 0;
                    z-index: 4;
                    background-color: #fff;
                    background-clip: padding-box;
                }

                #schedule-table thead th.col-name,
                #schedule-table td.schedule-user-name,
                #schedule-table tfoot td.schedule-attendance-total-label {
                    position: sticky !important;
                    left: var(--schedule-sticky-name-left) !important;
                    z-index: 5;
                    background-clip: padding-box;
                    box-shadow: 4px 0 6px -4px rgba(0, 0, 0, 0.45);
                }

                #schedule-table thead th.sticky-col-1,
                #schedule-table thead th.col-name {
                    z-index: 6;
                    top: 0;
                }

                #schedule-table td.schedule-user-name {
                    background-color: #fff;
                }

                #schedule-table tr.schedule-group-row td.sticky-col-1,
                #schedule-table tr.schedule-group-row td.schedule-user-name {
                    background-color: #f4f6f9;
                }

                #schedule-table tfoot td.sticky-col-1,
                #schedule-table tfoot td.schedule-attendance-total-label {
                    background-color: #f8f9fa;
                }
            }

            .schedule-group-day-check {
                border-color: #0d6efd;
                background: #e7f1ff;
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
        @vite(['resources/js/schedule.js'])
    @elseif(($activeTab ?? '') === 'trainer-workload')
        @vite(['resources/js/trainer-workload.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary')
        @vite(['resources/js/trainer-salary.js'])
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
