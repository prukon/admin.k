@php
    /** @var \Illuminate\Support\Collection|array $occurrenceStatuses */
    $occurrenceStatuses = $occurrenceStatuses ?? collect();
    $losColspan = auth()->user()->can('lessonPackages.view') ? 7 : 6;
    $losFaIcons = config('lesson_occurrence_status_icons');
    $losColorSwatches = ['#212529', '#495057', '#6c757d', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0', '#0d6efd', '#6f42c1', '#d63384', '#adb5bd'];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="mb-0">Статусы занятий</h4>
    <div class="d-flex flex-wrap gap-2">
        @can('lessonPackages.view')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#losCreateModal">
                Добавить статус
            </button>
        @endcan
    </div>
</div>

<p class="text-muted small mt-2 mb-0">
    Системные статусы нельзя удалить и переименовать; цвет, иконка и порядок настраиваются.
    Признак «списывает занятие» задаёт, уменьшается ли остаток абонемента при выставлении этого статуса занятию (правило действует на момент отметки).
</p>

<hr>

<div class="table-responsive">
    <table class="table table-striped table-bordered align-middle w-100">
        <thead>
        <tr>
            <th style="width: 100px;">Порядок</th>
            <th>Название</th>
            <th style="width: 80px;">Иконка</th>
            <th style="width: 100px;">Тип</th>
            <th style="width: 110px;">Списывает</th>
            <th style="width: 90px;">Активен</th>
            @can('lessonPackages.view')
                <th style="width: 140px;">Действия</th>
            @endcan
        </tr>
        </thead>
        <tbody>
        @forelse ($occurrenceStatuses as $st)
            <tr>
                <td>{{ $st->sort_order }}</td>
                <td>
                    <span class="badge rounded-pill"
                          style="background-color: {{ $st->color }}; max-width: 100%; white-space: normal;">
                        {{ $st->title }}
                    </span>
                </td>
                <td class="text-center">
                    @if ($st->icon)
                        <i class="{{ $st->icon }} fa-lg" title="{{ $st->icon }}"></i>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $st->is_system ? 'Системный' : 'Свой' }}</td>
                <td class="text-center">{{ $st->consumes_lesson ? 'Да' : 'Нет' }}</td>
                <td class="text-center">{{ $st->is_active ? 'Да' : 'Нет' }}</td>
                @can('lessonPackages.view')
                    <td class="text-nowrap">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary los-edit-btn"
                                data-id="{{ $st->id }}"
                                data-system="{{ $st->is_system ? '1' : '0' }}"
                                data-title="{{ e($st->title) }}"
                                data-color="{{ $st->color }}"
                                data-icon="{{ e($st->icon ?? '') }}"
                                data-sort="{{ $st->sort_order }}"
                                data-active="{{ $st->is_active ? '1' : '0' }}"
                                data-consumes-lesson="{{ $st->consumes_lesson ? '1' : '0' }}">
                            Изменить
                        </button>
                        @if (! $st->is_system)
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger los-delete-btn"
                                    data-id="{{ $st->id }}"
                                    data-title="{{ e($st->title) }}">
                                Удалить
                            </button>
                        @endif
                    </td>
                @endcan
            </tr>
        @empty
            <tr>
                <td colspan="{{ $losColspan }}" class="text-center text-muted">Нет статусов</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@can('lessonPackages.view')
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
                            <input type="hidden" name="color" id="los-create-color" value="#6c757d">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <input type="color" id="los-create-color-picker"
                                       class="form-control form-control-color los-color-picker-input"
                                       value="#6c757d" title="Выберите цвет">
                                <span class="small text-muted font-monospace" id="los-create-color-hex">#6c757d</span>
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

@push('scripts')
    @can('lessonPackages.view')
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
            (function () {
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
                    const hidden = document.getElementById('los-' + scope + '-color');
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
                    setLosColor('create', '#6c757d');
                    setLosIconSelection('create', '');
                    const consumeCreate = document.getElementById('los-create-consumes');
                    if (consumeCreate) consumeCreate.checked = false;
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
                        window.location.reload();
                    }
                });

                document.querySelectorAll('.los-edit-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
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

                        const modal = new bootstrap.Modal(document.getElementById('losEditModal'));
                        modal.show();
                    });
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
                        window.location.reload();
                    }
                });

                document.querySelectorAll('.los-delete-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        if (!confirm('Удалить статус «' + btn.getAttribute('data-title') + '»?')) {
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
                            window.location.reload();
                        }
                    });
                });
            })();
        </script>
    @endcan
@endpush
