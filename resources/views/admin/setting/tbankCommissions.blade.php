@php
    /** @var string $mode */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Partner[] $partners */
@endphp

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0"> </h1>

        @if(($mode ?? 'list') === 'list')
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button"
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#tbankPayoutSettingsModal">
                    Настройки выплат
                </button>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#tbankCommissionCreateModal">
                    Добавить комиссию
                </button>
            </div>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if(($mode ?? 'list') === 'edit')
        @php /** @var \App\Models\TinkoffCommissionRule $rule */ @endphp
        <h2 class="h6 mb-3">Правка правила #{{ $rule->id }}</h2>

        @php
            $rulePartnerId = (int) ($rule->partner_id ?? 0);
            $auto = !empty($autoPayoutByPartnerId) ? (bool) ($autoPayoutByPartnerId[$rulePartnerId] ?? false) : false;
            $conn = !empty($tbankConnectedByPartnerId) ? (bool) ($tbankConnectedByPartnerId[$rulePartnerId] ?? false) : false;
            $ruleStats = ($autoPayoutStatsByPartnerId ?? collect())->get($rulePartnerId);
        @endphp

        <form method="post" action="{{ route('admin.setting.tbankCommissions.update', ['id' => $rule->id]) }}">
            @csrf
            @method('put')

            <div class="card mb-3">
                <div class="card-body">
                    <div class="fw-semibold">Автовыплата партнёру после успешной оплаты</div>
                    <div class="text-muted small">
                        Настройка сохранится вместе с правилами по кнопке <b>Сохранить</b>.
                    </div>

                    @if($rulePartnerId <= 0)
                        <div class="text-warning small mt-2">
                            Для “глобального” правила partner_id не задан. Сначала выбери партнёра в правиле и сохрани — тогда появится чекбокс автовыплаты.
                        </div>
                    @else
                        @if(!$conn)
                            <div class="text-warning small mt-2">
                                T‑Bank у партнёра не считается “подключённым” (проверь ключи eacq/e2c в “Платёжных системах”).
                            </div>
                        @endif

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="autoPayoutEnabled"
                                   name="auto_payout_enabled"
                                   value="1"
                                   {{ $auto ? 'checked' : '' }}>
                            <label class="form-check-label" for="autoPayoutEnabled">Автовыплата включена</label>
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
        @vite(['resources/css/payments-report.css'])
        @php
            $tbankFilterPartnerId = old('filter_partner_id', request('filter_partner_id'));
            $tbankFilterMethod = old('filter_method', request('filter_method'));
            $tbankFiltersActive = ($tbankFilterPartnerId !== null && $tbankFilterPartnerId !== '')
                || ($tbankFilterMethod !== null && $tbankFilterMethod !== '');
        @endphp

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h2 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Правила комиссий и выплат</h2>
                    <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                        <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                            <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#tbankCommissionsFiltersCollapse"
                                    aria-expanded="{{ $tbankFiltersActive ? 'true' : 'false' }}"
                                    aria-controls="tbankCommissionsFiltersCollapse"
                                    id="tbankCommissionsFiltersToggle">
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
            <table class="table table-sm table-bordered align-middle w-100" id="tbank-commissions-table" style="width:100%">
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
                                <label for="payout_auto_delay_hours" class="form-label">Задержка автовыплаты после оплаты (часы)</label>
                                <input type="number" class="form-control" id="payout_auto_delay_hours" name="payout_auto_delay_hours"
                                       value="{{ old('payout_auto_delay_hours', $payoutAutoDelayHours ?? 48) }}" min="0" max="720" style="max-width: 8rem;">
                                <div class="form-text">0 = сразу, 48 = через 48 ч (окно возврата)</div>
                            </div>
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
            $(function () {
                var $form = $('#tbank-commissions-filters-form');
                var table = $('#tbank-commissions-table').DataTable({
                    processing: true,
                    serverSide: true,
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
                        {data: 'partner_cell', name: 'partner_title', orderable: true, searchable: true},
                        {data: 'method', name: 'method', orderable: true, searchable: true},
                        {data: 'acquiring_html', name: 'acquiring_percent', orderable: true, searchable: true},
                        {data: 'payout_html', name: 'payout_percent', orderable: true, searchable: true},
                        {data: 'platform_html', name: 'platform_percent', orderable: true, searchable: true},
                        {data: 'auto_payout_html', name: 'auto_payout', orderable: false, searchable: false, className: 'text-center'},
                        {data: 'payouts_30d_html', name: 'payouts_30d', orderable: false, searchable: false, className: 'text-center'},
                        {data: 'enabled_html', name: 'is_enabled', orderable: true, searchable: false, className: 'text-center'},
                        {data: 'actions_html', name: 'actions', orderable: false, searchable: false, className: 'text-start'}
                    ],
                    language: @include('partials.datatables.ru')
                });

                $form.on('submit', function (e) {
                    e.preventDefault();
                    table.ajax.reload();
                });

                $('#tbank-commissions-filters-reset').on('click', function () {
                    $form.find('[name="filter_partner_id"]').val('');
                    $form.find('[name="filter_method"]').val('');
                    table.ajax.reload();
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

