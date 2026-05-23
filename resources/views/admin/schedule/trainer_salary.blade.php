@php
    $monthValue = sprintf('%04d-%02d', (int) ($year ?? now()->year), (int) ($month ?? now()->month));
@endphp

<div class="trainer-salary-report"
     id="trainer-salary-app"
     data-data-url="{{ route('schedule.trainer-salary.data') }}"
     data-draft-url-template="{{ route('schedule.trainer-salary.draft.update', ['trainerProfile' => '__ID__']) }}"
     data-form-one-url-template="{{ route('schedule.trainer-salary.snapshots.form-one', ['trainerProfile' => '__ID__']) }}"
     data-form-all-url="{{ route('schedule.trainer-salary.snapshots.form-all') }}"
     data-can-manage="{{ ($canManageTrainerSalary ?? false) ? '1' : '0' }}">
    <div class="card trainer-salary-surface border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="trainer-salary-header mb-3 mb-md-4">
                <h1 class="h5 mb-1 fw-semibold text-body trainer-salary-title">ЗП тренеров</h1>
                <p class="text-muted small mb-0 trainer-salary-subtitle">
                    Черновик за календарный месяц. Кол-во тренировок — как в отчёте «Нагрузка тренеров» (итог по строке).
                </p>
            </div>

            <div class="trainer-salary-filters mb-3 mb-md-4 d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small text-muted mb-1" for="trainer-salary-month">Период</label>
                    <input type="month"
                           class="form-control"
                           id="trainer-salary-month"
                           value="{{ $monthValue }}">
                    <div class="invalid-feedback d-none" id="trainer-salary-error-month"></div>
                </div>
                @if($canManageTrainerSalary ?? false)
                    <div class="ms-md-auto">
                        <button type="button"
                                class="btn btn-outline-primary"
                                id="trainer-salary-form-all-btn">
                            Сформировать всех
                        </button>
                    </div>
                @endif
            </div>

            <div id="trainer-salary-flash" class="alert d-none mb-3" role="alert"></div>

            <div class="trainer-salary-table-scroll" id="trainer-salary-table-host">
                @include('admin.schedule._trainer_salary_table', [
                    'rows' => $rows ?? [],
                    'canManage' => $canManageTrainerSalary ?? false,
                ])
            </div>
        </div>
    </div>
</div>
