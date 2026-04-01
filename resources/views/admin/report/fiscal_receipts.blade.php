@vite(['resources/css/payments-report.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Чеки</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="fiscalReceiptsReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalPaidPrice ?? '0' }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#fiscalReceiptsFiltersCollapse"
                            aria-expanded="{{ $frHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="fiscalReceiptsFiltersCollapse"
                            id="fiscalReceiptsFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownFiscalReceipts"
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
                             aria-labelledby="columnsDropdownFiscalReceipts">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
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
            </div>
        </div>
    </div>
</div>

<style>
    .pay-cell-datetime {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        padding: 0.15rem 0;
        line-height: 1.25;
        min-width: 5.5rem;
    }

    .pay-cell-datetime__date {
        white-space: nowrap;
    }

    .pay-cell-datetime__time {
        font-size: 0.8125rem;
        font-variant-numeric: tabular-nums;
        color: var(--bs-secondary-color, #6c757d);
        white-space: nowrap;
    }

    #fiscal-receipts-table th.fiscal-payload-th,
    #fiscal-receipts-table td.fiscal-payload-td {
        width: 1%;
        max-width: 7rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .fiscal-payload-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .fiscal-payload-modal-pre {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.8125rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>

<div class="modal fade" id="fiscalPayloadModal" tabindex="-1" aria-labelledby="fiscalPayloadModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fiscalPayloadModalLabel">Payload</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <pre class="fiscal-payload-modal-pre mb-0"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary js-copy-fiscal-payload-modal">
                    <i class="fas fa-copy me-1" aria-hidden="true"></i>Копировать
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $frHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="fiscalReceiptsFiltersCollapse">
    <form id="fiscal-receipts-filters" method="GET" action="{{ route('reports.fiscal-receipts.index') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label">ID</label>
                <input class="form-control" name="id" value="{{ $filters['id'] ?? '' }}" placeholder="1">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">PaymentIntent</label>
                <input class="form-control" name="payment_intent_id" value="{{ $filters['payment_intent_id'] ?? '' }}" placeholder="123">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Payment</label>
                <input class="form-control" name="payment_id" value="{{ $filters['payment_id'] ?? '' }}" placeholder="456">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="fr-filter-partner">Партнер</label>
                <select class="form-select payments-report-filter-select2"
                        id="fr-filter-partner"
                        name="partner_id"
                        data-placeholder="Все партнеры"
                        data-search-url="{{ route('reports.fiscal-receipts.partners.search') }}">
                    <option value=""></option>
                    @if(!empty($frFilterPartner))
                        <option value="{{ $frFilterPartner['id'] }}" selected>{{ $frFilterPartner['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Тип</label>
                @php($type = $filters['type'] ?? '')
                <select class="form-select" name="type">
                    <option value="">—</option>
                    <option value="income" {{ $type === 'income' ? 'selected' : '' }}>income</option>
                    <option value="income_return" {{ $type === 'income_return' ? 'selected' : '' }}>income_return</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Статус</label>
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
                <label class="form-label">Создано: с</label>
                <input class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Создано: по</label>
                <input class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Обработано: с</label>
                <input class="form-control" type="date" name="processed_from" value="{{ $filters['processed_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Обработано: по</label>
                <input class="form-control" type="date" name="processed_to" value="{{ $filters['processed_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Время сбоя: с</label>
                <input class="form-control" type="date" name="failed_from" value="{{ $filters['failed_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label">Время сбоя: по</label>
                <input class="form-control" type="date" name="failed_to" value="{{ $filters['failed_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="fiscalReceiptsResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

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
            <th class="fiscal-payload-th">Request Payload</th>
            <th class="fiscal-payload-th">Response Payload</th>
            <th class="fiscal-payload-th">Webhook Payload</th>
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
            var $frFilterPartner = $('#fr-filter-partner');
            var $frTotalAmount = $('.payments-report-total-amount');
            var $frTotalStat = $('#fiscalReceiptsReportTotalStat');
            var $frTotalValueInner = $('.payments-report-total-value-inner');

            function initPaymentsReportFilterSelect2($el) {
                var searchUrl = $el.data('search-url');
                if (!$el.length || !searchUrl) {
                    return;
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    allowClear: true,
                    ajax: {
                        url: searchUrl,
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }

            initPaymentsReportFilterSelect2($frFilterPartner);

            function frParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function frFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function frAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $frTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : frParseTotalToInt(nextText);
                var prevVal = frParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($frTotalStat.length) {
                        $frTotalStat.removeClass('payments-report-total-stat--flash');
                        void $frTotalStat[0].offsetWidth;
                        $frTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($frTotalValueInner.length) {
                        $frTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $frTotalValueInner[0].offsetWidth;
                        $frTotalValueInner.addClass('payments-report-total-value-inner--pop');
                    }
                };

                var prefersReduced = window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (prefersReduced || prevText === nextText) {
                    $amount.text(nextText);
                    if (!prefersReduced && prevText !== nextText) {
                        runFlashAndPop();
                    }
                    return;
                }

                if (prevVal === nextVal) {
                    $amount.text(nextText);
                    runFlashAndPop();
                    return;
                }

                var duration = 480;
                var start = null;

                function easeInOutQuad(t) {
                    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                }

                function step(ts) {
                    if (start === null) {
                        start = ts;
                    }
                    var elapsed = ts - start;
                    var t = Math.min(1, elapsed / duration);
                    var eased = easeInOutQuad(t);
                    var cur = Math.round(prevVal + (nextVal - prevVal) * eased);
                    $amount.text(frFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function frFilterParams() {
                return {
                    id: $form.find('[name="id"]').val() || '',
                    payment_intent_id: $form.find('[name="payment_intent_id"]').val() || '',
                    payment_id: $form.find('[name="payment_id"]').val() || '',
                    partner_id: $form.find('[name="partner_id"]').val() || '',
                    type: $form.find('[name="type"]').val() || '',
                    status: $form.find('[name="status"]').val() || '',
                    external_id: $form.find('[name="external_id"]').val() || '',
                    created_from: $form.find('[name="created_from"]').val() || '',
                    created_to: $form.find('[name="created_to"]').val() || '',
                    processed_from: $form.find('[name="processed_from"]').val() || '',
                    processed_to: $form.find('[name="processed_to"]').val() || '',
                    failed_from: $form.find('[name="failed_from"]').val() || '',
                    failed_to: $form.find('[name="failed_to"]').val() || ''
                };
            }

            function refreshFiscalReceiptsTotal() {
                var prevText = $frTotalAmount.length ? $frTotalAmount.text() : '';
                if ($frTotalStat.length) {
                    $frTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.fiscal-receipts.total')), frFilterParams())
                    .done(function (res) {
                        if ($frTotalStat.length) {
                            $frTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$frTotalAmount.length) {
                            return;
                        }
                        frAnimateTotalChange(prevText, res.total_formatted, res.total_raw);
                    })
                    .fail(function () {
                        if ($frTotalStat.length) {
                            $frTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                    });
            }

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

            function formatPayloadPretty(text) {
                var t = String(text).trim();
                if (!t) return '';
                try {
                    return JSON.stringify(JSON.parse(t), null, 2);
                } catch (e) {
                    return t;
                }
            }

            function renderDateTimeTwoLines(data, type) {
                if (!data) return data;
                if (type !== 'display') return data;
                var date = new Date(data);
                if (isNaN(date.getTime())) return data;
                var day = ("0" + date.getDate()).slice(-2);
                var month = ("0" + (date.getMonth() + 1)).slice(-2);
                var year = date.getFullYear();
                var hours = ("0" + date.getHours()).slice(-2);
                var minutes = ("0" + date.getMinutes()).slice(-2);
                var seconds = ("0" + date.getSeconds()).slice(-2);
                var dateLine = day + '.' + month + '.' + year;
                var timeLine = hours + ':' + minutes + ':' + seconds;
                return (
                    '<div class="pay-cell-datetime" role="text" aria-label="' +
                    dateLine + ', ' + timeLine + '">' +
                    '<span class="pay-cell-datetime__date">' + dateLine + '</span>' +
                    '<span class="pay-cell-datetime__time">' + timeLine + '</span>' +
                    '</div>'
                );
            }

            function flashCopyButton($btn) {
                var origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-check text-success" aria-hidden="true"></i>');
                setTimeout(function () {
                    $btn.prop('disabled', false).html(origHtml);
                }, 1500);
            }

            function copyTextToClipboard(text, $btnForFlash) {
                function copyViaTextarea() {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy') && $btnForFlash) {
                            flashCopyButton($btnForFlash);
                        }
                    } catch (err) {
                        alert('Не удалось скопировать');
                    }
                    document.body.removeChild(ta);
                }

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text).then(function () {
                        if ($btnForFlash) flashCopyButton($btnForFlash);
                    }).catch(function () {
                        copyViaTextarea();
                    });
                } else {
                    copyViaTextarea();
                }
            }

            function renderPayloadActions(payloadText, title) {
                if (!payloadText) {
                    return '<span class="text-muted">—</span>';
                }

                var text = String(payloadText);
                var safe = $('<div/>').text(text).html();

                return (
                    '<div class="fiscal-payload-actions fiscal-payload-cell" data-title="' + $('<div/>').text(title).html() + '">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary js-show-fiscal-payload" title="Показать">' +
                    '<i class="fas fa-eye" aria-hidden="true"></i>' +
                    '<span class="visually-hidden">Показать</span>' +
                    '</button>' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary js-copy-fiscal-payload" title="Копировать">' +
                    '<i class="fas fa-copy" aria-hidden="true"></i>' +
                    '<span class="visually-hidden">Копировать</span>' +
                    '</button>' +
                    '<span class="fiscal-payload-full d-none">' + safe + '</span>' +
                    '</div>'
                );
            }

            var table = $('#fiscal-receipts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.fiscal-receipts.data') }}",
                    data: function (d) {
                        var extra = frFilterParams();
                        Object.keys(extra).forEach(function (key) {
                            d[key] = extra[key];
                        });
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
                            return renderPayloadActions(data, 'Request Payload');
                        }
                    },
                    {
                        data: 'response_payload',
                        name: 'response_payload',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderPayloadActions(data, 'Response Payload');
                        }
                    },
                    {
                        data: 'webhook_payload',
                        name: 'webhook_payload',
                        orderable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') return data || '';
                            return renderPayloadActions(data, 'Webhook Payload');
                        }
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        render: function (data, type, row) {
                            return renderDateTimeTwoLines(data, type);
                        }
                    },
                    {
                        data: 'queued_at',
                        name: 'queued_at',
                        render: function (data, type, row) {
                            return renderDateTimeTwoLines(data, type);
                        }
                    },
                    {data: 'processed_at', name: 'processed_at'},
                    {data: 'failed_at', name: 'failed_at'}
                ],
                order: [[0, 'desc']],
                scrollX: true,
                fixedColumns: {leftColumns: 2},
                language: dtLanguageRu()
            });

            table.on('draw', function () {
                $('#fiscal-receipts-table td:nth-child(10), ' +
                  '#fiscal-receipts-table td:nth-child(11), ' +
                  '#fiscal-receipts-table td:nth-child(12)').addClass('fiscal-payload-td');
            });

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    var colIndex = columnsMap[key];
                    var isVisible = toBool(config[key], defaultColumnsVisibility[key]);
                    table.column(colIndex).visible(isVisible, false);
                    $('.fiscal-receipts-column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
                });
                table.columns.adjust().draw(false);
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
                    },
                    success: function () {},
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            var payloadModalEl = document.getElementById('fiscalPayloadModal');
            var payloadModal = payloadModalEl && typeof bootstrap !== 'undefined'
                ? bootstrap.Modal.getOrCreateInstance(payloadModalEl)
                : null;

            $('#fiscal-receipts-table').on('click', '.js-show-fiscal-payload', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $cell = $(this).closest('.fiscal-payload-cell');
                var full = $cell.find('.fiscal-payload-full').text();
                var title = $cell.data('title') || 'Payload';
                $('#fiscalPayloadModalLabel').text(title);
                $('#fiscalPayloadModal .fiscal-payload-modal-pre').text(formatPayloadPretty(full));
                if (payloadModal) {
                    payloadModal.show();
                }
            });

            $('#fiscal-receipts-table').on('click', '.js-copy-fiscal-payload', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var full = $(this).closest('.fiscal-payload-cell').find('.fiscal-payload-full').text();
                copyTextToClipboard(full, $(this));
            });

            $(document).on('click', '.js-copy-fiscal-payload-modal', function (e) {
                e.preventDefault();
                var t = $('#fiscalPayloadModal .fiscal-payload-modal-pre').text();
                copyTextToClipboard(t, $(this));
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                refreshFiscalReceiptsTotal();
                table.ajax.reload();
            });

            $('#fiscalReceiptsResetBtn').on('click', function () {
                $form[0].reset();
                $frFilterPartner.val(null).trigger('change');
                refreshFiscalReceiptsTotal();
                table.ajax.reload();
            });
        });
    </script>
@endpush
