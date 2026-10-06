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
            .schedule-search-submit__icon {
                display: none;
            }
            @media only screen and (max-width: 768px) {
                .schedule-fullscreen-wrapper .wrap-filter-team {
                    flex: 1 1 auto;
                    min-width: 0;
                    width: auto;
                    max-width: none;
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
            /* Overlay журнала: ячейки дней на всю ширину, ФИО не резинится.
               body.layout-wide дни не растягивает — см. блок ниже. */
            .schedule-fullscreen-wrapper.fullscreen .schedule-journal-table-stack {
                width: 100% !important;
                max-width: 100%;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table_wrapper.dataTables_wrapper,
            .schedule-fullscreen-wrapper.fullscreen .schedule-table-container .dataTables_wrapper {
                width: 100% !important;
                min-width: 100% !important;
                margin: 0 !important;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table,
            .schedule-fullscreen-wrapper.fullscreen #schedule-table.table,
            .schedule-fullscreen-wrapper.fullscreen table.dataTable#schedule-table {
                width: 100% !important;
                margin: 0 !important;
            }
            .schedule-fullscreen-wrapper.fullscreen .schedule-day-header {
                width: auto !important;
            }
            .schedule-fullscreen-wrapper.fullscreen th.col-name,
            .schedule-fullscreen-wrapper.fullscreen td.schedule-user-name {
                width: 1% !important;
                max-width: 140px !important;
                white-space: nowrap;
            }
            .schedule-fullscreen-wrapper.fullscreen td.schedule-cell {
                width: auto !important;
            }
            /* Широкий кабинет: колонки как в обычной ширине (36px), таблица по содержимому.
               !important бьёт width: auto из прежнего бандла schedule.css. */
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) .schedule-day-header,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) #schedule-table td.schedule-group-day,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) #schedule-table tfoot td.schedule-attendance-day,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) td.schedule-cell {
                width: 36px !important;
                min-width: 36px !important;
                max-width: 36px !important;
            }
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) #schedule-table_wrapper.dataTables_wrapper,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) .schedule-table-container .dataTables_wrapper,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) #schedule-table,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) table.dataTable#schedule-table {
                width: max-content !important;
                min-width: 0 !important;
            }
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) th.col-name,
            body.layout-wide:not(:has(.schedule-fullscreen-wrapper.fullscreen)) td.schedule-user-name {
                width: auto !important;
                max-width: none !important;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table_wrapper > .kids-dt-scroll-x {
                overflow: visible !important;
                width: 100%;
                max-width: 100%;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table thead th {
                position: sticky;
                top: 0;
                z-index: 55;
                background-color: #fff;
                background-clip: padding-box;
            }
            .schedule-fullscreen-wrapper.fullscreen #schedule-table thead th.sticky-col-1,
            .schedule-fullscreen-wrapper.fullscreen #schedule-table thead th.col-name {
                z-index: 70 !important;
                top: 0;
                background-color: #fff;
            }

            @media (max-width: 768px) {
                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) {
                    overflow: hidden;
                    height: 100dvh;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .wrapper {
                    display: flex;
                    flex-direction: column;
                    height: 100dvh;
                    max-height: 100dvh;
                    min-height: 0;
                    overflow: hidden;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .main-header {
                    flex: 0 0 auto;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .main-footer {
                    display: none;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .content-wrapper {
                    flex: 1 1 auto;
                    display: flex;
                    flex-direction: column;
                    min-height: 0;
                    height: auto;
                    margin-bottom: 0;
                    overflow: hidden;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .content,
                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .container-fluid,
                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .schedule-section,
                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .tab-content,
                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .schedule-fullscreen-wrapper {
                    flex: 1 1 auto;
                    display: flex;
                    flex-direction: column;
                    min-height: 0;
                    overflow: hidden;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .schedule-section > .nav {
                    flex: 0 0 auto;
                }

                body:has(.schedule-fullscreen-wrapper:not(.fullscreen)) .schedule-section,
                .schedule-fullscreen-wrapper:not(.fullscreen) {
                    margin-top: 0 !important;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) .schedule-controls {
                    flex: 0 0 auto;
                }

                .schedule-fullscreen-wrapper .schedule-controls {
                    row-gap: 4px;
                }

                .schedule-fullscreen-wrapper .schedule-controls .form-select,
                .schedule-fullscreen-wrapper .schedule-controls .form-control,
                .schedule-fullscreen-wrapper .schedule-controls .btn {
                    height: 32px;
                    min-height: 32px;
                    padding-top: 0;
                    padding-bottom: 0;
                }

                .schedule-attendance-average {
                    display: none !important;
                }

                .schedule-search-submit__label {
                    display: none;
                }

                .schedule-search-submit__icon {
                    display: inline-block;
                }

                .schedule-fullscreen-wrapper .schedule-controls__filters {
                    display: flex;
                    flex-wrap: nowrap;
                    align-items: center;
                    width: 100%;
                    min-width: 0;
                    gap: 4px;
                }

                .schedule-fullscreen-wrapper .schedule-controls .wrap-filter-year,
                .schedule-fullscreen-wrapper .schedule-controls .wrap-filter-month {
                    flex: 0 0 auto;
                    width: auto;
                    max-width: none;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team {
                    flex: 1 1 auto;
                    width: auto !important;
                    min-width: 0 !important;
                    max-width: none !important;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team .select2-container .select2-selection__rendered {
                    flex-wrap: nowrap !important;
                    overflow: hidden !important;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team .select2-selection__choice,
                .schedule-fullscreen-wrapper .wrap-filter-team .select2-selection__placeholder {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap !important;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team {
                    min-height: 32px;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team > select.schedule-filter-team:not(.select2-hidden-accessible) {
                    height: 32px !important;
                    min-height: 32px !important;
                    max-height: 32px !important;
                }

                .schedule-fullscreen-wrapper .wrap-filter-team.generic-multiselect-field .select2-container--bootstrap-5 .select2-selection.select2-selection--multiple {
                    height: 32px !important;
                    min-height: 32px !important;
                    max-height: 32px !important;
                    padding: 0 0.35rem !important;
                    font-size: 12px !important;
                    line-height: 1.2 !important;
                    overflow: hidden !important;
                }

                .schedule-fullscreen-wrapper .schedule-controls__actions {
                    flex: 1 1 100%;
                    flex-wrap: wrap;
                    align-items: center;
                    min-width: 0;
                    gap: 4px;
                }

                .schedule-fullscreen-wrapper .wrap-filter-search {
                    flex: 1 1 0;
                    min-width: 0;
                    width: auto;
                }

                .schedule-fullscreen-wrapper .wrap-filter-fullscreen {
                    flex: 0 0 auto;
                    padding-left: 0;
                    padding-right: 0;
                }

                .schedule-fullscreen-wrapper .schedule-controls .schedule-controls__search {
                    width: auto;
                    flex: 1 1 auto;
                    min-width: 0;
                }

                .schedule-fullscreen-wrapper .schedule-controls .table-search,
                .schedule-fullscreen-wrapper .wrap-filter-search input {
                    width: 1% !important;
                    flex: 1 1 auto;
                    min-width: 0;
                }

                .schedule-fullscreen-wrapper .schedule-controls .schedule-search-submit,
                .schedule-fullscreen-wrapper .schedule-controls .schedule-btn-fullscreen {
                    flex: 0 0 32px;
                    width: 32px;
                    min-width: 32px;
                    height: 32px;
                    min-height: 32px;
                    padding: 0;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) #schedule-journal-stage.is-ready {
                    flex: 1 1 auto;
                    display: flex;
                    flex-direction: column;
                    min-height: 0;
                    height: auto;
                    overflow: hidden;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) .schedule-journal-table-stack {
                    display: flex;
                    flex-direction: column;
                    flex: 1 1 auto;
                    min-height: 0;
                    width: 100% !important;
                    max-width: 100%;
                    overflow: hidden;
                }

                .schedule-fullscreen-wrapper:not(.fullscreen) .schedule-table-container {
                    flex: 1 1 auto;
                    min-height: 0;
                    height: auto;
                    max-height: none;
                    overflow: auto !important;
                    background: #fff;
                    -webkit-overflow-scrolling: touch;
                }

                #schedule-table_wrapper > .kids-dt-scroll-x {
                    overflow: visible !important;
                    width: max-content;
                    max-width: none;
                }

                #schedule-table {
                    --schedule-sticky-name-left: 0;
                }

                #schedule-table tr > .col-number,
                #schedule-table tr > td.sticky-col-1,
                #schedule-table tr > td.number-line {
                    width: 0 !important;
                    min-width: 0 !important;
                    max-width: 0 !important;
                    padding: 0 !important;
                    border-width: 0 !important;
                    border-left-width: 0 !important;
                    font-size: 0 !important;
                    line-height: 0 !important;
                    overflow: hidden !important;
                    color: transparent !important;
                    pointer-events: none;
                }

                #schedule-table tr > th.schedule-consuming-count,
                #schedule-table tr > td.schedule-consuming-count {
                    width: 0 !important;
                    min-width: 0 !important;
                    max-width: 0 !important;
                    padding: 0 !important;
                    border-width: 0 !important;
                    font-size: 0 !important;
                    line-height: 0 !important;
                    overflow: hidden !important;
                    color: transparent !important;
                    pointer-events: none;
                }

                #schedule-table .schedule-consuming-count .journal-col-header-hint,
                #schedule-table .schedule-consuming-count i {
                    display: none !important;
                }

                .journal-flexible-hint--ratio {
                    max-height: 1.2em;
                    overflow: hidden;
                }

                #schedule-table th.col-name,
                #schedule-table td.schedule-user-name,
                #schedule-table tfoot td.schedule-attendance-total-label {
                    width: 108px !important;
                    max-width: 108px !important;
                }

                #schedule-table td.schedule-user-name {
                    overflow: hidden;
                }

                #schedule-table button.schedule-user-card-name {
                    display: block;
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                #schedule-table th.schedule-col-setup,
                #schedule-table td.schedule-col-setup {
                    width: 2.6rem !important;
                    min-width: 2.6rem !important;
                    max-width: 2.6rem !important;
                    padding-left: 2px !important;
                    padding-right: 2px !important;
                    overflow: hidden;
                }

                #schedule-table td.schedule-col-setup .journal-abonement-cell {
                    min-height: 0;
                    max-width: 100%;
                    gap: 0;
                }

                #schedule-table th.col-name,
                #schedule-table td.schedule-user-name,
                #schedule-table tfoot td.schedule-attendance-total-label {
                    border-left-width: 1px !important;
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

                #schedule-table thead th.col-name {
                    z-index: 6;
                    top: 0;
                }

                #schedule-table td.schedule-user-name {
                    background-color: #fff;
                }

                #schedule-table tr.schedule-group-row td.schedule-user-name {
                    background-color: #f4f6f9;
                }

                #schedule-table tfoot td.schedule-attendance-total-label {
                    background-color: #f8f9fa;
                }

                .schedule-fullscreen-wrapper.fullscreen {
                    display: flex;
                    flex-direction: column;
                    height: 100dvh;
                    max-height: 100dvh;
                    margin: 0 !important;
                    padding-top: 0;
                    overflow: hidden;
                }

                .schedule-fullscreen-wrapper.fullscreen .schedule-controls {
                    position: relative;
                    top: auto;
                    left: auto;
                    flex: 0 0 auto;
                    width: 100%;
                }

                .schedule-fullscreen-wrapper.fullscreen .wrap-filter-year {
                    display: block !important;
                }

                .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready {
                    flex: 1 1 auto;
                    height: auto;
                    min-height: 0;
                }

                .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready .schedule-journal-table-stack {
                    flex: 1 1 auto;
                    min-height: 0;
                    overflow: hidden;
                }

                .schedule-fullscreen-wrapper.fullscreen #schedule-journal-stage.is-ready .schedule-table-container {
                    flex: 1 1 auto;
                    min-height: 0;
                    height: auto;
                    max-height: none;
                    overflow: auto;
                }

                .schedule-fullscreen-wrapper.fullscreen #schedule-table thead th {
                    position: sticky;
                    top: 0;
                    z-index: 55;
                    background-color: #fff;
                    background-clip: padding-box;
                }

                .schedule-fullscreen-wrapper.fullscreen #schedule-table thead th.col-name {
                    z-index: 70 !important;
                    top: 0;
                    background-color: #fff;
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
