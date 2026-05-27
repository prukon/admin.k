@extends('layouts.admin2')

@php
    $canChangeTrainerPassword = auth()->user()->can('users.password.update');
    $trainersHasActiveFilters = false;
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Пользователи</h4>
        <div class="">
            @include('admin.users._users_section_tabs', ['activeTab' => $activeTab ?? 'trainers'])

            <div class="tab-content">
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Тренеры</h1>
                            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#trainerCreateModal">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-user-plus payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                                </button>

                                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#trainersReportFiltersCollapse"
                                        aria-expanded="{{ $trainersHasActiveFilters ? 'true' : 'false' }}"
                                        aria-controls="trainersReportFiltersCollapse"
                                        id="trainersReportFiltersToggle">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="dropdown payments-report-toolbar-dropdown">
                                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                            type="button"
                                            id="trainersColumnsDropdown"
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
                                         aria-labelledby="trainersColumnsDropdown">
                                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="avatar" id="colTrainerAvatar" checked>
                                            <label class="form-check-label" for="colTrainerAvatar">Аватар</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="full_name" id="colTrainerFullName" checked>
                                            <label class="form-check-label" for="colTrainerFullName">ФИО</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="teams_label" id="colTrainerTeams" checked>
                                            <label class="form-check-label" for="colTrainerTeams">Группы</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="email" id="colTrainerEmail" checked>
                                            <label class="form-check-label" for="colTrainerEmail">Email</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="default_base_salary" id="colTrainerBaseSalary" checked>
                                            <label class="form-check-label" for="colTrainerBaseSalary">Оклад</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="default_rate_per_training" id="colTrainerRate" checked>
                                            <label class="form-check-label" for="colTrainerRate">Ставка за тренировку</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="sort_order" id="colTrainerSortOrder" checked>
                                            <label class="form-check-label" for="colTrainerSortOrder">Сортировка</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_enabled" id="colTrainerStatus" checked>
                                            <label class="form-check-label" for="colTrainerStatus">Активен</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colTrainerActions" checked>
                                            <label class="form-check-label" for="colTrainerActions">Действия</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="collapse {{ $trainersHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="trainersReportFiltersCollapse">
                    <form id="trainers-report-filters" class="border rounded p-2 p-md-3 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="filter-name">Имя</label>
                                <input id="filter-name" class="form-control" type="text" placeholder="Поиск по ФИО, email, телефону">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="filter-team">Группа</label>
                                <select id="filter-team" class="form-select">
                                    <option value="">Все группы</option>
                                    @foreach($teamOptions as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label" for="filter-status">Статус</label>
                                <select id="filter-status" class="form-select">
                                    <option value="">Все</option>
                                    <option value="active" selected>Только активные</option>
                                    <option value="inactive">Только неактивные</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                                <button id="filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                                <button id="filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table id="trainers-table" class="table table-striped table-bordered align-middle w-100">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Аватар</th>
                            <th>ФИО</th>
                            <th>Группы</th>
                            <th>Email</th>
                            <th class="text-end">Оклад</th>
                            <th class="text-end">Ставка за тренировку</th>
                            <th class="text-center">Сортировка</th>
                            <th class="text-center">Активен</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    <div class="modal fade" id="trainerCreateModal" tabindex="-1" aria-labelledby="trainerCreateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="trainerCreateModalLabel">Создание тренера</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="trainerCreateForm" class="text-start">
                        @csrf
                        <div class="alert alert-danger d-none js-form-error mb-3" role="alert"></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-name">Имя*</label>
                                    <input type="text" class="form-control" name="name" id="trainer-create-name" />
                                    <div class="invalid-feedback d-block" data-error-for="name"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-lastname">Фамилия*</label>
                                    <input type="text" class="form-control" name="lastname" id="trainer-create-lastname" />
                                    <div class="invalid-feedback d-block" data-error-for="lastname"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-email">Email</label>
                                    <input type="email" class="form-control" name="email" id="trainer-create-email" autocomplete="off" />
                                    <div class="invalid-feedback d-block" data-error-for="email"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-phone">Телефон</label>
                                    <input type="tel" class="form-control" name="phone" id="trainer-create-phone" placeholder="+7 (XXX) XXX-XX-XX" />
                                    <div class="invalid-feedback d-block" data-error-for="phone"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-password">Пароль</label>
                                    <input type="password" class="form-control" name="password" id="trainer-create-password" autocomplete="new-password" placeholder="Пароль" />
                                    <div class="invalid-feedback d-block" data-error-for="password"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-sort-order">Порядок сортировки</label>
                                    <input type="number" class="form-control" name="sort_order" id="trainer-create-sort-order" value="0" min="0" />
                                    <div class="invalid-feedback d-block" data-error-for="sort_order"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-default-base-salary">Оклад по умолчанию</label>
                                    <input type="number" class="form-control" name="default_base_salary" id="trainer-create-default-base-salary" value="0" min="0" step="1" />
                                    <div class="invalid-feedback d-block" data-error-for="default_base_salary"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-create-default-rate">Ставка за тренировку</label>
                                    <input type="number" class="form-control" name="default_rate_per_training" id="trainer-create-default-rate" value="0" min="0" step="1" />
                                    <div class="invalid-feedback d-block" data-error-for="default_rate_per_training"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                @include('admin.trainers._teams_checkboxes', ['teamsFieldIdPrefix' => 'trainer-create'])
                            </div>
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="form-label" for="trainer-create-description">Описание</label>
                                    <textarea class="form-control" name="description" id="trainer-create-description" rows="3"></textarea>
                                    <div class="invalid-feedback d-block" data-error-for="description"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer-modal-user pt-3 mt-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="button" class="btn btn-primary" id="trainerCreateSubmit">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="trainerEditModal" tabindex="-1" aria-labelledby="trainerEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content background-color-grey">
                <div class="modal-header">
                    <h5 class="modal-title" id="trainerEditModalLabel">Редактирование тренера</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="trainerEditForm" class="text-start">
                        @csrf
                        @method('put')
                        <div class="alert alert-danger d-none js-form-error mb-3" role="alert"></div>
                        <input type="hidden" name="id" />

                        <div class="mb-3 d-flex flex-column align-items-center">
                            <div class="avatar_wrapper">
                                <div class="avatar">
                                    <div class="avatar-clip">
                                        <img src="{{ asset('img/default-avatar.png') }}" alt="Avatar">
                                    </div>
                                    <div class="avatar-actions">
                                        <button class="dropdown-item js-open-photo" type="button">
                                            <i class="fa-solid fa-image"></i> Открыть фото
                                        </button>
                                        <button class="dropdown-item js-change-photo" type="button"
                                                data-bs-toggle="modal" data-bs-target="#avatarEditModal">
                                            <i class="fa-solid fa-pen-to-square"></i> Изменить фото
                                        </button>
                                        <button class="dropdown-item text-danger js-delete-photo" type="button">
                                            <i class="fa-solid fa-trash"></i> Удалить фото
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-lastname">Фамилия*</label>
                                    <input type="text" class="form-control" name="lastname" id="trainer-edit-lastname" />
                                    <div class="invalid-feedback d-block" data-error-for="lastname"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-name">Имя*</label>
                                    <input type="text" class="form-control" name="name" id="trainer-edit-name" />
                                    <div class="invalid-feedback d-block" data-error-for="name"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-email">Email</label>
                                    <input type="email" class="form-control" name="email" id="trainer-edit-email" autocomplete="off" />
                                    <div class="invalid-feedback d-block" data-error-for="email"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-phone">Телефон</label>
                                    <input type="tel" class="form-control" name="phone" id="trainer-edit-phone" placeholder="+7 (XXX) XXX-XX-XX" />
                                    <div class="invalid-feedback d-block" data-error-for="phone"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                @include('admin.trainers._teams_checkboxes', [
                                    'teamsFieldIdPrefix' => 'trainer-edit',
                                    'teamsLabel' => 'Группы',
                                ])
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-description">Описание</label>
                                    <textarea class="form-control" name="description" id="trainer-edit-description" rows="3"></textarea>
                                    <div class="invalid-feedback d-block" data-error-for="description"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-sort-order">Порядок сортировки</label>
                                    <input type="number" class="form-control" name="sort_order" id="trainer-edit-sort-order" min="0" />
                                    <div class="invalid-feedback d-block" data-error-for="sort_order"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-is-enabled">Активен</label>
                                    <select class="form-select" name="is_enabled" id="trainer-edit-is-enabled">
                                        <option value="1">Да</option>
                                        <option value="0">Нет</option>
                                    </select>
                                    <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-default-base-salary">Оклад по умолчанию</label>
                                    <input type="number" class="form-control" name="default_base_salary" id="trainer-edit-default-base-salary" min="0" step="1" />
                                    <div class="invalid-feedback d-block" data-error-for="default_base_salary"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="trainer-edit-default-rate">Ставка за тренировку</label>
                                    <input type="number" class="form-control" name="default_rate_per_training" id="trainer-edit-default-rate" min="0" step="1" />
                                    <div class="invalid-feedback d-block" data-error-for="default_rate_per_training"></div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="trainer-edit-user-id" value="" />

                        <div class="buttons-wrap change-pass-wrap" id="trainer-change-pass-wrap" style="display: none;">
                            <div class="d-flex align-items-center mt-3">
                                <div class="position-relative wrap-change-password">
                                    <input type="password" id="trainer-new-password" class="form-control" placeholder="Новый пароль" autocomplete="new-password">
                                    <span toggle="#trainer-new-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                </div>
                                <button type="button" id="trainer-apply-password-btn" class="btn btn-primary ml-2">Применить</button>
                                <button type="button" id="trainer-cancel-change-password-btn" class="btn btn-danger ml-2">Отмена</button>
                            </div>
                            <div id="trainer-password-error-message" class="text-danger mt-2" style="display:none;">
                                Пароль должен быть не менее 8 символов
                            </div>
                        </div>

                        <div class="button-group buttons-wrap mt-3">
                            <button type="button"
                                    id="trainer-change-password-btn"
                                    class="btn btn-primary mt-3 change-password-btn {{ $canChangeTrainerPassword ? '' : 'opacity-50 pe-none' }}"
                                    @unless($canChangeTrainerPassword)
                                        aria-disabled="true"
                                        tabindex="-1"
                                        data-bs-toggle="tooltip"
                                        title="Нет прав на изменение пароля"
                                    @endunless
                            >
                                <i class="fa-solid fa-key me-1"></i> Изменить пароль
                            </button>

                            @unless($canChangeTrainerPassword)
                                <div class="form-text text-muted mt-2">
                                    <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение пароля
                                </div>
                            @endunless

                            <button type="button" class="btn btn-primary mt-3" id="trainerEditSubmit">Сохранить изменения</button>
                            <button type="button" class="btn btn-danger mt-3" id="trainerDeleteBtn">Удалить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('includes.modal.editAvatar')
    </div>
@endsection

@section('scripts')
    @parent
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/jquery.inputmask.min.js"></script>
    <script>
        window.__trainerPageConfig = {
            defaultAvatar: @json(asset('img/default-avatar.png')),
            storeUrl: @json(route('admin.trainers.store')),
            dataUrl: @json(route('admin.trainers.data')),
            columnsSettingsUrl: @json(route('admin.trainers.columns-settings.get')),
            canChangePassword: @json($canChangeTrainerPassword ?? false),
        };
    </script>
    <script>
        $(document).ready(function () {
            const defaultAvatar = window.__trainerPageConfig.defaultAvatar;
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const defaultFilterStatus = 'active';

            const defaultColumnsVisibility = {
                avatar: true,
                full_name: true,
                teams_label: true,
                email: true,
                default_base_salary: true,
                default_rate_per_training: true,
                sort_order: true,
                is_enabled: true,
                actions: true,
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = {
                avatar: 1,
                full_name: 2,
                teams_label: 3,
                email: 4,
                default_base_salary: 5,
                default_rate_per_training: 6,
                sort_order: 7,
                is_enabled: 8,
                actions: 9,
            };

            function toBool(val, fallback = true) {
                if (val === undefined || val === null) return fallback;
                if (typeof val === 'boolean') return val;
                if (typeof val === 'number') return val === 1;
                if (typeof val === 'string') {
                    const v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }
                return fallback;
            }

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    column.visible(isVisible);
                    $('.column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });

                try {
                    table.columns.adjust();
                } catch (e) {
                    /* no-op */
                }
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: window.__trainerPageConfig.columnsSettingsUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                Object.prototype.hasOwnProperty.call(response, key) ? response[key] : defaultColumnsVisibility[key],
                                defaultColumnsVisibility[key]
                            );
                        });
                        currentColumnsConfig = merged;
                        applyVisibleColumns(currentColumnsConfig);
                    },
                    error: function () {
                        currentColumnsConfig = {...defaultColumnsVisibility};
                        applyVisibleColumns(currentColumnsConfig);
                    }
                });
            }

            function trainersFilterParams() {
                return {
                    name: $('#filter-name').val() || '',
                    team_id: $('#filter-team').val() || '',
                    status: $('#filter-status').val() || '',
                };
            }

            function trainersHasNonDefaultFilters() {
                const params = trainersFilterParams();
                return params.name !== ''
                    || params.team_id !== ''
                    || params.status !== defaultFilterStatus;
            }

            function syncTrainersFiltersCollapseState() {
                const hasActive = trainersHasNonDefaultFilters();
                const collapseEl = document.getElementById('trainersReportFiltersCollapse');
                const $toggle = $('#trainersReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const table = $('#trainers-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: window.__trainerPageConfig.dataUrl,
                    type: 'GET',
                    data: function (d) {
                        const params = trainersFilterParams();
                        d.name = params.name;
                        d.team_id = params.team_id;
                        d.status = params.status;
                    }
                },
                columns: [
                    {
                        data: null,
                        name: 'rownum',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        data: 'avatar_url',
                        name: 'avatar_url',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data) {
                            const url = data || defaultAvatar;
                            return '<img src="' + url + '" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">';
                        }
                    },
                    {
                        data: 'full_name',
                        name: 'full_name',
                        defaultContent: ''
                    },
                    {
                        data: 'teams_label',
                        name: 'teams_label',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            return data ? data : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        data: 'email',
                        name: 'email',
                        defaultContent: ''
                    },
                    {
                        data: 'default_base_salary',
                        name: 'default_base_salary',
                        className: 'text-end text-nowrap'
                    },
                    {
                        data: 'default_rate_per_training',
                        name: 'default_rate_per_training',
                        className: 'text-end text-nowrap'
                    },
                    {
                        data: 'sort_order',
                        name: 'sort_order',
                        className: 'text-center'
                    },
                    {
                        data: 'status_label',
                        name: 'is_enabled',
                        className: 'text-center'
                    },
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            return '<button type="button" class="btn btn-sm btn-outline-primary js-trainer-edit" data-id="' + row.id + '">Редактировать</button>';
                        }
                    }
                ],
                order: [[7, 'asc']],
                scrollX: true,
                language: @include('partials.datatables.ru')
            });

            loadColumnsConfigFromServer();
            table.columns.adjust();

            function reloadTrainersTable() {
                table.ajax.reload();
                syncTrainersFiltersCollapseState();
            }

            window.__reloadTrainersTable = function () {
                table.ajax.reload(null, false);
                syncTrainersFiltersCollapseState();
            };

            $('#filter-apply').on('click', function () {
                reloadTrainersTable();
            });

            $('#trainers-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadTrainersTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-team').val('');
                $('#filter-status').val(defaultFilterStatus);
                reloadTrainersTable();
            });

            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadTrainersTable();
                }
            });

            $('#trainersReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#trainersReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#trainersReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );

                try {
                    table.columns.adjust();
                } catch (e) {
                    /* no-op */
                }
            });

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: window.__trainerPageConfig.columnsSettingsUrl,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        columns: currentColumnsConfig
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });
        });
    </script>
    @verbatim
    <script>
        (function () {
            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.getAttribute('content') : '';
            const defaultAvatar = window.__trainerPageConfig.defaultAvatar;

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.js-trainer-teams-checkboxes').forEach(el => {
                    el.classList.remove('border-danger');
                });
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
                const box = form.querySelector('.js-form-error');
                if (box) {
                    box.textContent = '';
                    box.classList.add('d-none');
                }
            }

            function showFormError(form, message) {
                const box = form.querySelector('.js-form-error');
                if (!box || !message) return;
                box.textContent = message;
                box.classList.remove('d-none');
            }

            function hideModal(modalId) {
                const el = document.getElementById(modalId);
                if (!el) return;
                const instance = bootstrap.Modal.getInstance(el);
                if (instance) {
                    instance.hide();
                }
            }

            function handleSaveResponse(form, ok, status, data, options = {}) {
                if (ok) {
                    if (typeof window.__reloadTrainersTable === 'function') {
                        window.__reloadTrainersTable();
                    }
                    if (options.modalId) {
                        hideModal(options.modalId);
                    }
                    if (options.resetForm) {
                        form.reset();
                        clearErrors(form);
                    }
                    return;
                }

                if (status === 422) {
                    applyErrors(form, data.errors || {});
                    const fieldKeys = Object.keys(data.errors || {});
                    if (fieldKeys.length === 0 && data.message) {
                        showFormError(form, data.message);
                    } else if (fieldKeys.length > 0 && data.message) {
                        showFormError(form, data.message);
                    }
                    return;
                }

                showFormError(form, data.message || `Не удалось сохранить (код ${status}). Попробуйте ещё раз.`);
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const baseKey = key.split('.')[0];
                    let input = form.querySelector(`[name="${key}"]`);
                    if (!input && baseKey === 'team_ids') {
                        const teamsBox = form.querySelector('.js-trainer-teams-checkboxes');
                        if (teamsBox) teamsBox.classList.add('border-danger');
                        form.querySelectorAll('input[name="team_ids[]"]').forEach(cb => cb.classList.add('is-invalid'));
                    }
                    let err = form.querySelector(`[data-error-for="${key}"]`);
                    if (!err) err = form.querySelector(`[data-error-for="${baseKey}"]`);
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = (messages && messages[0]) ? messages[0] : 'Ошибка';
                });
            }

            function setTeamIdsCheckboxes(form, teamIds) {
                const ids = (teamIds || []).map(id => parseInt(id, 10));
                form.querySelectorAll('input[name="team_ids[]"]').forEach(cb => {
                    cb.checked = ids.includes(parseInt(cb.value, 10));
                });
            }

            async function postForm(url, form, method = 'POST') {
                const fd = new FormData(form);
                if (method !== 'POST') {
                    fd.set('_method', method);
                }
                const res = await fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, status: res.status, data };
            }

            function applyTrainerEditAvatar(data) {
                const modal = document.getElementById('trainerEditModal');
                const clipImg = modal?.querySelector('.avatar-clip img');
                const thumbUrl = data.avatar_url || defaultAvatar;

                if (clipImg) {
                    clipImg.src = thumbUrl;
                }

                const avatarUser = {
                    id: data.user_id || null,
                    image: data.image || null,
                    image_crop: data.image_crop || null,
                };

                if (typeof window.setZoomImageFromUser === 'function') {
                    window.setZoomImageFromUser(avatarUser);
                }
                if (typeof window.setSelectedUserContext === 'function') {
                    window.setSelectedUserContext(avatarUser);
                }
                if (typeof window.setOpenPhotoVisibilityByUser === 'function') {
                    window.setOpenPhotoVisibilityByUser(avatarUser);
                }

                const avatarEl = modal?.querySelector('.avatar');
                if (avatarEl && typeof window.initAvatarHoverMenu === 'function') {
                    window.initAvatarHoverMenu(avatarEl);
                }
            }

            function initTrainerEditPhoneMask() {
                const $phone = window.jQuery ? window.jQuery('#trainer-edit-phone') : null;
                if (!$phone || !$phone.length || !$.fn.inputmask) {
                    return;
                }
                if ($phone.inputmask) {
                    $phone.inputmask('remove');
                }
                $phone.inputmask('+7 (999) 999-99-99');
            }

            function resetTrainerPasswordUi() {
                const changeBtn = document.getElementById('trainer-change-password-btn');
                const passWrap = document.getElementById('trainer-change-pass-wrap');
                const newPassword = document.getElementById('trainer-new-password');
                const err = document.getElementById('trainer-password-error-message');
                if (changeBtn) changeBtn.style.display = '';
                if (passWrap) passWrap.style.display = 'none';
                if (newPassword) newPassword.value = '';
                if (err) err.style.display = 'none';
            }

            function initTrainerPasswordToggle() {
                const wrap = document.querySelector('#trainerEditModal .wrap-change-password');
                if (!wrap || wrap.dataset.toggleInit === '1') {
                    return;
                }
                wrap.dataset.toggleInit = '1';
                const toggle = wrap.querySelector('.toggle-password');
                const input = wrap.querySelector('#trainer-new-password');
                if (!toggle || !input) {
                    return;
                }
                toggle.addEventListener('click', function () {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    toggle.classList.toggle('fa-eye');
                    toggle.classList.toggle('fa-eye-slash');
                });
            }

            const createForm = document.getElementById('trainerCreateForm');
            const editForm = document.getElementById('trainerEditForm');

            document.getElementById('trainer-change-password-btn')?.addEventListener('click', function () {
                this.style.display = 'none';
                const passWrap = document.getElementById('trainer-change-pass-wrap');
                if (passWrap) passWrap.style.display = '';
            });

            document.getElementById('trainer-cancel-change-password-btn')?.addEventListener('click', function () {
                resetTrainerPasswordUi();
            });

            document.getElementById('trainer-apply-password-btn')?.addEventListener('click', function () {
                const userId = document.getElementById('trainer-edit-user-id')?.value;
                const newPassword = document.getElementById('trainer-new-password')?.value || '';
                const err = document.getElementById('trainer-password-error-message');

                if (!userId) {
                    return;
                }
                if (newPassword.length < 8) {
                    if (err) err.style.display = '';
                    return;
                }
                if (err) err.style.display = 'none';

                $.ajax({
                    url: `/admin/user/${userId}/update-password`,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token },
                    data: { password: newPassword },
                    success: function (response) {
                        if (response.success) {
                            resetTrainerPasswordUi();
                            if (typeof showSuccessModal === 'function') {
                                showSuccessModal('Обновление пароля', 'Пароль успешно обновлен.');
                            }
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            || 'Произошла ошибка при сохранении пароля.';
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Ошибка', msg, 1);
                        } else {
                            alert(msg);
                        }
                    },
                });
            });

            document.getElementById('trainerEditModal')?.addEventListener('hidden.bs.modal', function () {
                resetTrainerPasswordUi();
                const $phone = window.jQuery ? window.jQuery('#trainer-edit-phone') : null;
                if ($phone && $phone.inputmask) {
                    $phone.inputmask('remove');
                }
            });

            if (!window.__trainerPageConfig.canChangePassword) {
                document.addEventListener('DOMContentLoaded', function () {
                    const btn = document.getElementById('trainer-change-password-btn');
                    if (btn) new bootstrap.Tooltip(btn);
                });
            }

            document.getElementById('trainerCreateSubmit')?.addEventListener('click', async () => {
                clearErrors(createForm);
                const { ok, status, data } = await postForm(window.__trainerPageConfig.storeUrl, createForm, 'POST');
                handleSaveResponse(createForm, ok, status, data, {
                    modalId: 'trainerCreateModal',
                    resetForm: true,
                });
            });

            document.addEventListener('click', async (event) => {
                const btn = event.target.closest('.js-trainer-edit');
                if (!btn) {
                    return;
                }

                clearErrors(editForm);
                const id = btn.getAttribute('data-id');
                const res = await fetch(`/admin/trainers/${id}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                editForm.querySelector('[name="id"]').value = data.id;
                const userIdEl = document.getElementById('trainer-edit-user-id');
                if (userIdEl) userIdEl.value = data.user_id || '';
                editForm.querySelector('[name="lastname"]').value = data.lastname || '';
                editForm.querySelector('[name="name"]').value = data.name || '';
                editForm.querySelector('[name="email"]').value = data.email || '';
                editForm.querySelector('[name="phone"]').value = data.phone || '';
                initTrainerEditPhoneMask();
                editForm.querySelector('[name="description"]').value = data.description || '';
                resetTrainerPasswordUi();
                editForm.querySelector('[name="is_enabled"]').value = String(data.is_enabled ?? 1);
                editForm.querySelector('[name="sort_order"]').value = String(data.sort_order ?? 0);
                const baseSalaryEl = editForm.querySelector('[name="default_base_salary"]');
                if (baseSalaryEl) {
                    baseSalaryEl.value = data.default_base_salary ?? '0';
                }
                const rateEl = editForm.querySelector('[name="default_rate_per_training"]');
                if (rateEl) {
                    rateEl.value = data.default_rate_per_training ?? '0';
                }
                setTeamIdsCheckboxes(editForm, data.team_ids || []);
                applyTrainerEditAvatar(data);
                const modal = new bootstrap.Modal(document.getElementById('trainerEditModal'));
                modal.show();
            });

            document.getElementById('trainerEditSubmit')?.addEventListener('click', async () => {
                clearErrors(editForm);
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) {
                    showFormError(editForm, 'Не выбран тренер для редактирования');
                    return;
                }
                const { ok, status, data } = await postForm(`/admin/trainers/${id}`, editForm, 'PUT');
                handleSaveResponse(editForm, ok, status, data, { modalId: 'trainerEditModal' });
            });

            function performTrainerDelete() {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;

                const confirmEl = document.getElementById('confirmDeleteModal');
                const editEl = document.getElementById('trainerEditModal');
                $(confirmEl).off('hidden.bs.modal.return');

                fetch(`/admin/trainers/${id}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                }).then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (res.ok) {
                        $(editEl).off('hidden.bs.modal.openNext');
                        hideModal('trainerEditModal');

                        if (typeof window.__reloadTrainersTable === 'function') {
                            window.__reloadTrainersTable();
                        }

                        if (typeof showSuccessModal === 'function') {
                            showSuccessModal(
                                'Удаление тренера',
                                data.message || 'Тренер успешно удалён.',
                                0
                            );
                        }
                        return;
                    }

                    const msg = data.message || 'Произошла ошибка при удалении тренера.';
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Ошибка', msg, 1);
                    } else if ($('#errorModal').length) {
                        $('#error-modal-message').text(msg);
                        $('#errorModal').modal('show');
                    }
                });
            }

            document.getElementById('trainerDeleteBtn')?.addEventListener('click', function () {
                const id = editForm.querySelector('[name="id"]').value;
                if (!id) return;
                const title = 'Удаление тренера';
                const text = 'Вы уверены, что хотите удалить тренера? Учётная запись в CRM также будет удалена.';
                if (typeof showConfirmDeleteModal === 'function') {
                    showConfirmDeleteModal(title, text, performTrainerDelete);
                    return;
                }
                if (confirm(text)) {
                    performTrainerDelete();
                }
            });

            initTrainerPasswordToggle();
            initTrainerEditPhoneMask();
        })();
    </script>
    @endverbatim
@endsection
