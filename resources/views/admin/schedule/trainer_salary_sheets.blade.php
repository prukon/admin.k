@php
    $monthValue = sprintf('%04d-%02d', (int) ($year ?? now()->year), (int) ($month ?? now()->month));
@endphp

<div class="trainer-salary-sheets-report"
     id="trainer-salary-sheets-app"
     data-data-url="{{ route('schedule.trainer-salary-sheets.data') }}">
    <div class="card trainer-salary-surface border-0 shadow-sm">
        <div class="card-body">
            <div class="trainer-salary-header mb-3 mb-md-4">
                <h1 class="h5 mb-1 fw-semibold text-body">Листы ЗП</h1>
                <p class="text-muted small mb-0">
                    Архив сформированных слепков. Редактирование — только на вкладке «ЗП тренеров».
                </p>
            </div>

            <div class="trainer-salary-filters mb-3 mb-md-4 d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small text-muted mb-1" for="trainer-salary-sheets-month">Период</label>
                    <input type="month"
                           class="form-control"
                           id="trainer-salary-sheets-month"
                           value="{{ $monthValue }}">
                </div>
                <div class="form-check form-switch mb-0 pb-1">
                    <input class="form-check-input"
                           type="checkbox"
                           id="trainer-salary-sheets-latest-only"
                           {{ ($latest_only ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="trainer-salary-sheets-latest-only">
                        Только актуальные по тренеру / последний пакет
                    </label>
                </div>
            </div>

            <div class="trainer-salary-table-scroll" id="trainer-salary-sheets-table-host">
                @include('admin.schedule._trainer_salary_sheets_list', ['sheets' => $sheets ?? []])
            </div>

            <div id="trainer-salary-sheets-latest-host">
                @include('admin.schedule._trainer_salary_sheets_latest', [
                    'latestByTrainer' => $latest_by_trainer ?? [],
                ])
            </div>
        </div>
    </div>
</div>
