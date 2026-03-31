@php
    $frFilterKeys = ['id', 'payment_intent_id', 'payment_id', 'partner_id', 'type', 'status', 'external_id', 'created_from', 'created_to', 'processed_from', 'processed_to', 'failed_from', 'failed_to'];
    $frHasActiveFilters = false;
    foreach ($frFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $frHasActiveFilters = true;
            break;
        }
    }
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="text-start mb-0">Чеки</h4>

    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle"
                type="button"
                id="columnsDropdownFiscalReceipts"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Поля списка">
            <i class="fas fa-table-columns"></i>
        </button>

        <div class="dropdown-menu p-3"
             aria-labelledby="columnsDropdownFiscalReceipts"
             style="min-width: 260px;">
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="partner" id="frColPartner" checked>
                <label class="form-check-label" for="frColPartner">Партнер</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="payment_intent_id" id="frColIntent" checked>
                <label class="form-check-label" for="frColIntent">PaymentIntent</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="payment_id" id="frColPayment" checked>
                <label class="form-check-label" for="frColPayment">Payment</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="external_id" id="frColExternalId" checked>
                <label class="form-check-label" for="frColExternalId">External ID</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="error" id="frColError" checked>
                <label class="form-check-label" for="frColError">Ошибки</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="request_payload" id="frColRequest" checked>
                <label class="form-check-label" for="frColRequest">Request Payload</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="response_payload" id="frColResponse" checked>
                <label class="form-check-label" for="frColResponse">Response Payload</label>
            </div>
            <div class="form-check">
                <input class="form-check-input fiscal-receipts-column-toggle" type="checkbox" data-column-key="webhook_payload" id="frColWebhook" checked>
                <label class="form-check-label" for="frColWebhook">Webhook Payload</label>
            </div>
        </div>
    </div>
</div>

<div class="mb-2">
    <button class="fiscal-receipts-filters-toggle d-inline-flex align-items-center gap-2"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#fiscalReceiptsFiltersCollapse"
            aria-expanded="{{ $frHasActiveFilters ? 'true' : 'false' }}"
            aria-controls="fiscalReceiptsFiltersCollapse"
            id="fiscalReceiptsFiltersToggle">
        <span class="fiscal-receipts-filters-icon-wrap" aria-hidden="true">
            <i class="fas fa-sliders-h fiscal-receipts-filters-main-icon"></i>
        </span>
        <span class="fiscal-receipts-filters-label">Фильтры</span>
        <i class="fas {{ $frHasActiveFilters ? 'fa-chevron-up' : 'fa-chevron-down' }} fiscal-receipts-filters-chevron"
           aria-hidden="true"></i>
    </button>
</div>

<div class="collapse {{ $frHasActiveFilters ? 'show' : '' }} mb-3" id="fiscalReceiptsFiltersCollapse">
    <form id="fiscal-receipts-filters" method="GET" action="/admin/reports/fiscal-receipts" class="border rounded p-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label">ID</label>
                <input class="form-control" name="id" value="{{ $filters['id'] ?? '' }}" placeholder="1">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">PaymentIntent ID</label>
                <input class="form-control" name="payment_intent_id" value="{{ $filters['payment_intent_id'] ?? '' }}" placeholder="123">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Payment ID</label>
                <input class="form-control" name="payment_id" value="{{ $filters['payment_id'] ?? '' }}" placeholder="456">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Partner ID</label>
                <input class="form-control" name="partner_id" value="{{ $filters['partner_id'] ?? '' }}" placeholder="1">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Type</label>
                @php($type = $filters['type'] ?? '')
                <select class="form-select" name="type">
                    <option value="">—</option>
                    <option value="income" {{ $type === 'income' ? 'selected' : '' }}>income</option>
                    <option value="income_return" {{ $type === 'income_return' ? 'selected' : '' }}>income_return</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Status</label>
                @php($status = $filters['status'] ?? '')
                <select class="form-select" name="status">
                    <option value="">—</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>pending</option>
                    <option value="queued" {{ $status === 'queued' ? 'selected' : '' }}>queued</option>
                    <option value="processed" {{ $status === 'processed' ? 'selected' : '' }}>processed</option>
                    <option value="error" {{ $status === 'error' ? 'selected' : '' }}>error</option>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label">External ID</label>
                <input class="form-control" name="external_id" value="{{ $filters['external_id'] ?? '' }}" placeholder="CloudKassir Id">
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">Created from</label>
                <input class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Created to</label>
                <input class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Processed from</label>
                <input class="form-control" type="date" name="processed_from" value="{{ $filters['processed_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Processed to</label>
                <input class="form-control" type="date" name="processed_to" value="{{ $filters['processed_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Failed from</label>
                <input class="form-control" type="date" name="failed_from" value="{{ $filters['failed_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Failed to</label>
                <input class="form-control" type="date" name="failed_to" value="{{ $filters['failed_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-end gap-2 ms-md-auto">
                <button class="btn btn-primary" type="submit">Фильтр</button>
                <button class="btn btn-outline-secondary" type="button" id="fiscalReceiptsResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<style>
    .fiscal-receipts-filters-toggle {
        cursor: pointer;
        user-select: none;
        padding: 0.45rem 0.9rem 0.45rem 0.45rem;
        border-radius: 0.5rem;
        font-weight: 600;
        line-height: 1.25;
        color: var(--bs-body-color);
        background: linear-gradient(180deg, #fff 0%, var(--bs-light, #f8f9fa) 100%);
        border: 1px solid var(--bs-border-color, #dee2e6);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .fiscal-receipts-filters-toggle:hover {
        border-color: #b6d4fe;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        color: var(--bs-body-color);
        background: #fff;
    }

    .fiscal-receipts-filters-toggle:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .fiscal-receipts-filters-toggle[aria-expanded="true"] {
        border-color: #86b7fe;
        background: #f0f7ff;
    }

    .fiscal-receipts-filters-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.4rem;
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary, #0d6efd);
    }

    .fiscal-receipts-filters-main-icon {
        font-size: 0.95rem;
        line-height: 1;
    }

    .fiscal-receipts-filters-label {
        letter-spacing: 0.02em;
    }

    .fiscal-receipts-filters-chevron {
        font-size: 0.7rem;
        opacity: 0.65;
        margin-left: 0.15rem;
    }
</style>

<div class="table-responsive">
    <table class="table table-bordered" id="fiscal-receipts-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Партнер</th>
            <th>PaymentIntent</th>
            <th>Payment</th>
            <th>Тип</th>
            <th>Статус</th>
            <th>Сумма</th>
            <th>External ID</th>
            <th>Ошибка</th>
            <th>Request Payload</th>
            <th>Response Payload</th>
            <th>Webhook Payload</th>
            <th>Создано</th>
            <th>В очереди</th>
            <th>Обработано</th>
            <th>Время сбоя</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#fiscal-receipts-filters');
            var $frCollapse = $('#fiscalReceiptsFiltersCollapse');
            var $frToggle = $('#fiscalReceiptsFiltersToggle');
            var $frChevron = $frToggle.find('.fiscal-receipts-filters-chevron');

            $frCollapse.on('shown.bs.collapse', function () {
                $frToggle.attr('aria-expanded', 'true');
                $frChevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            });
            $frCollapse.on('hidden.bs.collapse', function () {
                $frToggle.attr('aria-expanded', 'false');
                $frChevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });

            var defaultColumnsVisibility = {
                partner: true,
                payment_intent_id: true,
                payment_id: true,
                external_id: true,
                error: true,
                request_payload: true,
                response_payload: true,
                webhook_payload: true
            };
            var currentColumnsConfig = {...defaultColumnsVisibility};
            var columnsMap = {
                partner: 1,
                payment_intent_id: 2,
                payment_id: 3,
                external_id: 7,
                error: 8,
                request_payload: 9,
                response_payload: 10,
                webhook_payload: 11
            };

            function dtLanguageRu() {
                return {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                };
            }

            function toBool(val, fallback) {
                if (val === undefined || val === null) return fallback;
                if (typeof val === 'boolean') return val;
                if (typeof val === 'number') return val === 1;
                if (typeof val === 'string') {
                    var v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }
                return fallback;
            }

            function formatAmount(number) {
                if (number === null || number === undefined || number === '') return '';
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function renderStatusBadge(status) {
                if (!status) return '';
                var s = String(status);
                if (s === 'pending') return '<span class="badge bg-warning text-dark">pending</span>';
                if (s === 'queued') return '<span class="badge bg-info text-dark">queued</span>';
                if (s === 'processed') return '<span class="badge bg-success">processed</span>';
                if (s === 'error') return '<span class="badge bg-danger">error</span>';
                return '<span class="badge bg-light text-dark">' + $('<div/>').text(s).html() + '</span>';
            }

            function renderPayloadCell(payloadText, title) {
                if (!payloadText) {
                    return '<span class="text-muted">—</span>';
                }

                var text = String(payloadText);
                var safe = $('<div/>').text(text).html();
                var short = safe.length > 180 ? (safe.slice(0, 180) + '...') : safe;

                return '' +
                    '<div class="d-flex align-items-start gap-1 fiscal-payload-cell">' +
                        '<span class="small flex-grow-1" style="min-width:0">' + short + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 js-copy-fiscal-payload" title="Копировать ' + title + '">' +
                            '<i class="fas fa-copy" aria-hidden="true"></i>' +
                            '<span class="visually-hidden">Копировать</span>' +
                        '</button>' +
                        '<span class="fiscal-payload-full d-none">' + safe + '</span>' +
                    '</div>';
            }

            var table = $('#fiscal-receipts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.fiscal-receipts.data') }}",
                    data: function (d) {
                        d.id = $form.find('[name="id"]').val();
                        d.payment_intent_id = $form.find('[name="payment_intent_id"]').val();
                        d.payment_id = $form.find('[name="payment_id"]').val();
                        d.partner_id = $form.find('[name="partner_id"]').val();
                        d.type = $form.find('[name="type"]').val();
                        d.status = $form.find('[name="status"]').val();
                        d.external_id = $form.find('[name="external_id"]').val();
                        d.created_from = $form.find('[name="created_from"]').val();
                        d.created_to = $form.find('[name="created_to"]').val();
                        d.processed_from = $form.find('[name="processed_from"]').val();
                        d.processed_to = $form.find('[name="processed_to"]').val();
                        d.failed_from = $form.find('[name="failed_from"]').val();
                        d.failed_to = $form.find('[name="failed_to"]').val();
                    }
                },
                columns: [
                    {data: 'id', name: 'id'},
                    {
                        data: null,
                        name: 'partner_id',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return row.partner_id || '';
                            }
                            var title = row.partner_title ? ('<div class="small text-muted">' + $('<div/>').text(row.partner_title).html() + '</div>') : '';
                            return (row.partner_id || '') + title;
                        }
                    },
                    {data: 'payment_intent_id', name: 'payment_intent_id'},
                    {data: 'payment_id', name: 'payment_id'},
                    {data: 'type', name: 'type'},
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderStatusBadge(data);
                        }
                    },
                    {
                        data: 'amount',
                        name: 'amount',
                        render: function (data, type, row) {
                            if (type !== 'display') return data;
                            if (data === null || data === undefined || data === '') return '';
                            var n = Math.round(parseFloat(data));
                            if (isNaN(n)) return '';
                            return formatAmount(n) + ' руб';
                        }
                    },
                    {data: 'external_id', name: 'external_id'},
                    {
                        data: null,
                        name: 'error_message',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var code = row.error_code !== null && row.error_code !== undefined ? String(row.error_code) : '';
                            var msg = row.error_message ? String(row.error_message) : '';
                            if (type !== 'display') {
                                return (code + ' ' + msg).trim();
                            }
                            if (!code && !msg) {
                                return '<span class="text-muted">—</span>';
                            }
                            var parts = [];
                            if (code) parts.push('<span class="badge bg-danger">code: ' + $('<div/>').text(code).html() + '</span>');
                            if (msg) parts.push('<div class="small mt-1">' + $('<div/>').text(msg).html() + '</div>');
                            return parts.join('');
                        }
                    },
                    {
                        data: 'request_payload',
                        name: 'request_payload',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderPayloadCell(data, 'Request');
                        }
                    },
                    {
                        data: 'response_payload',
                        name: 'response_payload',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderPayloadCell(data, 'Response');
                        }
                    },
                    {
                        data: 'webhook_payload',
                        name: 'webhook_payload',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderPayloadCell(data, 'Webhook');
                        }
                    },
                    {data: 'created_at', name: 'created_at'},
                    {data: 'queued_at', name: 'queued_at'},
                    {data: 'processed_at', name: 'processed_at'},
                    {data: 'failed_at', name: 'failed_at'}
                ],
                order: [[0, 'desc']],
                scrollX: true,
                fixedColumns: {leftColumns: 2},
                language: dtLanguageRu()
            });

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    var colIndex = columnsMap[key];
                    var isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    table.column(colIndex).visible(isVisible);
                    $('.fiscal-receipts-column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });
                table.columns.adjust();
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '/admin/reports/fiscal-receipts/columns-settings',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        var merged = {};
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

            loadColumnsConfigFromServer();

            $('.fiscal-receipts-column-toggle').on('change', function () {
                var key = $(this).data('column-key');
                var isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;
                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/admin/reports/fiscal-receipts/columns-settings',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    }
                });
            });

            $('#fiscal-receipts-table').on('click', '.js-copy-fiscal-payload', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $cell = $(this).closest('.fiscal-payload-cell');
                var full = $cell.find('.fiscal-payload-full').text();
                var $btn = $(this);
                var origHtml = $btn.html();

                function flashOk() {
                    $btn.prop('disabled', true).html('<i class="fas fa-check text-success" aria-hidden="true"></i>');
                    setTimeout(function () {
                        $btn.prop('disabled', false).html(origHtml);
                    }, 1500);
                }

                function copyViaTextarea() {
                    var ta = document.createElement('textarea');
                    ta.value = full;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy')) {
                            flashOk();
                        } else {
                            alert('Не удалось скопировать payload');
                        }
                    } catch (err) {
                        alert('Не удалось скопировать payload');
                    }
                    document.body.removeChild(ta);
                }

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(full).then(flashOk).catch(function () {
                        copyViaTextarea();
                    });
                } else {
                    copyViaTextarea();
                }
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                table.ajax.reload();
            });

            $('#fiscalReceiptsResetBtn').on('click', function () {
                $form[0].reset();
                table.ajax.reload();
            });
        });
    </script>
@endpush
