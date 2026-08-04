@php
    $packagesHasActiveFilters = $packagesHasActiveFilters ?? false;
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="tab-content">
    <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
        <div class="card-body px-3 py-3">
            <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Абонементы</h1>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                    @can('lessonPackages.view')
                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                data-bs-toggle="modal"
                                data-bs-target="#lessonPackageCreateModal"
                                title="Добавить абонемент">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-plus payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                        </button>
                    @endcan

                    <button type="button"
                            class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#historyModal"
                            title="История изменений">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-clock-rotate-left payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">История</span>
                    </button>

                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#lessonPackagesFiltersCollapse"
                            aria-expanded="{{ $packagesHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="lessonPackagesFiltersCollapse"
                            id="lessonPackagesFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="lessonPackagesColumnsDropdown"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                aria-haspopup="true"
                                title="Какие колонки показывать в таблице">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-table-columns payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Колонки</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end payments-report-toolbar-dropdown-panel payments-report-columns-menu"
                             aria-labelledby="lessonPackagesColumnsDropdown">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="name" id="colLessonPackageName" checked>
                                <label class="form-check-label" for="colLessonPackageName">Название</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="schedule_type_label" id="colLessonPackageType" checked>
                                <label class="form-check-label" for="colLessonPackageType">Тип</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="duration_days" id="colLessonPackageDuration" checked>
                                <label class="form-check-label" for="colLessonPackageDuration">Срок действия (дни)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="lessons_count" id="colLessonPackageLessons" checked>
                                <label class="form-check-label" for="colLessonPackageLessons">Занятий</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="price_label" id="colLessonPackagePrice" checked>
                                <label class="form-check-label" for="colLessonPackagePrice">Стоимость</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="freeze_label" id="colLessonPackageFreeze" checked>
                                <label class="form-check-label" for="colLessonPackageFreeze">Заморозка</label>
                            </div>
                            @can('lessonPackages.view')
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colLessonPackageActions" checked>
                                    <label class="form-check-label" for="colLessonPackageActions">Действия</label>
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse {{ $packagesHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="lessonPackagesFiltersCollapse">
        <form id="lesson-packages-filters" class="border rounded p-2 p-md-3 bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="filter-lesson-package-search">Поиск</label>
                    <input id="filter-lesson-package-search"
                           class="form-control"
                           type="text"
                           placeholder="По названию">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="filter-lesson-package-type">Тип</label>
                    <select id="filter-lesson-package-type" class="form-select">
                        <option value="">Все типы</option>
                        <option value="fixed">Фиксированный</option>
                        <option value="flexible">Гибкий</option>
                        <option value="no_schedule">Разовое занятие</option>
                        @can('lessonPackages.type.postpay')
                            <option value="postpay">Постоплата</option>
                        @endcan
                    </select>
                </div>

                <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                    <button id="filter-lesson-package-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                    <button id="filter-lesson-package-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                </div>
            </div>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="table-responsive">
        <table id="lesson-packages-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
            <thead>
            <tr>
                <th>Название</th>
                <th>Тип</th>
                <th>Срок действия (дни)</th>
                <th>Занятий</th>
                <th>Стоимость</th>
                <th>Заморозка</th>
                @can('lessonPackages.view')
                    <th class="text-start" style="min-width: 220px;">Действия</th>
                @endcan
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@can('lessonPackages.view')
    {{-- Create Modal --}}
    <div class="modal fade" id="lessonPackageCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="lessonPackageCreateForm" novalidate>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Название *</label>
                                <input type="text" name="create[name]" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback d-none" data-error-for="create[name]"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Тип *</label>
                                <select name="create[schedule_type]" id="create_schedule_type" class="form-select" required>
                                    <option value="fixed">Фиксированный</option>
                                    <option value="flexible">Гибкий</option>
                                    <option value="no_schedule">Разовое занятие</option>
                                    @can('lessonPackages.type.postpay')
                                        <option value="postpay">Постоплата</option>
                                    @endcan
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="create[schedule_type]"></div>
                            </div>

                            <div class="col-12 col-md-6" id="create_duration_wrap">
                                <label class="form-label">Срок действия (дни) *</label>
                                <input type="number" name="create[duration_days]" id="create_duration_days" class="form-control" min="1" max="3650" value="30" required>
                                <div class="invalid-feedback d-none" data-error-for="create[duration_days]"></div>
                            </div>
                            <div class="col-12 col-md-6" id="create_lessons_wrap">
                                <label class="form-label">Занятий *</label>
                                <input type="number" name="create[lessons_count]" id="create_lessons_count" class="form-control" min="1" max="1000" value="8" required>
                                <div class="invalid-feedback d-none" data-error-for="create[lessons_count]"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" id="create_price_label">Стоимость (руб.) *</label>
                                <input type="number" name="create[price]" class="form-control" min="0" max="99999999.99" step="0.01" value="0" required>
                                <div class="invalid-feedback d-none" data-error-for="create[price]"></div>
                            </div>

                            <div class="col-12" id="create_freeze_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="create_freeze_enabled" name="create[freeze_enabled]">
                                        <label class="form-check-label" for="create_freeze_enabled">Разрешена заморозка</label>
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="create[freeze_enabled]"></div>

                                    <div id="create_freeze_days_wrap" class="d-none mt-3 pt-3 border-top">
                                        <div class="row g-3">
                                            <div class="col-12 col-sm-4">
                                                <label class="form-label mb-1" for="create_freeze_days">Дней заморозки</label>
                                                <input type="number" name="create[freeze_days]" id="create_freeze_days" class="form-control" min="1" max="3650" value="7">
                                                <div class="invalid-feedback d-none" data-error-for="create[freeze_days]"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="create_auto_attendance_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="create_auto_attendance_enabled" name="create[auto_attendance_enabled]">
                                        <label class="form-check-label" for="create_auto_attendance_enabled">Автосписание</label>
                                    </div>
                                    <div class="form-text mt-2 mb-0">
                                        В конце дня занятия автоматически ставится статус «Посетил» и списывается занятие, если статус не был выставлен вручную.
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="create[auto_attendance_enabled]"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade" id="lessonPackageEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Редактировать абонемент</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form id="lessonPackageEditForm" novalidate>
                        <input type="hidden" id="edit_id" value="">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Название *</label>
                                <input type="text" name="edit[name]" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[name]"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Тип *</label>
                                <select name="edit[schedule_type]" id="edit_schedule_type" class="form-select" required>
                                    <option value="fixed">Фиксированный</option>
                                    <option value="flexible">Гибкий</option>
                                    <option value="no_schedule">Разовое занятие</option>
                                    @can('lessonPackages.type.postpay')
                                        <option value="postpay">Постоплата</option>
                                    @endcan
                                </select>
                                <div class="invalid-feedback d-none" data-error-for="edit[schedule_type]"></div>
                            </div>

                            <div class="col-12 col-md-6" id="edit_duration_wrap">
                                <label class="form-label">Срок действия (дни) *</label>
                                <input type="number" name="edit[duration_days]" id="edit_duration_days" class="form-control" min="1" max="3650" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[duration_days]"></div>
                            </div>

                            <div class="col-12 col-md-6" id="edit_lessons_wrap">
                                <label class="form-label">Занятий *</label>
                                <input type="number" name="edit[lessons_count]" id="edit_lessons_count" class="form-control" min="1" max="1000" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[lessons_count]"></div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" id="edit_price_label">Стоимость (руб.) *</label>
                                <input type="number" name="edit[price]" class="form-control" min="0" max="99999999.99" step="0.01" required>
                                <div class="invalid-feedback d-none" data-error-for="edit[price]"></div>
                            </div>

                            <div class="col-12" id="edit_freeze_section">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="edit_freeze_enabled" name="edit[freeze_enabled]">
                                    <label class="form-check-label" for="edit_freeze_enabled">Разрешена заморозка</label>
                                </div>
                                <div class="invalid-feedback d-none" data-error-for="edit[freeze_enabled]"></div>
                            </div>

                            <div class="col-12 col-md-4" id="edit_freeze_days_wrap">
                                <label class="form-label">Дней заморозки</label>
                                <input type="number" name="edit[freeze_days]" class="form-control" min="1" max="3650">
                                <div class="invalid-feedback d-none" data-error-for="edit[freeze_days]"></div>
                            </div>

                            <div class="col-12" id="edit_auto_attendance_section">
                                <div class="rounded border bg-light p-3">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="edit_auto_attendance_enabled" name="edit[auto_attendance_enabled]">
                                        <label class="form-check-label" for="edit_auto_attendance_enabled">Автосписание</label>
                                    </div>
                                    <div class="form-text mt-2 mb-0">
                                        В конце дня занятия автоматически ставится статус «Посетил» и списывается занятие, если статус не был выставлен вручную.
                                    </div>
                                    <div class="invalid-feedback d-none" data-error-for="edit[auto_attendance_enabled]"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete confirm --}}
    <div class="modal fade" id="lessonPackageDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Удаление абонемента</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">
                        Удалить абонемент «<span id="lessonPackageDeleteName"></span>»? Это действие нельзя отменить.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-danger" id="lessonPackageDeleteConfirmBtn">Удалить</button>
                </div>
            </div>
        </div>
    </div>
@endcan

@include('includes.logModal')

@can('lessonPackages.view')
@push('scripts')
    <script>
        $(document).ready(function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const canManagePackages = @json(auth()->user()->can('lessonPackages.view'));

                function clearErrors(modalEl) {
                    modalEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    modalEl.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; el.classList.add('d-none'); });
                }

                function setFieldError(modalEl, name, message) {
                    const field = modalEl.querySelector('[name="' + CSS.escape(name) + '"]');
                    if (field) {
                        field.classList.add('is-invalid');
                    }

                    const err = modalEl.querySelector('[data-error-for="' + name + '"]');
                    if (err) {
                        err.textContent = message;
                        err.classList.remove('d-none');
                    }
                }

                function normalizePayload(formData, prefix) {
                    const scheduleType = (formData.get(prefix + '[schedule_type]') || '').toString();
                    return {
                        name: (formData.get(prefix + '[name]') || '').toString(),
                        schedule_type: scheduleType,
                        duration_days: (formData.get(prefix + '[duration_days]') || '').toString(),
                        lessons_count: (formData.get(prefix + '[lessons_count]') || '').toString(),
                        price: (formData.get(prefix + '[price]') || '').toString(),
                        freeze_enabled: formData.get(prefix + '[freeze_enabled]') ? 1 : 0,
                        freeze_days: (formData.get(prefix + '[freeze_days]') || '').toString(),
                        auto_attendance_enabled: formData.get(prefix + '[auto_attendance_enabled]') ? 1 : 0,
                        time_slots: [],
                    };
                }

                async function requestJson(method, url, data) {
                    const res = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: data ? JSON.stringify(data) : undefined
                    });

                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = new Error(json.message || 'Ошибка запроса');
                        err.payload = json;
                        err.status = res.status;
                        throw err;
                    }
                    return json;
                }

                function applyValidationErrors(modalEl, errors, prefix) {
                    Object.keys(errors || {}).forEach(function (k) {
                        const msg = (errors[k] && errors[k][0]) ? errors[k][0] : 'Ошибка';
                        const inputName = prefix + '[' + k + ']';
                        setFieldError(modalEl, inputName, msg);
                    });
                }

                function packagesFilterParams() {
                    return {
                        name: $('#filter-lesson-package-search').val() || '',
                        schedule_type: $('#filter-lesson-package-type').val() || '',
                    };
                }

                function packagesHasNonDefaultFilters() {
                    const params = packagesFilterParams();
                    return params.name !== '' || params.schedule_type !== '';
                }

                function syncPackagesFiltersCollapseState() {
                    const hasActive = packagesHasNonDefaultFilters();
                    const collapseEl = document.getElementById('lessonPackagesFiltersCollapse');
                    const $toggle = $('#lessonPackagesFiltersToggle');

                    if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                    }

                    if ($toggle.length && collapseEl) {
                        $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                    }
                }

                const dtApi = KidsCrmDataTable.create('#lesson-packages-table', {
                    columnsSettings: {
                        defaults: {
                            name: true,
                            schedule_type_label: true,
                            duration_days: true,
                            lessons_count: true,
                            price_label: true,
                            freeze_label: true,
                            ...(canManagePackages ? { actions: true } : {}),
                        },
                        urls: {
                            get: @json(route('admin.lesson-packages.columns-settings.get')),
                            save: @json(route('admin.lesson-packages.columns-settings.save')),
                        },
                        csrfToken: csrfToken,
                    },
                    dataTable: {
                        ajax: {
                            url: @json(route('admin.lesson-packages.data')),
                            type: 'GET',
                            data: function (d) {
                                const params = packagesFilterParams();
                                d.name = params.name;
                                d.schedule_type = params.schedule_type;
                            }
                        },
                        order: [[0, 'asc']],
                        language: @include('partials.datatables.ru')
                    },
                    columns: [
                        {
                            key: 'name',
                            type: 'text',
                            data: 'name',
                            name: 'name',
                            className: 'dt-col-text',
                        },
                        {
                            key: 'schedule_type_label',
                            type: 'text',
                            data: 'schedule_type_label',
                            name: 'schedule_type_label',
                        },
                        {
                            key: 'duration_days',
                            type: 'count',
                            data: 'duration_days',
                            name: 'duration_days',
                            className: 'text-center',
                        },
                        {
                            key: 'lessons_count',
                            type: 'count',
                            data: 'lessons_count',
                            name: 'lessons_count',
                            className: 'text-center',
                        },
                        {
                            key: 'price_label',
                            type: 'text',
                            data: 'price_label',
                            name: 'price_label',
                            className: 'text-end text-nowrap',
                        },
                        {
                            key: 'freeze_label',
                            type: 'text',
                            data: 'freeze_label',
                            name: 'freeze_label',
                            className: 'text-center',
                        },
                        {
                            key: 'actions',
                            type: 'actions',
                            when: canManagePackages,
                            orderable: false,
                            searchable: false,
                            className: 'text-start',
                            render: function (data, type, row) {
                                let html = '<div class="d-flex flex-wrap gap-1 justify-content-start">' +
                                    '<button type="button" class="btn btn-sm btn-outline-primary lesson-package-edit-btn" ' +
                                    'data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#lessonPackageEditModal">' +
                                    'Изменить</button>';
                                if (row.can_delete) {
                                    html += '<button type="button" class="btn btn-sm btn-outline-danger lesson-package-delete-btn" ' +
                                        'data-id="' + row.id + '" data-name="' + $('<div>').text(row.name || '').html() + '" ' +
                                        'data-bs-toggle="modal" data-bs-target="#lessonPackageDeleteModal">Удалить</button>';
                                }
                                html += '</div>';
                                return html;
                            }
                        },
                    ],
                });

                function reloadPackagesTable() {
                    dtApi.reload({ keepPage: true });
                    syncPackagesFiltersCollapseState();
                }

                $('#filter-lesson-package-apply').on('click', function () {
                    reloadPackagesTable();
                });

                $('#lesson-packages-filters').on('submit', function (e) {
                    e.preventDefault();
                    reloadPackagesTable();
                });

                $('#filter-lesson-package-reset').on('click', function () {
                    $('#filter-lesson-package-search').val('');
                    $('#filter-lesson-package-type').val('');
                    reloadPackagesTable();
                });

                const createModalEl = document.getElementById('lessonPackageCreateModal');
                const createFormEl = document.getElementById('lessonPackageCreateForm');
                const createFreezeEnabled = document.getElementById('create_freeze_enabled');
                const createFreezeDaysWrap = document.getElementById('create_freeze_days_wrap');
                const createScheduleType = document.getElementById('create_schedule_type');
                const createFreezeSection = document.getElementById('create_freeze_section');
                const createAutoAttendanceSection = document.getElementById('create_auto_attendance_section');
                const createAutoAttendanceEnabled = document.getElementById('create_auto_attendance_enabled');
                const createDuration = document.getElementById('create_duration_days');
                const createLessons = document.getElementById('create_lessons_count');
                const createDurationWrap = document.getElementById('create_duration_wrap');
                const createLessonsWrap = document.getElementById('create_lessons_wrap');
                const createPriceLabel = document.getElementById('create_price_label');
                let createSnapshotBeforeSingle = null;

                function createToggleFreezeDays() {
                    if (!createFreezeDaysWrap || !createFreezeEnabled) {
                        return;
                    }
                    createFreezeDaysWrap.classList.toggle('d-none', !createFreezeEnabled.checked);
                }

                function applyCreateScheduleTypeUi() {
                    if (!createScheduleType) {
                        return;
                    }
                    const t = createScheduleType.value;
                    const isSingle = t === 'no_schedule';
                    const isPostpay = t === 'postpay';
                    if (isSingle || isPostpay) {
                        if (isSingle) {
                            createSnapshotBeforeSingle = {
                                duration: (createDuration && createDuration.value) ? createDuration.value : '30',
                                lessons: (createLessons && createLessons.value) ? createLessons.value : '8',
                            };
                            if (createDuration) {
                                createDuration.value = '1';
                                createDuration.readOnly = true;
                            }
                            if (createLessons) {
                                createLessons.value = '1';
                                createLessons.readOnly = true;
                            }
                            if (createDurationWrap) {
                                createDurationWrap.style.display = '';
                            }
                            if (createLessonsWrap) {
                                createLessonsWrap.style.display = '';
                            }
                        } else {
                            if (createDuration) {
                                createDuration.value = '31';
                                createDuration.readOnly = true;
                            }
                            if (createLessons) {
                                createLessons.value = '1';
                                createLessons.readOnly = true;
                            }
                            if (createDurationWrap) {
                                createDurationWrap.style.display = 'none';
                            }
                            if (createLessonsWrap) {
                                createLessonsWrap.style.display = 'none';
                            }
                        }
                        if (createPriceLabel) {
                            createPriceLabel.textContent = isPostpay ? 'Стоимость за одно занятие (руб.) *' : 'Стоимость (руб.) *';
                        }
                        if (createFreezeSection) {
                            createFreezeSection.style.display = 'none';
                        }
                        if (createAutoAttendanceSection) {
                            createAutoAttendanceSection.style.display = 'none';
                        }
                        if (createFreezeEnabled) {
                            createFreezeEnabled.checked = false;
                        }
                        if (createAutoAttendanceEnabled) {
                            createAutoAttendanceEnabled.checked = false;
                        }
                        createToggleFreezeDays();
                    } else {
                        if (createSnapshotBeforeSingle) {
                            if (createDuration) {
                                createDuration.value = createSnapshotBeforeSingle.duration;
                            }
                            if (createLessons) {
                                createLessons.value = createSnapshotBeforeSingle.lessons;
                            }
                            createSnapshotBeforeSingle = null;
                        }
                        if (createDuration) {
                            createDuration.readOnly = false;
                        }
                        if (createLessons) {
                            createLessons.readOnly = false;
                        }
                        if (createDurationWrap) {
                            createDurationWrap.style.display = '';
                        }
                        if (createLessonsWrap) {
                            createLessonsWrap.style.display = '';
                        }
                        if (createPriceLabel) {
                            createPriceLabel.textContent = 'Стоимость (руб.) *';
                        }
                        if (createFreezeSection) {
                            createFreezeSection.style.display = '';
                        }
                        if (createAutoAttendanceSection) {
                            createAutoAttendanceSection.style.display = '';
                        }
                        createToggleFreezeDays();
                    }
                }

                createFreezeEnabled?.addEventListener('change', createToggleFreezeDays);
                createScheduleType?.addEventListener('change', applyCreateScheduleTypeUi);

                createFormEl?.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    clearErrors(createModalEl);

                    const fd = new FormData(createFormEl);
                    const payload = normalizePayload(fd, 'create');

                    try {
                        await requestJson('POST', @json(route('admin.lesson-packages.store')), payload);
                        bootstrap.Modal.getInstance(createModalEl)?.hide();
                        reloadPackagesTable();
                    } catch (err) {
                        const p = err.payload || {};
                        if (p.errors) {
                            applyValidationErrors(createModalEl, p.errors, 'create');
                        }
                    }
                });

                createModalEl?.addEventListener('shown.bs.modal', function () {
                    createSnapshotBeforeSingle = null;
                    if (createFormEl) {
                        createFormEl.reset();
                    }
                    if (createScheduleType) {
                        createScheduleType.value = 'fixed';
                    }
                    if (createDuration) {
                        createDuration.value = '30';
                        createDuration.readOnly = false;
                    }
                    if (createLessons) {
                        createLessons.value = '8';
                        createLessons.readOnly = false;
                    }
                    applyCreateScheduleTypeUi();
                });

                const editModalEl = document.getElementById('lessonPackageEditModal');
                const editFormEl = document.getElementById('lessonPackageEditForm');
                const editFreezeEnabled = document.getElementById('edit_freeze_enabled');
                const editFreezeDaysWrap = document.getElementById('edit_freeze_days_wrap');
                const editScheduleType = document.getElementById('edit_schedule_type');
                const editFreezeSection = document.getElementById('edit_freeze_section');
                const editAutoAttendanceSection = document.getElementById('edit_auto_attendance_section');
                const editAutoAttendanceEnabled = document.getElementById('edit_auto_attendance_enabled');
                const editDuration = document.getElementById('edit_duration_days');
                const editLessons = document.getElementById('edit_lessons_count');
                const editDurationWrap = document.getElementById('edit_duration_wrap');
                const editLessonsWrap = document.getElementById('edit_lessons_wrap');
                const editPriceLabel = document.getElementById('edit_price_label');
                const editIdEl = document.getElementById('edit_id');
                let editSnapshotBeforeSingle = null;

                function editToggleFreezeDays() {
                    if (!editFreezeDaysWrap || !editFreezeEnabled) {
                        return;
                    }
                    editFreezeDaysWrap.style.display = editFreezeEnabled.checked ? '' : 'none';
                }

                function applyEditScheduleTypeUi() {
                    if (!editScheduleType) {
                        return;
                    }
                    const t = editScheduleType.value;
                    const isSingle = t === 'no_schedule';
                    const isPostpay = t === 'postpay';
                    if (isSingle || isPostpay) {
                        if (isSingle) {
                            editSnapshotBeforeSingle = {
                                duration: (editDuration && editDuration.value) ? editDuration.value : '30',
                                lessons: (editLessons && editLessons.value) ? editLessons.value : '8',
                            };
                            if (editDuration) {
                                editDuration.value = '1';
                                editDuration.readOnly = true;
                            }
                            if (editLessons) {
                                editLessons.value = '1';
                                editLessons.readOnly = true;
                            }
                            if (editDurationWrap) {
                                editDurationWrap.style.display = '';
                            }
                            if (editLessonsWrap) {
                                editLessonsWrap.style.display = '';
                            }
                        } else {
                            if (editDuration) {
                                editDuration.value = '31';
                                editDuration.readOnly = true;
                            }
                            if (editLessons) {
                                editLessons.value = '1';
                                editLessons.readOnly = true;
                            }
                            if (editDurationWrap) {
                                editDurationWrap.style.display = 'none';
                            }
                            if (editLessonsWrap) {
                                editLessonsWrap.style.display = 'none';
                            }
                        }
                        if (editPriceLabel) {
                            editPriceLabel.textContent = isPostpay ? 'Стоимость за одно занятие (руб.) *' : 'Стоимость (руб.) *';
                        }
                        if (editFreezeSection) {
                            editFreezeSection.style.display = 'none';
                        }
                        if (editAutoAttendanceSection) {
                            editAutoAttendanceSection.style.display = 'none';
                        }
                        if (editFreezeEnabled) {
                            editFreezeEnabled.checked = false;
                        }
                        if (editAutoAttendanceEnabled) {
                            editAutoAttendanceEnabled.checked = false;
                        }
                        editToggleFreezeDays();
                    } else {
                        if (editSnapshotBeforeSingle) {
                            if (editDuration) {
                                editDuration.value = editSnapshotBeforeSingle.duration;
                            }
                            if (editLessons) {
                                editLessons.value = editSnapshotBeforeSingle.lessons;
                            }
                            editSnapshotBeforeSingle = null;
                        } else {
                            if (editDuration) {
                                editDuration.value = '30';
                            }
                            if (editLessons) {
                                editLessons.value = '8';
                            }
                        }
                        if (editDuration) {
                            editDuration.readOnly = false;
                        }
                        if (editLessons) {
                            editLessons.readOnly = false;
                        }
                        if (editDurationWrap) {
                            editDurationWrap.style.display = '';
                        }
                        if (editLessonsWrap) {
                            editLessonsWrap.style.display = '';
                        }
                        if (editPriceLabel) {
                            editPriceLabel.textContent = 'Стоимость (руб.) *';
                        }
                        if (editFreezeSection) {
                            editFreezeSection.style.display = '';
                        }
                        if (editAutoAttendanceSection) {
                            editAutoAttendanceSection.style.display = '';
                        }
                        editToggleFreezeDays();
                    }
                }

                editFreezeEnabled?.addEventListener('change', editToggleFreezeDays);
                editScheduleType?.addEventListener('change', applyEditScheduleTypeUi);

                editFormEl?.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    clearErrors(editModalEl);

                    const id = editIdEl.value;
                    if (!id) return;

                    const fd = new FormData(editFormEl);
                    const payload = normalizePayload(fd, 'edit');

                    try {
                        await requestJson('PUT', '/admin/lesson-packages/' + id, payload);
                        bootstrap.Modal.getInstance(editModalEl)?.hide();
                        reloadPackagesTable();
                    } catch (err) {
                        const p = err.payload || {};
                        if (p.errors) {
                            applyValidationErrors(editModalEl, p.errors, 'edit');
                        }
                    }
                });

                const deleteModalEl = document.getElementById('lessonPackageDeleteModal');
                const deleteNameEl = document.getElementById('lessonPackageDeleteName');
                const deleteConfirmBtn = document.getElementById('lessonPackageDeleteConfirmBtn');
                let deleteTargetId = null;

                $(document).on('click', '.lesson-package-delete-btn', function () {
                    const btn = this;
                    deleteTargetId = btn.getAttribute('data-id');
                    if (deleteNameEl) {
                        deleteNameEl.textContent = btn.getAttribute('data-name') || '';
                    }
                });

                deleteConfirmBtn?.addEventListener('click', async function () {
                    if (!deleteTargetId) {
                        return;
                    }
                    try {
                        await requestJson('DELETE', '/admin/lesson-packages/' + deleteTargetId);
                        bootstrap.Modal.getInstance(deleteModalEl)?.hide();
                        reloadPackagesTable();
                    } catch (err) {
                        const msg = (err.payload && err.payload.message)
                            ? err.payload.message
                            : (err.message || 'Не удалось удалить абонемент.');
                        alert(msg);
                    }
                });

                deleteModalEl?.addEventListener('hidden.bs.modal', function () {
                    deleteTargetId = null;
                });

                $(document).on('click', '.lesson-package-edit-btn', async function () {
                    clearErrors(editModalEl);
                    const id = this.getAttribute('data-id');
                    editIdEl.value = id;

                    try {
                        const json = await requestJson('GET', '/admin/lesson-packages/' + id);
                        const lp = json.lesson_package || {};
                        const scheduleType = lp.schedule_type || 'fixed';
                        const scheduleSelect = editModalEl.querySelector('[name="edit[schedule_type]"]');
                        if (scheduleType === 'postpay' && scheduleSelect && !scheduleSelect.querySelector('option[value="postpay"]')) {
                            const opt = document.createElement('option');
                            opt.value = 'postpay';
                            opt.textContent = 'Постоплата';
                            scheduleSelect.appendChild(opt);
                        }

                        editModalEl.querySelector('[name="edit[name]"]').value = lp.name || '';
                        scheduleSelect.value = scheduleType;
                        editModalEl.querySelector('[name="edit[duration_days]"]').value = lp.duration_days || 30;
                        editModalEl.querySelector('[name="edit[lessons_count]"]').value = lp.lessons_count || 8;
                        editModalEl.querySelector('[name="edit[price]"]').value = (lp.price !== undefined && lp.price !== null) ? lp.price : 0;

                        editFreezeEnabled.checked = !!lp.freeze_enabled;
                        editModalEl.querySelector('[name="edit[freeze_days]"]').value = lp.freeze_days || 7;
                        if (editAutoAttendanceEnabled) {
                            editAutoAttendanceEnabled.checked = !!lp.auto_attendance_enabled;
                        }

                        editSnapshotBeforeSingle = null;
                        applyEditScheduleTypeUi();
                    } catch (err) {
                        // silent
                    }
                });

                editModalEl?.addEventListener('shown.bs.modal', function () {
                    editToggleFreezeDays();
                });

                if (typeof showLogModal === 'function') {
                    showLogModal(@json(route('logs.data.lesson-package')));
                }
        });
    </script>
@endpush
@endcan

