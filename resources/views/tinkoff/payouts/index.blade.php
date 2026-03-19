@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Выплаты T‑Bank</h4>
        <hr>

        <div class="alert alert-light border mb-3 py-2">
            <div class="small">
                <strong>Автовыплаты:</strong>
                @if($partnersWithAuto->isNotEmpty())
                    включены у партнёров
                    @foreach($partnersWithAuto as $pa)
                        <span class="badge text-bg-success">{{ $pa->title }}</span>
                    @endforeach
                @else
                    <span class="text-muted">ни у кого не включены</span>
                @endif
            </div>
            <div class="small text-muted mt-1">
                Обработка отложенных выплат: каждые {{ $scheduledIntervalMinutes }} мин.
            </div>
        </div>

        <div class="row gy-2 align-items-end">
            <div class="col-12 col-lg-8">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if(!empty($isSuperadmin))
                        <select id="filter-partner" class="form-select width-170">
                            <option value="">Все партнёры</option>
                            @foreach($partners as $p)
                                <option value="{{ $p->id }}">{{ $p->title }}</option>
                            @endforeach
                        </select>
                    @endif

                    <select id="filter-status" class="form-select width-170">
                        <option value="">Все статусы</option>
                        <option value="INITIATED">INITIATED</option>
                        <option value="NEW">NEW</option>
                        <option value="AUTHORIZING">AUTHORIZING</option>
                        <option value="CHECKING">CHECKING</option>
                        <option value="CREDIT_CHECKING">CREDIT_CHECKING</option>
                        <option value="CHECKED">CHECKED</option>
                        <option value="COMPLETING">COMPLETING</option>
                        <option value="COMPLETED">COMPLETED</option>
                        <option value="REJECTED">REJECTED</option>
                    </select>

                    <select id="filter-source" class="form-select width-170">
                        <option value="">Любой источник</option>
                        <option value="auto">auto</option>
                        <option value="manual">manual</option>
                        <option value="delayed">delayed</option>
                        <option value="scheduled">scheduled</option>
                    </select>

                    <input id="filter-payer" class="form-control width-170" type="text" placeholder="Плательщик: id/ФИО/тел/email">
                    <input id="filter-initiator" class="form-control width-170" type="text" placeholder="Инициатор: id/ФИО/тел/email">
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <input id="filter-created-from" class="form-control width-170" type="date" title="Создана с">
                    <input id="filter-created-to" class="form-control width-170" type="date" title="Создана по">

                    <input id="filter-run-from" class="form-control width-170" type="date" title="Запланирована с">
                    <input id="filter-run-to" class="form-control width-170" type="date" title="Запланирована по">

                    <input id="filter-completed-from" class="form-control width-170" type="date" title="Завершена с">
                    <input id="filter-completed-to" class="form-control width-170" type="date" title="Завершена по">
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <input id="filter-gross-min" class="form-control width-170" type="number" step="0.01" min="0" placeholder="Gross от (₽)">
                    <input id="filter-gross-max" class="form-control width-170" type="number" step="0.01" min="0" placeholder="Gross до (₽)">
                    <input id="filter-net-min" class="form-control width-170" type="number" step="0.01" min="0" placeholder="Net от (₽)">
                    <input id="filter-net-max" class="form-control width-170" type="number" step="0.01" min="0" placeholder="Net до (₽)">

                    <div class="form-check ms-1">
                        <input class="form-check-input" type="checkbox" value="1" id="filter-stuck-only">
                        <label class="form-check-label" for="filter-stuck-only">Застрявшие</label>
                    </div>
                    <input id="filter-stuck-minutes" class="form-control width-170" type="number" min="1" value="60" title="Сколько минут без обновления">
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <input id="filter-deal-id" class="form-control width-170" type="text" placeholder="DealId">
                    <input id="filter-tinkoff-payment-id" class="form-control width-170" type="number" min="1" placeholder="ID платежа (в системе)">
                    <input id="filter-payout-payment-id" class="form-control width-170" type="text" placeholder="T‑Bank payout PaymentId">

                    <button id="filter-apply" class="btn btn-primary">Найти</button>
                    <button id="filter-reset" class="btn btn-secondary">Сбросить</button>
                </div>
            </div>

            <div class="col-12 col-lg-4 text-lg-end">
                <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-secondary dropdown-toggle"
                            type="button"
                            id="columnsDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            title="Поля списка">
                        <i class="fa-solid fa-table-columns"></i> Колонки
                    </button>

                    <div class="dropdown-menu p-3" aria-labelledby="columnsDropdown" style="min-width: 260px; max-height: 60vh; overflow:auto;">
                        @php
                            $cols = [
                                'id' => 'ID',
                                'status' => 'Статус',
                                'source' => 'Источник',
                                'partner' => 'Партнёр',
                                'payer' => 'Плательщик',
                                'initiator' => 'Инициатор',
                                'payment' => 'Платёж',
                                'deal_id' => 'DealId',
                                'gross' => 'Gross',
                                'bank_accept_fee' => 'Комиссия банк (приём)',
                                'bank_payout_fee' => 'Комиссия банк (выплата)',
                                'platform_fee' => 'Комиссия платформа',
                                'net' => 'Net',
                                'when_to_run' => 'Запланирована',
                                'created_at' => 'Создана',
                                'completed_at' => 'Завершена',
                                'tinkoff_payout_payment_id' => 'T‑Bank payout PaymentId',
                                'actions' => 'Действия',
                            ];
                        @endphp

                        @foreach($cols as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input column-toggle"
                                       type="checkbox"
                                       data-column-key="{{ $key }}"
                                       id="col_{{ $key }}"
                                       checked>
                                <label class="form-check-label" for="col_{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="table-responsive">
            <table id="payouts-table" class="table table-striped table-bordered align-middle w-100">
                <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Статус</th>
                    <th>Источник</th>
                    <th>Партнёр</th>
                    <th>Плательщик</th>
                    <th>Инициатор</th>
                    <th>Платёж</th>
                    <th>DealId</th>
                    <th>Gross</th>
                    <th>Банк (приём)</th>
                    <th>Банк (выплата)</th>
                    <th>Платформа</th>
                    <th>Net</th>
                    <th>Запланирована</th>
                    <th>Создана</th>
                    <th>Завершена</th>
                    <th>T‑Bank payout PaymentId</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            const defaultColumnsVisibility = {
                id: true,
                status: true,
                source: true,
                partner: true,
                payer: true,
                initiator: true,
                payment: true,
                deal_id: true,
                gross: true,
                bank_accept_fee: true,
                bank_payout_fee: true,
                platform_fee: true,
                net: true,
                when_to_run: true,
                created_at: true,
                completed_at: true,
                tinkoff_payout_payment_id: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            // 0 – нумерация (всегда видна)
            const columnsMap = {
                id: 1,
                status: 2,
                source: 3,
                partner: 4,
                payer: 5,
                initiator: 6,
                payment: 7,
                deal_id: 8,
                gross: 9,
                bank_accept_fee: 10,
                bank_payout_fee: 11,
                platform_fee: 12,
                net: 13,
                when_to_run: 14,
                created_at: 15,
                completed_at: 16,
                tinkoff_payout_payment_id: 17,
                actions: 18
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

            // --- DataTables ---
            const table = $('#payouts-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 20,
                lengthMenu: [10, 20, 50, 100],
                order: [[1, 'desc']],
                ajax: {
                    url: '/admin/tinkoff/payouts/data',
                    type: 'GET',
                    data: function (d) {
                        @if(!empty($isSuperadmin))
                        d.partner_id = $('#filter-partner').val();
                        @endif
                        d.status = $('#filter-status').val();
                        d.source = $('#filter-source').val();

                        d.payer_query = $('#filter-payer').val();
                        d.initiator_query = $('#filter-initiator').val();

                        d.created_from = $('#filter-created-from').val();
                        d.created_to = $('#filter-created-to').val();
                        d.run_from = $('#filter-run-from').val();
                        d.run_to = $('#filter-run-to').val();
                        d.completed_from = $('#filter-completed-from').val();
                        d.completed_to = $('#filter-completed-to').val();

                        d.gross_min = $('#filter-gross-min').val();
                        d.gross_max = $('#filter-gross-max').val();
                        d.net_min = $('#filter-net-min').val();
                        d.net_max = $('#filter-net-max').val();

                        d.deal_id = $('#filter-deal-id').val();
                        d.tinkoff_payment_id = $('#filter-tinkoff-payment-id').val();
                        d.tinkoff_payout_payment_id = $('#filter-payout-payment-id').val();

                        d.stuck_only = $('#filter-stuck-only').is(':checked') ? 1 : 0;
                        d.stuck_minutes = $('#filter-stuck-minutes').val();
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
                    {data: 'id', name: 'id', className: 'text-center'},
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data) {
                            let cls = 'bg-secondary';
                            if (data === 'COMPLETED') cls = 'bg-success';
                            if (data === 'REJECTED') cls = 'bg-danger';
                            return '<span class="badge ' + cls + '">' + data + '</span>';
                        }
                    },
                    {
                        data: 'source',
                        name: 'source',
                        render: function (data) {
                            const label = data || '—';
                            return '<span class="badge bg-light text-dark border">' + label + '</span>';
                        }
                    },
                    {data: 'partner', name: 'partner', defaultContent: ''},
                    {data: 'payer', name: 'payer', defaultContent: ''},
                    {data: 'initiator', name: 'initiator', defaultContent: ''},
                    {
                        data: 'payment_id',
                        name: 'payment_id',
                        className: 'text-center',
                        render: function (data) {
                            if (!data) return '—';
                            return '<a href="/admin/tinkoff/payments/' + data + '" target="_blank">#' + data + '</a>';
                        }
                    },
                    {data: 'deal_id', name: 'deal_id', defaultContent: ''},
                    {data: 'gross', name: 'gross', className: 'text-end', defaultContent: ''},
                    {data: 'bank_accept_fee', name: 'bank_accept_fee', className: 'text-end', defaultContent: ''},
                    {data: 'bank_payout_fee', name: 'bank_payout_fee', className: 'text-end', defaultContent: ''},
                    {data: 'platform_fee', name: 'platform_fee', className: 'text-end', defaultContent: ''},
                    {data: 'net', name: 'net', className: 'text-end', defaultContent: ''},
                    {data: 'when_to_run', name: 'when_to_run', className: 'text-nowrap', defaultContent: ''},
                    {data: 'created_at', name: 'created_at', className: 'text-nowrap', defaultContent: ''},
                    {data: 'completed_at', name: 'completed_at', className: 'text-nowrap', defaultContent: ''},
                    {data: 'tinkoff_payout_payment_id', name: 'tinkoff_payout_payment_id', defaultContent: ''},
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end text-nowrap',
                        render: function (data, type, row) {
                            return '<a class="btn btn-sm btn-outline-primary" href="/admin/tinkoff/payouts/' + row.id + '">Карточка</a>';
                        }
                    }
                ]
            });

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);
                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    column.visible(isVisible);
                    $('.column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '/admin/tinkoff/payouts/columns-settings',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};
                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                response.hasOwnProperty(key) ? response[key] : defaultColumnsVisibility[key],
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

            function saveColumnsConfigToServer(config) {
                $.ajax({
                    url: '/admin/tinkoff/payouts/columns-settings',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: config
                    }
                });
            }

            loadColumnsConfigFromServer();

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const val = $(this).is(':checked');
                currentColumnsConfig[key] = val;
                applyVisibleColumns(currentColumnsConfig);
                saveColumnsConfigToServer(currentColumnsConfig);
            });

            $('#filter-apply').on('click', function () {
                table.ajax.reload();
            });

            $('#filter-reset').on('click', function () {
                @if(!empty($isSuperadmin))
                $('#filter-partner').val('');
                @endif
                $('#filter-status').val('');
                $('#filter-source').val('');
                $('#filter-payer').val('');
                $('#filter-initiator').val('');
                $('#filter-created-from').val('');
                $('#filter-created-to').val('');
                $('#filter-run-from').val('');
                $('#filter-run-to').val('');
                $('#filter-completed-from').val('');
                $('#filter-completed-to').val('');
                $('#filter-gross-min').val('');
                $('#filter-gross-max').val('');
                $('#filter-net-min').val('');
                $('#filter-net-max').val('');
                $('#filter-stuck-only').prop('checked', false);
                $('#filter-stuck-minutes').val('60');
                $('#filter-deal-id').val('');
                $('#filter-tinkoff-payment-id').val('');
                $('#filter-payout-payment-id').val('');
                table.ajax.reload();
            });
        });
    </script>
@endsection

