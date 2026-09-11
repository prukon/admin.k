@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Partner[] $partners */
    $tbankFilterPartnerId = old('filter_partner_id', request('filter_partner_id'));
    $tbankFilterMethod = old('filter_method', request('filter_method'));
    $tbankFiltersActive = ($tbankFilterPartnerId !== null && $tbankFilterPartnerId !== '')
        || ($tbankFilterMethod !== null && $tbankFilterMethod !== '');
    $tbankOldEditId = old('tbank_edit_id');
    $tbankEditFormAction = ($tbankOldEditId !== null && $tbankOldEditId !== '')
        ? route('admin.setting.tbankCommissions.update', ['id' => (int) $tbankOldEditId])
        : '#';
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="container py-3">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
        <div class="card-body px-3 py-3">
            <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Правила комиссий и выплат</h1>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                    <button type="button"
                            class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#tbankPayoutSettingsModal"
                            title="Глобальные настройки выплат Т‑Банк">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-gear payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Настройки выплат</span>
                    </button>

                    <button type="button"
                            class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#tbankCommissionCreateModal"
                            title="Новое правило комиссии">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-plus payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить комиссию</span>
                    </button>

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
                            data-bs-target="#tbankCommissionsFiltersCollapse"
                            aria-expanded="{{ $tbankFiltersActive ? 'true' : 'false' }}"
                            aria-controls="tbankCommissionsFiltersCollapse"
                            id="tbankCommissionsFiltersToggle"
                            title="Фильтры таблицы">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="tbankCommissionsColumnsDropdown"
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
                             aria-labelledby="tbankCommissionsColumnsDropdown">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="partner_title" id="colTbankPartner" checked>
                                <label class="form-check-label" for="colTbankPartner">Партнёр</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="method" id="colTbankMethod" checked>
                                <label class="form-check-label" for="colTbankMethod">Метод</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="acquiring_percent" id="colTbankAcquiring" checked>
                                <label class="form-check-label" for="colTbankAcquiring">Эквайринг банка</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="payout_percent" id="colTbankPayout" checked>
                                <label class="form-check-label" for="colTbankPayout">Выплата банка</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="platform_percent" id="colTbankPlatform" checked>
                                <label class="form-check-label" for="colTbankPlatform">Комиссия платформы</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="auto_payout" id="colTbankAutoPayout" checked>
                                <label class="form-check-label" for="colTbankAutoPayout">Автовыплата</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="payouts_30d" id="colTbankPayouts30d" checked>
                                <label class="form-check-label" for="colTbankPayouts30d">Выплат за 30 дн.</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="is_enabled" id="colTbankEnabled" checked>
                                <label class="form-check-label" for="colTbankEnabled">Активность</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input column-toggle" type="checkbox" data-column-key="actions" id="colTbankActions" checked>
                                <label class="form-check-label" for="colTbankActions">Действия</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="collapse {{ $tbankFiltersActive ? 'show' : '' }} mb-2 mb-md-3" id="tbankCommissionsFiltersCollapse">
        <form id="tbank-commissions-filters-form" method="get" action="{{ route('admin.setting.tbankCommissions') }}" class="border rounded p-2 p-md-3 bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="tbank-filter-partner">Партнёр</label>
                    <select class="form-select" id="tbank-filter-partner" name="filter_partner_id">
                        <option value="">Все</option>
                        @foreach(($partners ?? collect()) as $p)
                            <option value="{{ $p->id }}" @selected((string) $tbankFilterPartnerId === (string) $p->id)>{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="tbank-filter-method">Метод</label>
                    <select class="form-select" id="tbank-filter-method" name="filter_method">
                        <option value="">Все</option>
                        <option value="card" @selected($tbankFilterMethod === 'card')>card</option>
                        <option value="sbp" @selected($tbankFilterMethod === 'sbp')>sbp</option>
                        <option value="tpay" @selected($tbankFilterMethod === 'tpay')>tpay</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                    <button class="btn btn-primary payments-report-filters-submit" type="submit" id="tbank-commissions-filters-apply">Применить</button>
                    <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="tbank-commissions-filters-reset">Сброс</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle dt-columns-managed w-100" id="tbank-commissions-table">
            <thead class="table-light">
            <tr>
                <th data-priority="1">№</th>
                <th>Партнёр</th>
                <th>Метод</th>
                <th>Эквайринг банка</th>
                <th>Выплата банка</th>
                <th>Комиссия платформы</th>
                <th>Автовыплата</th>
                <th>Выплат за 30 дн.</th>
                <th>Активность</th>
                <th data-orderable="false">Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="modal fade" id="tbankPayoutSettingsModal" tabindex="-1" aria-labelledby="tbankPayoutSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tbankPayoutSettingsModalLabel">Глобальные настройки выплат Т‑Банк</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{ route('admin.setting.tbankCommissions.payoutSettings') }}" id="tbank-payout-settings-form" class="text-start">
                        @csrf
                        <input type="hidden" name="tbank_payout_settings_form" value="1">
                        <div class="mb-3">
                            <label for="payout_scheduled_interval_minutes" class="form-label">Интервал запуска джобы (мин)</label>
                            <input type="number" class="form-control" id="payout_scheduled_interval_minutes" name="payout_scheduled_interval_minutes"
                                   value="{{ old('payout_scheduled_interval_minutes', $payoutScheduledIntervalMinutes ?? 10) }}" min="1" max="1440" style="max-width: 8rem;">
                            <div class="form-text">Как часто обрабатываются отложенные выплаты</div>
                        </div>
                        <div class="form-text text-muted mb-3">Изменение интервала джобы применится после перезапуска планировщика (cron/queue).</div>
                        <div class="modal-footer-modal-user">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Как модалка «Создание пользователя» (includes/modal/createUser): modal-dialog без lg, поля внутри modal-body --}}
    <div class="modal fade" id="tbankCommissionCreateModal" tabindex="-1" aria-labelledby="tbankCommissionCreateModalLabel" aria-hidden="true">
        {{-- Без modal-dialog-scrollable: иначе .modal-content получает max-height ~100vh и снизу пустота --}}
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tbankCommissionCreateModalLabel">Новое правило комиссии</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{ route('admin.setting.tbankCommissions.store') }}" id="tbank-commission-create-form" class="text-start">
                        @csrf
                        <input type="hidden" name="tbank_create_form" value="1">
                        @include('tinkoff.commissions._form', [
                            'rule' => null,
                            'partners' => $partners,
                            'compact' => true,
                            'idPrefix' => 'tbank_create',
                            'autoPayoutBlockId' => 'tbank-auto-payout-create-block',
                            'delayHoursId' => 'tbank_create_auto_payout_delay_hours',
                            'enabledId' => 'tbank_create_auto_payout_enabled',
                            'partnerSelectId' => 'tbank-commission-partner-id',
                        ])
                        <div class="modal-footer-modal-user">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Чуть шире стандартных 500px; без modal-lg — в проекте это 90% экрана --}}
    <style>
        #tbankCommissionEditModal .modal-dialog {
            max-width: min(720px, 96vw);
        }
    </style>
    <div class="modal fade" id="tbankCommissionEditModal" tabindex="-1" aria-labelledby="tbankCommissionEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tbankCommissionEditModalLabel">Редактирование правила комиссии</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{ $tbankEditFormAction }}" id="tbank-commission-edit-form" class="text-start">
                        @csrf
                        @method('put')
                        <input type="hidden" name="tbank_edit_form" value="1">
                        <input type="hidden" name="tbank_edit_id" id="tbank_edit_id" value="{{ $tbankOldEditId }}">
                        @include('tinkoff.commissions._form', [
                            'rule' => null,
                            'partners' => $partners,
                            'compact' => true,
                            'lockScope' => true,
                            'idPrefix' => 'tbank_edit',
                            'showPayoutStats' => true,
                        ])
                        <div class="modal-footer-modal-user">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.logModal')

@push('scripts')
    <script type="text/javascript">
        function syncTbankAutoPayoutFields($form) {
            if (!$form || !$form.length) {
                return;
            }
            var partnerId = String($form.find('[name=partner_id]').val() || '');
            var hasPartner = partnerId !== '' && parseInt(partnerId, 10) > 0;
            var $block = $form.find('.tbank-auto-payout-block');
            var $delay = $form.find('[name=auto_payout_delay_hours]');
            if (hasPartner) {
                $block.removeClass('d-none');
                $delay.prop('required', true);
            } else {
                $block.addClass('d-none');
                $delay.prop('required', false);
            }
        }

        function syncTbankCreateAutoPayoutFields() {
            syncTbankAutoPayoutFields($('#tbank-commission-create-form'));
        }

        $(function () {
            $('#tbank-commission-create-form').on('change', 'select[name=partner_id]', function () {
                syncTbankAutoPayoutFields($('#tbank-commission-create-form'));
            });
            $('#tbankCommissionCreateModal').on('shown.bs.modal', syncTbankCreateAutoPayoutFields);
            syncTbankCreateAutoPayoutFields();

            var $form = $('#tbank-commissions-filters-form');
            var $editForm = $('#tbank-commission-edit-form');
            var editFormEl = document.getElementById('tbank-commission-edit-form');
            var editModalEl = document.getElementById('tbankCommissionEditModal');
            var tbankCommissionRoutes = {
                edit: @json(route('admin.setting.tbankCommissions.edit', ['id' => '__ID__'])),
                update: @json(route('admin.setting.tbankCommissions.update', ['id' => '__ID__'])),
                destroy: @json(route('admin.setting.tbankCommissions.destroy', ['id' => '__ID__'])),
                csrf: @json(csrf_token())
            };

            function tbankCommissionUrl(template, id) {
                return String(template).replace('__ID__', String(id));
            }

            function clearTbankFormErrors(form) {
                if (!form) {
                    return;
                }
                form.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
                form.querySelectorAll('[data-error-for]').forEach(function (el) {
                    el.textContent = '';
                });
            }

            function applyTbankFormErrors(form, errors) {
                Object.keys(errors || {}).forEach(function (key) {
                    var messages = errors[key];
                    var message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    var input = form.querySelector('[name="' + key + '"]');
                    var err = form.querySelector('[data-error-for="' + key + '"]');
                    if (input) {
                        input.classList.add('is-invalid');
                    }
                    if (err) {
                        err.textContent = message;
                    }
                });
            }

            function setTbankEditAction(id) {
                $editForm.attr('action', tbankCommissionUrl(tbankCommissionRoutes.update, id));
                $editForm.find('[name=tbank_edit_id]').val(String(id));
            }

            function fillTbankEditForm(data) {
                var partnerId = data.partner_id ? String(data.partner_id) : '';
                var methodLabel = data.method_label;
                if (!methodLabel) {
                    if (data.method === 'card') {
                        methodLabel = 'Карты';
                    } else if (data.method === 'sbp') {
                        methodLabel = 'СБП';
                    } else if (data.method === 'tpay') {
                        methodLabel = 'T‑Pay';
                    } else {
                        methodLabel = '— Для всех —';
                    }
                }
                $editForm.find('[name=partner_id]').val(partnerId);
                $editForm.find('#tbank_edit_partner_title').text(data.partner_title || '— Глобально —');
                $editForm.find('[name=method]').val(data.method || '');
                $editForm.find('#tbank_edit_method_label').text(methodLabel);
                $editForm.find('[name=acquiring_percent]').val(data.acquiring_percent);
                $editForm.find('[name=acquiring_min_fixed]').val(data.acquiring_min_fixed);
                $editForm.find('[name=payout_percent]').val(data.payout_percent);
                $editForm.find('[name=payout_min_fixed]').val(data.payout_min_fixed);
                $editForm.find('[name=platform_percent]').val(data.platform_percent);
                $editForm.find('[name=platform_min_fixed]').val(data.platform_min_fixed);
                $editForm.find('[name=is_enabled]').prop('checked', !!data.is_enabled);
                $editForm.find('[name=auto_payout_enabled]').prop('checked', !!data.auto_payout_enabled);
                $editForm.find('[name=auto_payout_delay_hours]').val(data.auto_payout_delay_hours != null ? data.auto_payout_delay_hours : 0);

                var $keysWarning = $('#tbank-edit-keys-warning');
                if (partnerId && data.tbank_globally_connected === false) {
                    $keysWarning.removeClass('d-none');
                } else {
                    $keysWarning.addClass('d-none');
                }

                var $count = $('#tbank-edit-payouts-count');
                var $lastWrap = $('#tbank-edit-payouts-last-wrap');
                var $last = $('#tbank-edit-payouts-last');
                var $link = $('#tbank-edit-payouts-link');
                $count.text(data.payouts_30d_count != null ? String(data.payouts_30d_count) : '0');
                if (data.payouts_30d_last_at) {
                    $last.text(data.payouts_30d_last_at);
                    $lastWrap.removeClass('d-none');
                } else {
                    $last.text('');
                    $lastWrap.addClass('d-none');
                }
                if (data.payouts_30d_url) {
                    $link.attr('href', data.payouts_30d_url).removeClass('d-none');
                } else {
                    $link.attr('href', '#').addClass('d-none');
                }

                syncTbankAutoPayoutFields($editForm);
            }

            function openTbankCommissionEditModal() {
                if (typeof bootstrap === 'undefined' || !editModalEl) {
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                syncTbankAutoPayoutFields($editForm);
            }

            function openTbankCommissionEdit(id) {
                clearTbankFormErrors(editFormEl);
                setTbankEditAction(id);
                return fetch(tbankCommissionUrl(tbankCommissionRoutes.edit, id), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).then(function (res) {
                    if (!res.ok) {
                        return null;
                    }
                    return res.json();
                }).then(function (data) {
                    if (!data) {
                        return;
                    }
                    fillTbankEditForm(data);
                    openTbankCommissionEditModal();
                });
            }

            $('#tbankCommissionEditModal').on('shown.bs.modal', function () {
                syncTbankAutoPayoutFields($editForm);
            });

            $(document).on('click', '.js-tbank-commission-edit', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                if (!id) {
                    return;
                }
                openTbankCommissionEdit(id);
            });

            $editForm.on('submit', function (e) {
                e.preventDefault();
                clearTbankFormErrors(editFormEl);
                var id = $editForm.find('[name=tbank_edit_id]').val();
                if (!id) {
                    return;
                }
                var fd = new FormData(editFormEl);
                fetch(tbankCommissionUrl(tbankCommissionRoutes.update, id), {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tbankCommissionRoutes.csrf
                    }
                }).then(function (res) {
                    return res.json().catch(function () {
                        return {};
                    }).then(function (data) {
                        return {ok: res.ok, status: res.status, data: data};
                    });
                }).then(function (result) {
                    if (!result.ok && result.status === 422) {
                        applyTbankFormErrors(editFormEl, result.data.errors || {});
                        return;
                    }
                    if (result.ok) {
                        if (typeof bootstrap !== 'undefined' && editModalEl) {
                            var modal = bootstrap.Modal.getInstance(editModalEl);
                            if (modal) {
                                modal.hide();
                            }
                        }
                        dtApi.reload({keepPage: true});
                    }
                });
            });

            function renderTbankCommissionPercentCell(percentKey, minKey) {
                return function (value, type, row) {
                    if (type === 'sort' || type === 'filter') {
                        return row[percentKey] != null ? row[percentKey] : '';
                    }
                    if (type !== 'display') {
                        return value != null ? value : '';
                    }

                    var percent = Number(row[percentKey] || 0);
                    var minFixed = Number(row[minKey] || 0);
                    var percentText = percent.toLocaleString('ru-RU', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    var minText = minFixed.toLocaleString('ru-RU', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    return '<div>' + percentText + '%</div>'
                        + '<div class="text-muted small">мин ' + minText + ' ₽</div>';
                };
            }

            function renderTbankPartnerCell(value, type, row) {
                if (type !== 'display') {
                    return value || '';
                }

                var link = window.KidsCrmTooltip.renderLink(value, {
                    linkClass: 'js-tbank-commission-edit',
                    extraAttrs: 'data-id="' + String(row.id) + '"'
                });
                var html = '<div>' + link + '</div>';
                if (row.partner_id && row.tbank_keys_connected === false) {
                    html += '<div class="small mt-1"><span class="badge text-bg-warning">ключи?</span></div>';
                }

                return html;
            }

            function renderTbankOptionalBadge(label, enabledKey, successClass, secondaryClass) {
                return function (value, type, row) {
                    if (row[enabledKey] === null || row[enabledKey] === undefined) {
                        if (type === 'display') {
                            return '<span class="dt-cell-empty text-muted">—</span>';
                        }
                        return '';
                    }

                    if (type !== 'display') {
                        return value || '';
                    }

                    var badgeClass = row[enabledKey] ? successClass : secondaryClass;
                    return '<span class="badge ' + badgeClass + '">' + window.KidsCrmTooltip.escapeHtml(value || '') + '</span>';
                };
            }

            function renderTbankPayouts30d(value, type, row) {
                if (row.payouts_30d_count === null || row.payouts_30d_count === undefined) {
                    if (type === 'display') {
                        return '<span class="dt-cell-empty text-muted">—</span>';
                    }
                    return '';
                }

                if (type !== 'display') {
                    return row.payouts_30d_count;
                }

                var url = row.payouts_30d_url || '';
                var count = String(row.payouts_30d_count);
                if (!url) {
                    return count;
                }

                return '<a href="' + window.KidsCrmTooltip.escapeHtml(url) + '"'
                    + ' class="link-primary fw-semibold" target="_blank"'
                    + ' title="Выплаты (авто) за 30 дней">' + count + '</a>';
            }

            var dtApi = KidsCrmDataTable.create('#tbank-commissions-table', {
                columnsSettings: {
                    persistPageLength: true,
                    defaults: {
                        partner_title: true,
                        method: true,
                        acquiring_percent: true,
                        payout_percent: true,
                        platform_percent: true,
                        auto_payout: true,
                        payouts_30d: true,
                        is_enabled: true,
                        actions: true,
                    },
                    urls: {
                        get: @json(route('admin.setting.tbankCommissions.columns-settings.get')),
                        save: @json(route('admin.setting.tbankCommissions.columns-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    pageLength: @json((int) ($tbankCommissionsPageLength ?? 10)),
                    lengthMenu: [10, 20, 50, 100],
                    order: [[1, 'asc']],
                    searching: true,
                    ajax: {
                        url: @json(route('admin.setting.tbankCommissions.data')),
                        type: 'GET',
                        data: function (d) {
                            d.filter_partner_id = $form.find('[name="filter_partner_id"]').val() || '';
                            d.filter_method = $form.find('[name="filter_method"]').val() || '';
                        }
                    },
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { type: 'rownum' },
                    {
                        key: 'partner_title',
                        type: 'link',
                        data: 'partner_title',
                        name: 'partner_title',
                        className: 'dt-col-text',
                        linkClass: 'js-tbank-commission-edit',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '"';
                        },
                        render: renderTbankPartnerCell,
                    },
                    { key: 'method', type: 'text', data: 'method', name: 'method' },
                    {
                        key: 'acquiring_percent',
                        type: 'text',
                        data: 'acquiring_percent',
                        name: 'acquiring_percent',
                        className: 'dt-col-text',
                        render: renderTbankCommissionPercentCell('acquiring_percent', 'acquiring_min_fixed'),
                    },
                    {
                        key: 'payout_percent',
                        type: 'text',
                        data: 'payout_percent',
                        name: 'payout_percent',
                        className: 'dt-col-text',
                        render: renderTbankCommissionPercentCell('payout_percent', 'payout_min_fixed'),
                    },
                    {
                        key: 'platform_percent',
                        type: 'text',
                        data: 'platform_percent',
                        name: 'platform_percent',
                        className: 'dt-col-text',
                        render: renderTbankCommissionPercentCell('platform_percent', 'platform_min_fixed'),
                    },
                    {
                        key: 'auto_payout',
                        type: 'badge',
                        data: 'auto_payout_label',
                        name: 'auto_payout',
                        className: 'dt-col-badge text-center',
                        orderable: false,
                        searchable: false,
                        render: renderTbankOptionalBadge(
                            'auto_payout_label',
                            'auto_payout_enabled',
                            'text-bg-success',
                            'text-bg-secondary'
                        ),
                    },
                    {
                        key: 'payouts_30d',
                        type: 'text',
                        data: 'payouts_30d_count',
                        name: 'payouts_30d',
                        className: 'dt-col-count text-center',
                        orderable: false,
                        searchable: false,
                        render: renderTbankPayouts30d,
                    },
                    {
                        key: 'is_enabled',
                        type: 'badge',
                        data: 'enabled_label',
                        name: 'is_enabled',
                        className: 'dt-col-badge text-center',
                        badgeKey: 'is_enabled',
                        searchable: false,
                        render: function (value, type, row) {
                            if (type !== 'display') {
                                return value || '';
                            }
                            var badgeClass = row.is_enabled ? 'text-bg-success' : 'text-bg-secondary';
                            return '<span class="badge ' + badgeClass + '">'
                                + window.KidsCrmTooltip.escapeHtml(value || '') + '</span>';
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        className: 'dt-col-actions text-start',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return '';
                            }

                            var destroyUrl = tbankCommissionUrl(tbankCommissionRoutes.destroy, row.id);

                            return ''
                                + '<div class="text-start text-nowrap">'
                                + '<button type="button" class="btn btn-outline-primary btn-sm js-tbank-commission-edit" data-id="'
                                + window.KidsCrmTooltip.escapeHtml(String(row.id))
                                + '">Изменить</button>'
                                + '<form action="' + window.KidsCrmTooltip.escapeHtml(destroyUrl) + '" method="post" class="d-inline-block ms-1" onsubmit="return confirm(\'Удалить правило?\');">'
                                + '<input type="hidden" name="_token" value="' + window.KidsCrmTooltip.escapeHtml(tbankCommissionRoutes.csrf) + '">'
                                + '<input type="hidden" name="_method" value="DELETE">'
                                + '<button type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>'
                                + '</form>'
                                + '</div>';
                        },
                    },
                ]
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                dtApi.reload({ keepPage: true });
            });

            $('#tbank-commissions-filters-reset').on('click', function () {
                $form.find('[name="filter_partner_id"]').val('');
                $form.find('[name="filter_method"]').val('');
                dtApi.reload();
            });

            function openModalsIfNeeded() {
                if (typeof bootstrap === 'undefined') {
                    return;
                }
                var fromPayoutForm = @json((bool) old('tbank_payout_settings_form'));
                if (fromPayoutForm) {
                    var payoutEl = document.getElementById('tbankPayoutSettingsModal');
                    if (payoutEl) {
                        bootstrap.Modal.getOrCreateInstance(payoutEl).show();
                    }
                }
                var fromCreate = @json((bool) old('tbank_create_form'));
                var fromCreateRoute = @json((bool) request('open_create'));
                if (fromCreate || fromCreateRoute) {
                    var createEl = document.getElementById('tbankCommissionCreateModal');
                    if (createEl) {
                        bootstrap.Modal.getOrCreateInstance(createEl).show();
                        syncTbankCreateAutoPayoutFields();
                    }
                }
                var fromEditForm = @json((bool) old('tbank_edit_form'));
                var fromEditRoute = @json(request('edit'));
                if (fromEditForm) {
                    openTbankCommissionEditModal();
                } else if (fromEditRoute) {
                    var editId = parseInt(fromEditRoute, 10);
                    if (editId > 0) {
                        openTbankCommissionEdit(editId);
                    }
                }
            }

            openModalsIfNeeded();

            showLogModal(@json(route('logs.data.tbank-commission')));
        });
    </script>
@endpush
