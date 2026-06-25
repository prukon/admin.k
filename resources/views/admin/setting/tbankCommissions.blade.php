@php
    /** @var string $mode */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Partner[] $partners */
@endphp

<div class="container py-3">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if(($mode ?? 'list') === 'edit')
        @php /** @var \App\Models\TinkoffCommissionRule $rule */ @endphp
        <h2 class="h6 mb-3">Правка правила #{{ $rule->id }}</h2>

        @php
            $rulePartnerId = (int) ($rule->partner_id ?? 0);
            $ruleStats = ($autoPayoutStatsByPartnerId ?? collect())->get($rulePartnerId);
            $tbankGloballyConnected = (bool) ($tbankGloballyConnected ?? false);
        @endphp

        <form method="post" action="{{ route('admin.setting.tbankCommissions.update', ['id' => $rule->id]) }}">
            @csrf
            @method('put')

            <div class="card mb-3">
                <div class="card-body">
                    <div class="fw-semibold">Автовыплата партнёру после успешной оплаты</div>
                    <div class="text-muted small">
                        Настройка сохранится вместе с правилами по кнопке <b>Сохранить</b> (в разрезе партнёра и метода оплаты).
                    </div>

                    @if($rulePartnerId <= 0)
                        <div class="text-warning small mt-2">
                            Для «глобального» правила partner_id не задан. Сначала выбери партнёра в правиле и сохрани — тогда появятся настройки автовыплаты.
                        </div>
                    @else
                        @if(!$tbankGloballyConnected)
                            <div class="text-warning small mt-2">
                                T‑Bank на платформе не считается «подключённым» (проверь глобальные ключи eacq/e2c в «Платёжных системах»).
                            </div>
                        @endif

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="autoPayoutEnabled"
                                   name="auto_payout_enabled"
                                   value="1"
                                   {{ old('auto_payout_enabled', $rule->auto_payout_enabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="autoPayoutEnabled">Автовыплата включена</label>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="auto_payout_delay_hours" class="form-label">Задержка после оплаты (часы)</label>
                            <input type="number"
                                   class="form-control @error('auto_payout_delay_hours') is-invalid @enderror"
                                   id="auto_payout_delay_hours"
                                   name="auto_payout_delay_hours"
                                   min="0"
                                   max="720"
                                   style="max-width: 8rem;"
                                   value="{{ old('auto_payout_delay_hours', $rule->auto_payout_delay_hours) }}"
                                   required>
                            <div class="form-text">0 = сразу, 48 = через 48 ч (окно возврата)</div>
                            @error('auto_payout_delay_hours')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="small text-muted mt-2">
                            За 30 дн.: {{ $ruleStats['count'] ?? 0 }} автовыплат
                            @if(!empty($ruleStats['last_at']))
                                , последняя {{ $ruleStats['last_at']->format('d.m.Y H:i') }}
                            @endif
                            — <a href="{{ url('/admin/tinkoff/payouts?partner_id=' . $rulePartnerId . '&source=auto') }}" target="_blank">к выплатам (авто)</a>
                        </div>
                    @endif
                </div>
            </div>

            @include('tinkoff.commissions._form', ['rule' => $rule, 'partners' => $partners])
            <div class="mt-3">
                <button class="btn btn-primary">Сохранить</button>
                <a href="{{ route('admin.setting.tbankCommissions') }}" class="btn btn-link">Отмена</a>
            </div>
        </form>

    @else
        @vite(['resources/css/admin-list-toolbar.css'])
        @php
            $tbankFilterPartnerId = old('filter_partner_id', request('filter_partner_id'));
            $tbankFilterMethod = old('filter_method', request('filter_method'));
            $tbankFiltersActive = ($tbankFilterPartnerId !== null && $tbankFilterPartnerId !== '')
                || ($tbankFilterMethod !== null && $tbankFilterMethod !== '');
        @endphp

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
                            @include('tinkoff.commissions._form', ['rule' => null, 'partners' => $partners, 'compact' => true])
                            <div class="modal-footer-modal-user">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@if(($mode ?? 'list') === 'list')
    @push('scripts')
        <script type="text/javascript">
            function syncTbankCreateAutoPayoutFields() {
                var $createForm = $('#tbank-commission-create-form');
                if (!$createForm.length) {
                    return;
                }
                var partnerId = String($createForm.find('select[name=partner_id]').val() || '');
                var hasPartner = partnerId !== '' && parseInt(partnerId, 10) > 0;
                var $block = $('#tbank-auto-payout-create-block');
                var $delay = $('#tbank_create_auto_payout_delay_hours');
                if (hasPartner) {
                    $block.removeClass('d-none');
                    $delay.prop('required', true);
                } else {
                    $block.addClass('d-none');
                    $delay.prop('required', false);
                }
            }

            $(function () {
                $('#tbank-commission-create-form').on('change', 'select[name=partner_id]', syncTbankCreateAutoPayoutFields);
                $('#tbankCommissionCreateModal').on('shown.bs.modal', syncTbankCreateAutoPayoutFields);
                syncTbankCreateAutoPayoutFields();

                var $form = $('#tbank-commissions-filters-form');
                var tbankCommissionRoutes = {
                    edit: @json(route('admin.setting.tbankCommissions.edit', ['id' => '__ID__'])),
                    destroy: @json(route('admin.setting.tbankCommissions.destroy', ['id' => '__ID__'])),
                    csrf: @json(csrf_token())
                };

                function tbankCommissionUrl(template, id) {
                    return String(template).replace('__ID__', String(id));
                }

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

                    var html = '<div>' + window.KidsCrmTooltip.escapeHtml(value || '') + '</div>';
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
                    dataTable: {
                        pageLength: 20,
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
                            type: 'text',
                            data: 'partner_title',
                            name: 'partner_title',
                            className: 'dt-col-text',
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

                                var editUrl = tbankCommissionUrl(tbankCommissionRoutes.edit, row.id);
                                var destroyUrl = tbankCommissionUrl(tbankCommissionRoutes.destroy, row.id);

                                return ''
                                    + '<div class="text-start text-nowrap">'
                                    + '<a class="btn btn-outline-primary btn-sm" href="' + window.KidsCrmTooltip.escapeHtml(editUrl) + '">Изменить</a>'
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
            });

            (function () {
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
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', openModalsIfNeeded);
                } else {
                    openModalsIfNeeded();
                }
            })();
        </script>
    @endpush
@endif

