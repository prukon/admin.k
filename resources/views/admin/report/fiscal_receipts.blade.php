<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="text-start mb-0">Чеки</h4>

    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle"
                type="button"
                id="columnsDropdownFiscalReceipts"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Поля списка">
            <i class="fa-solid fa-table-columns"></i>
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

<form id="fiscal-receipts-filters" method="GET" action="/admin/reports/fiscal-receipts" class="mb-3 mt-3">
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
            <button class="btn btn-primary w-100" type="submit">Фильтр</button>
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-outline-secondary w-100" type="button" id="fiscalReceiptsResetBtn">Сброс</button>
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
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered" id="fiscal-receipts-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Partner</th>
            <th>PaymentIntent</th>
            <th>Payment</th>
            <th>Type</th>
            <th>Status</th>
            <th>Amount</th>
            <th>External ID</th>
            <th>Error</th>
            <th>Request Payload</th>
            <th>Response Payload</th>
            <th>Webhook Payload</th>
            <th>Created</th>
            <th>Queued</th>
            <th>Processed</th>
            <th>Failed</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#fiscal-receipts-filters');

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
                            '<i class="fa-solid fa-copy" aria-hidden="true"></i>' +
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
                            var formatted = formatAmount(data);
                            return formatted ? (formatted + ' руб') : '';
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
                    $btn.prop('disabled', true).html('<i class="fa-solid fa-check text-success" aria-hidden="true"></i>');
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
