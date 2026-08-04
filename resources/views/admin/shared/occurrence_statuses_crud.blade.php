@php
    $losHasActiveFilters = false;
    $losCanManage = auth()->user()->can('lessonOccurrenceStatuses.manage');
    $losFaIcons = config('lesson_occurrence_status_icons');
    $losColorSwatches = [
        '#64748B', '#94A3B8', '#5B8DEF', '#6BA8B8', '#4CAF82', '#7BC47F',
        '#E0A04A', '#E08B5A', '#E06B6B', '#C97BA5', '#8B7EC8', '#ADB5BD',
    ];
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Статусы занятий</h1>
            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                @can('lessonOccurrenceStatuses.manage')
                    <button type="button"
                            class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#losCreateModal"
                            title="Добавить статус">
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
                        data-bs-target="#losReportFiltersCollapse"
                        aria-expanded="{{ $losHasActiveFilters ? 'true' : 'false' }}"
                        aria-controls="losReportFiltersCollapse"
                        id="losReportFiltersToggle">
                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                    </span>
                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                </button>

                <div class="dropdown payments-report-toolbar-dropdown">
                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            id="losColumnsDropdown"
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
                         aria-labelledby="losColumnsDropdown">
                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="sort_order" id="colLosSort" checked>
                            <label class="form-check-label" for="colLosSort">Порядок</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="title" id="colLosTitle" checked>
                            <label class="form-check-label" for="colLosTitle">Название</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="icon" id="colLosIcon" checked>
                            <label class="form-check-label" for="colLosIcon">Иконка</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="type_label" id="colLosType" checked>
                            <label class="form-check-label" for="colLosType">Тип</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="consumes_lesson_label" id="colLosConsumes" checked>
                            <label class="form-check-label" for="colLosConsumes">Списывает</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_active_label" id="colLosActive" checked>
                            <label class="form-check-label" for="colLosActive">Активен</label>
                        </div>
                        @can('lessonOccurrenceStatuses.manage')
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colLosActions" checked>
                                <label class="form-check-label" for="colLosActions">Действия</label>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $losHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="losReportFiltersCollapse">
    <form id="los-report-filters" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="los-filter-title">Название</label>
                <input id="los-filter-title" class="form-control" type="text" placeholder="Поиск по названию">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="los-filter-type">Тип</label>
                <select id="los-filter-type" class="form-select">
                    <option value="" selected>Все</option>
                    <option value="system">Системные</option>
                    <option value="custom">Свои</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="los-filter-status">Активен</label>
                <select id="los-filter-status" class="form-select">
                    <option value="" selected>Все</option>
                    <option value="active">Только активные</option>
                    <option value="inactive">Только неактивные</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="los-filter-consumes">Списывает</label>
                <select id="los-filter-consumes" class="form-select">
                    <option value="" selected>Все</option>
                    <option value="yes">Да</option>
                    <option value="no">Нет</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button id="los-filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                <button id="los-filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
            </div>
        </div>
    </form>
</div>

<p class="text-muted small mb-2">
    Системные статусы нельзя удалить и переименовать; цвет, иконка и порядок настраиваются.
    Признак «списывает занятие» задаёт, уменьшается ли остаток абонемента при выставлении этого статуса занятию (правило действует на момент отметки).
</p>

<div class="table-responsive">
    <table id="los-statuses-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
        <thead>
        <tr>
            <th>Порядок</th>
            <th>Название</th>
            <th>Иконка</th>
            <th>Тип</th>
            <th>Списывает</th>
            <th>Активен</th>
            @can('lessonOccurrenceStatuses.manage')
                <th>Действия</th>
            @endcan
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

@can('lessonOccurrenceStatuses.manage')
    {{-- Создание --}}
    <div class="modal fade" id="losCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered los-status-modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Новый статус</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="los-create-form">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Название*</label>
                            <input type="text" name="title" class="form-control form-control-sm">
                            <div class="invalid-feedback" data-err="title"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Цвет*</label>
                            <input type="hidden" name="color" id="los-create-color" value="#94A3B8">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <input type="color" id="los-create-color-picker"
                                       class="form-control form-control-color los-color-picker-input"
                                       value="#94A3B8" title="Выберите цвет">
                                <span class="small text-muted font-monospace" id="los-create-color-hex">#94A3B8</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-0 los-color-swatches" data-los-scope="create">
                                @foreach ($losColorSwatches as $hex)
                                    <button type="button" class="los-color-swatch" data-hex="{{ $hex }}"
                                            style="background-color: {{ $hex }};" title="{{ $hex }}"
                                            aria-label="Цвет {{ $hex }}"></button>
                                @endforeach
                            </div>
                            <div class="invalid-feedback d-block" data-err="color"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Иконка</label>
                            <input type="hidden" name="icon" id="los-create-icon" value="">
                            <div class="d-flex flex-wrap gap-2 mb-1 align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="los-create-icon-clear">
                                    Без иконки
                                </button>
                            </div>
                            <div id="los-create-icon-grid" class="los-icon-grid border rounded p-2 bg-light">
                                @foreach ($losFaIcons as $faClass)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary los-icon-btn"
                                            data-icon="{{ $faClass }}" title="{{ $faClass }}">
                                        <i class="{{ $faClass }}" aria-hidden="true"></i>
                                    </button>
                                @endforeach
                            </div>
                            <div class="invalid-feedback d-block" data-err="icon"></div>
                        </div>
                        <input type="hidden" name="consumes_lesson" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="consumes_lesson" value="1" id="los-create-consumes">
                            <label class="form-check-label" for="los-create-consumes">Списывает занятие с абонемента</label>
                        </div>
                        <div class="invalid-feedback d-block" data-err="consumes_lesson"></div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="los-create-active" checked>
                            <label class="form-check-label" for="los-create-active">Активен</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary btn-sm" id="los-create-submit">Сохранить</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Редактирование --}}
    <div class="modal fade" id="losEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered los-status-modal-dialog los-edit-status-modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Редактировать статус</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="los-edit-form">
                        @csrf
                        <input type="hidden" name="id" id="los-edit-id">
                        <div class="mb-2">
                            <label class="form-label">Название*</label>
                            <input type="text" name="title" id="los-edit-title" class="form-control form-control-sm">
                            <div class="invalid-feedback" data-err="title"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Цвет*</label>
                            <input type="hidden" name="color" id="los-edit-color" value="#000000">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <input type="color" id="los-edit-color-picker"
                                       class="form-control form-control-color los-color-picker-input"
                                       value="#000000" title="Выберите цвет">
                                <span class="small text-muted font-monospace" id="los-edit-color-hex">#000000</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-0 los-color-swatches" data-los-scope="edit">
                                @foreach ($losColorSwatches as $hex)
                                    <button type="button" class="los-color-swatch" data-hex="{{ $hex }}"
                                            style="background-color: {{ $hex }};" title="{{ $hex }}"
                                            aria-label="Цвет {{ $hex }}"></button>
                                @endforeach
                            </div>
                            <div class="invalid-feedback d-block" data-err="color"></div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Иконка</label>
                            <input type="hidden" name="icon" id="los-edit-icon" value="">
                            <div class="d-flex flex-wrap gap-2 mb-1 align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="los-edit-icon-clear">
                                    Без иконки
                                </button>
                            </div>
                            <div id="los-edit-icon-grid" class="los-icon-grid border rounded p-2 bg-light">
                                @foreach ($losFaIcons as $faClass)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary los-icon-btn"
                                            data-icon="{{ $faClass }}" title="{{ $faClass }}">
                                        <i class="{{ $faClass }}" aria-hidden="true"></i>
                                    </button>
                                @endforeach
                            </div>
                            <div class="invalid-feedback d-block" data-err="icon"></div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="consumes_lesson" value="1" id="los-edit-consumes">
                            <label class="form-check-label" for="los-edit-consumes">Списывает занятие с абонемента</label>
                        </div>
                        <div class="invalid-feedback d-block" data-err="consumes_lesson"></div>
                        <div class="mb-2">
                            <label class="form-label">Порядок*</label>
                            <input type="number" name="sort_order" id="los-edit-sort" class="form-control form-control-sm" min="0" max="65535">
                            <div class="invalid-feedback" data-err="sort_order"></div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="los-edit-active">
                            <label class="form-check-label" for="los-edit-active">Активен</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary btn-sm" id="los-edit-submit">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
@endcan

@include('includes.logModal')

@push('scripts')
    <style>
        .los-status-modal-dialog {
            max-width: 26rem;
        }
        .los-color-picker-input {
            width: 3rem;
            height: 2.25rem;
            padding: 0.125rem;
            cursor: pointer;
        }
        .los-color-swatch {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: .25rem;
            border: 1px solid rgba(0, 0, 0, .18);
            padding: 0;
            cursor: pointer;
            flex-shrink: 0;
        }
        .los-color-swatch:focus-visible {
            outline: 2px solid var(--bs-primary);
            outline-offset: 2px;
        }
        .los-icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(2rem, 1fr));
            gap: .35rem;
            max-height: 10rem;
            overflow-y: auto;
            width: 100%;
            box-sizing: border-box;
        }
        #los-edit-icon-grid.los-icon-grid {
            max-height: none;
            overflow-y: visible;
        }
        .los-icon-btn {
            width: 2.35rem;
            height: 2.35rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .los-icon-btn.active {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #fff;
        }
        .los-icon-btn.active i {
            color: #fff;
        }
    </style>
    <script>
        $(document).ready(function () {
            const canManageLos = @json($losCanManage);
            const escHtml = (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.escapeHtml === 'function')
                ? window.KidsCrmTooltip.escapeHtml
                : function (value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };

            function losFilterParams() {
                return {
                    title: $('#los-filter-title').val() || '',
                    type: $('#los-filter-type').val() || '',
                    status: $('#los-filter-status').val() || '',
                    consumes: $('#los-filter-consumes').val() || ''
                };
            }

            const dtApi = KidsCrmDataTable.create('#los-statuses-table', {
                columnsSettings: {
                    defaults: {
                        sort_order: true,
                        title: true,
                        icon: true,
                        type_label: true,
                        consumes_lesson_label: true,
                        is_active_label: true,
                        ...(canManageLos ? { actions: true } : {}),
                    },
                    urls: {
                        get: @json(route('admin.lesson-packages.occurrence-statuses.columns-settings.get')),
                        save: @json(route('admin.lesson-packages.occurrence-statuses.columns-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.lesson-packages.occurrence-statuses.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = losFilterParams();
                            d.title = params.title;
                            d.type = params.type;
                            d.status = params.status;
                            d.consumes = params.consumes;
                        }
                    },
                    searching: false,
                    order: [[0, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { key: 'sort_order', type: 'sort', data: 'sort_order', name: 'sort_order' },
                    {
                        key: 'title',
                        type: 'text',
                        data: 'title',
                        name: 'title',
                        className: 'dt-col-text',
                        render: function (value, type, row) {
                            if (type !== 'display') {
                                return value || '';
                            }
                            const color = row.color || '#94A3B8';
                            return '<span class="badge rounded-pill" style="background-color: ' + escHtml(color)
                                + '; max-width: 100%; white-space: normal;">' + escHtml(value || '') + '</span>';
                        }
                    },
                    {
                        key: 'icon',
                        type: 'text',
                        data: 'icon',
                        name: 'icon',
                        orderable: false,
                        className: 'text-center',
                        render: function (value, type) {
                            if (type !== 'display') {
                                return value || '';
                            }
                            if (!value) {
                                return '—';
                            }
                            return '<i class="' + escHtml(value) + ' fa-lg" title="' + escHtml(value) + '"></i>';
                        }
                    },
                    { key: 'type_label', type: 'text', data: 'type_label', name: 'type_label' },
                    {
                        key: 'consumes_lesson_label',
                        type: 'badge',
                        data: 'consumes_lesson_label',
                        name: 'consumes_lesson_label',
                        badgeKey: 'consumes_lesson',
                        className: 'dt-col-badge text-center',
                    },
                    {
                        key: 'is_active_label',
                        type: 'badge',
                        data: 'is_active_label',
                        name: 'is_active_label',
                        badgeKey: 'is_active',
                        className: 'dt-col-badge text-center',
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        when: canManageLos,
                        render: function (data, type, row) {
                            let html = '<button type="button" class="btn btn-sm btn-outline-primary los-edit-btn"'
                                + ' data-id="' + escHtml(row.id) + '"'
                                + ' data-system="' + (row.is_system ? '1' : '0') + '"'
                                + ' data-title="' + escHtml(row.title || '') + '"'
                                + ' data-color="' + escHtml(row.color || '') + '"'
                                + ' data-icon="' + escHtml(row.icon || '') + '"'
                                + ' data-sort="' + escHtml(row.sort_order) + '"'
                                + ' data-active="' + (row.is_active ? '1' : '0') + '"'
                                + ' data-consumes-lesson="' + (row.consumes_lesson ? '1' : '0') + '">'
                                + 'Изменить</button>';
                            if (!row.is_system) {
                                html += ' <button type="button" class="btn btn-sm btn-outline-danger los-delete-btn"'
                                    + ' data-id="' + escHtml(row.id) + '"'
                                    + ' data-title="' + escHtml(row.title || '') + '">Удалить</button>';
                            }
                            return html;
                        }
                    },
                ],
            });

            function reloadLosTable() {
                dtApi.reload({ keepPage: true });
            }

            $('#los-filter-apply').on('click', reloadLosTable);
            $('#los-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadLosTable();
            });
            $('#los-filter-reset').on('click', function () {
                $('#los-filter-title').val('');
                $('#los-filter-type').val('');
                $('#los-filter-status').val('');
                $('#los-filter-consumes').val('');
                reloadLosTable();
            });
            $('#los-filter-title').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadLosTable();
                }
            });

            @can('lessonOccurrenceStatuses.manage')
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function normalizeHex(h) {
                if (!h || typeof h !== 'string') return '#000000';
                let s = h.trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(s)) return s;
                return '#000000';
            }

            function setLosColor(scope, hex) {
                const h = normalizeHex(hex);
                const hidden = document.getElementById('los-' + scope + '-color');
                const picker = document.getElementById('los-' + scope + '-color-picker');
                const label = document.getElementById('los-' + scope + '-color-hex');
                if (hidden) hidden.value = h;
                if (picker) picker.value = h;
                if (label) label.textContent = h;
            }

            function bindLosColor(scope) {
                const picker = document.getElementById('los-' + scope + '-color-picker');
                document.querySelectorAll('.los-color-swatches[data-los-scope="' + scope + '"] .los-color-swatch')
                    .forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setLosColor(scope, btn.getAttribute('data-hex'));
                        });
                    });
                picker?.addEventListener('input', function () {
                    setLosColor(scope, picker.value);
                });
            }

            function setLosIconSelection(scope, iconClass) {
                const hidden = document.getElementById('los-' + scope + '-icon');
                const grid = document.getElementById('los-' + scope + '-icon-grid');
                const v = iconClass || '';
                if (hidden) hidden.value = v;
                grid?.querySelectorAll('.los-icon-btn').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-icon') === v);
                });
            }

            function bindLosIcons(scope) {
                const grid = document.getElementById('los-' + scope + '-icon-grid');
                grid?.querySelectorAll('.los-icon-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setLosIconSelection(scope, btn.getAttribute('data-icon'));
                    });
                });
                document.getElementById('los-' + scope + '-icon-clear')?.addEventListener('click', function () {
                    setLosIconSelection(scope, '');
                });
            }

            bindLosColor('create');
            bindLosColor('edit');
            bindLosIcons('create');
            bindLosIcons('edit');

            document.getElementById('losCreateModal')?.addEventListener('shown.bs.modal', function () {
                const form = document.getElementById('los-create-form');
                form?.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form?.querySelectorAll('[data-err]').forEach(function (el) { el.textContent = ''; });
                setLosColor('create', '#94A3B8');
                setLosIconSelection('create', '');
                const consumeCreate = document.getElementById('los-create-consumes');
                if (consumeCreate) consumeCreate.checked = false;
                const titleInput = form?.querySelector('[name="title"]');
                if (titleInput) titleInput.value = '';
                const activeCreate = document.getElementById('los-create-active');
                if (activeCreate) activeCreate.checked = true;
            });

            function clearErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-err]').forEach(el => {
                    el.textContent = '';
                });
            }

            function applyErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, msgs]) => {
                    const input = form.querySelector('[name="' + key + '"]');
                    const fb = form.querySelector('[data-err="' + key + '"]');
                    if (input) input.classList.add('is-invalid');
                    if (fb && msgs && msgs[0]) fb.textContent = msgs[0];
                });
            }

            document.getElementById('los-create-submit')?.addEventListener('click', async () => {
                const form = document.getElementById('los-create-form');
                clearErrors(form);

                const fd = new FormData(form);
                if (!form.querySelector('[name="is_active"]').checked) {
                    fd.delete('is_active');
                }

                const res = await fetch(`{{ route('admin.lesson-packages.occurrence-statuses.store') }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    applyErrors(form, data.errors || {});
                    return;
                }
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('losCreateModal'))?.hide();
                    reloadLosTable();
                }
            });

            $('#los-statuses-table').on('click', '.los-edit-btn', function () {
                const btn = this;
                const isSystem = btn.getAttribute('data-system') === '1';
                document.getElementById('los-edit-id').value = btn.getAttribute('data-id');
                const titleInput = document.getElementById('los-edit-title');
                titleInput.value = btn.getAttribute('data-title') || '';
                titleInput.disabled = isSystem;
                setLosColor('edit', btn.getAttribute('data-color') || '#000000');
                setLosIconSelection('edit', btn.getAttribute('data-icon') || '');
                document.getElementById('los-edit-sort').value = btn.getAttribute('data-sort') || '0';
                document.getElementById('los-edit-active').checked = btn.getAttribute('data-active') === '1';
                document.getElementById('los-edit-consumes').checked = btn.getAttribute('data-consumes-lesson') === '1';

                const form = document.getElementById('los-edit-form');
                clearErrors(form);
                new bootstrap.Modal(document.getElementById('losEditModal')).show();
            });

            document.getElementById('los-edit-submit')?.addEventListener('click', async () => {
                const form = document.getElementById('los-edit-form');
                clearErrors(form);
                const id = document.getElementById('los-edit-id').value;
                const titleInput = document.getElementById('los-edit-title');
                const isSystem = titleInput.disabled;

                const payload = {
                    color: document.getElementById('los-edit-color').value,
                    icon: document.getElementById('los-edit-icon').value || null,
                    sort_order: parseInt(document.getElementById('los-edit-sort').value, 10),
                    consumes_lesson: document.getElementById('los-edit-consumes').checked,
                    is_active: document.getElementById('los-edit-active').checked ? 1 : 0,
                };
                if (!isSystem) {
                    payload.title = titleInput.value;
                }

                const res = await fetch(`/admin/lesson-packages/occurrence-statuses/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 422) {
                    applyErrors(form, data.errors || {});
                    return;
                }
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('losEditModal'))?.hide();
                    reloadLosTable();
                }
            });

            $('#los-statuses-table').on('click', '.los-delete-btn', async function () {
                const btn = this;
                if (!confirm('Удалить статус «' + (btn.getAttribute('data-title') || '') + '»?')) {
                    return;
                }
                const id = btn.getAttribute('data-id');
                const res = await fetch(`/admin/lesson-packages/occurrence-statuses/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                if (res.ok) {
                    reloadLosTable();
                }
            });
            @endcan

            if (typeof showLogModal === 'function') {
                showLogModal(@json(route('logs.data.lesson-occurrence-status')));
            }
        });
    </script>
@endpush
