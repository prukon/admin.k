@php
    $piFilterKeys = ['inv_id', 'partner_title', 'user_name', 'provider', 'status', 'created_from', 'created_to', 'paid_from', 'paid_to'];
    $piHasActiveFilters = false;
    foreach ($piFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $piHasActiveFilters = true;
            break;
        }
    }
@endphp
<h4 class="pt-3 text-start">Платежные запросы</h4>

<div class="mb-2">
    <button class="payment-intents-filters-toggle d-inline-flex align-items-center gap-2"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#paymentIntentsFiltersCollapse"
            aria-expanded="{{ $piHasActiveFilters ? 'true' : 'false' }}"
            aria-controls="paymentIntentsFiltersCollapse"
            id="paymentIntentsFiltersToggle">
        <span class="payment-intents-filters-icon-wrap" aria-hidden="true">
            <i class="fas fa-sliders-h payment-intents-filters-main-icon"></i>
        </span>
        <span class="payment-intents-filters-label">Фильтры</span>
        <i class="fas {{ $piHasActiveFilters ? 'fa-chevron-up' : 'fa-chevron-down' }} payment-intents-filters-chevron"
           aria-hidden="true"></i>
    </button>
</div>

<div class="collapse {{ $piHasActiveFilters ? 'show' : '' }} mb-3" id="paymentIntentsFiltersCollapse">
    <form id="payment-intents-filters" method="GET" action="/admin/reports/payment-intents" class="border rounded p-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-inv-id">№</label>
                <input class="form-control" id="pi-filter-inv-id" name="inv_id" value="{{ $filters['inv_id'] ?? '' }}"
                       placeholder="123">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-partner">Партнер</label>
                <input class="form-control" id="pi-filter-partner" name="partner_title"
                       value="{{ $filters['partner_title'] ?? '' }}" placeholder="Фрагмент названия">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-user">Пользователь</label>
                <input class="form-control" id="pi-filter-user" name="user_name"
                       value="{{ $filters['user_name'] ?? '' }}" placeholder="Имя или фамилия">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-provider">Провайдер</label>
                <select class="form-select" id="pi-filter-provider" name="provider">
                    @php($provider = $filters['provider'] ?? '')
                    <option value="">—</option>
                    <option value="robokassa" {{ $provider === 'robokassa' ? 'selected' : '' }}>Robokassa</option>
                    <option value="tbank" {{ $provider === 'tbank' ? 'selected' : '' }}>T-Bank</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-status">Статус</label>
                @php($status = $filters['status'] ?? '')
                <select class="form-select" id="pi-filter-status" name="status">
                    <option value="">—</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>в ожидании</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>оплачен</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>ошибка</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>отменён</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-created-from">Создано: с</label>
                <input class="form-control" id="pi-filter-created-from" type="date" name="created_from"
                       value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-created-to">Создано: по</label>
                <input class="form-control" id="pi-filter-created-to" type="date" name="created_to"
                       value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-paid-from">Дата оплаты: с</label>
                <input class="form-control" id="pi-filter-paid-from" type="date" name="paid_from"
                       value="{{ $filters['paid_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="pi-filter-paid-to">Дата оплаты: по</label>
                <input class="form-control" id="pi-filter-paid-to" type="date" name="paid_to"
                       value="{{ $filters['paid_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-end gap-2 ms-md-auto">
                <button class="btn btn-primary" type="submit">Применить</button>
                <button class="btn btn-outline-secondary" type="button" id="paymentIntentsResetBtn">Сброс</button>
            </div>
        </div>
    </form>
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

    .payment-intents-filters-toggle {
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

    .payment-intents-filters-toggle:hover {
        border-color: #b6d4fe;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        color: var(--bs-body-color);
        background: #fff;
    }

    .payment-intents-filters-toggle:focus-visible {
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .payment-intents-filters-toggle[aria-expanded="true"] {
        border-color: #86b7fe;
        background: #f0f7ff;
    }

    .payment-intents-filters-icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.4rem;
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary, #0d6efd);
    }

    .payment-intents-filters-main-icon {
        font-size: 0.95rem;
        line-height: 1;
    }

    .payment-intents-filters-label {
        letter-spacing: 0.02em;
    }

    .payment-intents-filters-chevron {
        font-size: 0.7rem;
        opacity: 0.65;
        margin-left: 0.15rem;
    }

    #payment-intents-table th.payment-intent-meta-th,
    #payment-intents-table td.payment-intent-meta-td {
        width: 1%;
        max-width: 5.5rem;
        white-space: nowrap;
        vertical-align: middle;
    }

    .payment-intent-meta-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .payment-intent-meta-modal-pre {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.8125rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>

<div class="modal fade" id="paymentIntentMetaModal" tabindex="-1" aria-labelledby="paymentIntentMetaModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentIntentMetaModalLabel">Мета</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <pre class="payment-intent-meta-modal-pre mb-0"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary js-copy-payment-intent-meta-modal">
                    <i class="fas fa-copy me-1" aria-hidden="true"></i>Копировать
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered" id="payment-intents-table">
        <thead>
        <tr>
            <th>№</th>
            <th>ID провайдера</th>
            <th>Партнер</th>
            <th>Пользователь</th>
            <th>Провайдер</th>
            <th>Способ оплаты</th>
            <th>Статус</th>
            <th>Сумма платежа</th>
            <th>Оплаченный месяц</th>
            <th>Создано</th>
            <th>Дата оплаты</th>
            <th class="payment-intent-meta-th">Мета</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#payment-intents-filters');
            var $collapse = $('#paymentIntentsFiltersCollapse');
            var $toggle = $('#paymentIntentsFiltersToggle');
            var $chevron = $toggle.find('.payment-intents-filters-chevron');

            $collapse.on('shown.bs.collapse', function () {
                $toggle.attr('aria-expanded', 'true');
                $chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
            });
            $collapse.on('hidden.bs.collapse', function () {
                $toggle.attr('aria-expanded', 'false');
                $chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });

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

            function formatNumber(number) {
                if (number === null || number === undefined) return '';
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function formatMetaPretty(text) {
                var t = String(text).trim();
                if (!t) return '';
                try {
                    return JSON.stringify(JSON.parse(t), null, 2);
                } catch (e) {
                    return t;
                }
            }

            function renderPaymentMonth(data, type) {
                if (!data) return data;
                if (type !== 'display') return data;
                if (/\d{4}-\d{2}-\d{2}/.test(data)) {
                    var date = new Date(data);
                    var monthNames = [
                        "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                        "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
                    ];
                    var month = monthNames[date.getMonth()];
                    var year = date.getFullYear();
                    return month + ' ' + year;
                }
                return data;
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

            function renderStatusBadge(status) {
                if (!status) return '';
                var s = String(status);
                if (s === 'pending') return '<span class="badge bg-warning text-dark">в ожидании</span>';
                if (s === 'paid') return '<span class="badge bg-success">оплачен</span>';
                if (s === 'failed') return '<span class="badge bg-danger">ошибка</span>';
                if (s === 'cancelled') return '<span class="badge bg-secondary">отменён</span>';
                return '<span class="badge bg-light text-dark">' + $('<div/>').text(s).html() + '</span>';
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

            var table = $('#payment-intents-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.payment-intents.data') }}",
                    data: function (d) {
                        d.inv_id = $form.find('[name="inv_id"]').val();
                        d.partner_title = $form.find('[name="partner_title"]').val();
                        d.user_name = $form.find('[name="user_name"]').val();
                        d.provider = $form.find('[name="provider"]').val();
                        d.status = $form.find('[name="status"]').val();
                        d.created_from = $form.find('[name="created_from"]').val();
                        d.created_to = $form.find('[name="created_to"]').val();
                        d.paid_from = $form.find('[name="paid_from"]').val();
                        d.paid_to = $form.find('[name="paid_to"]').val();
                    }
                },
                columnDefs: [
                    {targets: -1, className: 'payment-intent-meta-td'}
                ],
                columns: [
                    {data: 'id', name: 'id'},
                    {data: 'provider_inv_id', name: 'provider_inv_id'},
                    {
                        data: null,
                        name: 'partner_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var title = row.partner_title ? ('<div class="small text-muted">' + row.partner_title + '</div>') : '';
                            return (row.partner_id ? row.partner_id : '') + title;
                        }
                    },
                    {
                        data: null,
                        name: 'user_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var name = row.user_name ? ('<div class="small text-muted">' + row.user_name + '</div>') : '';
                            return (row.user_id ? row.user_id : '') + name;
                        }
                    },
                    {
                        data: 'provider',
                        name: 'provider',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (data === 'tbank') {
                                return '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
                            }
                            if (data === 'robokassa') {
                                return '<span class="badge bg-secondary">Robokassa</span>';
                            }
                            return data ? $('<div/>').text(data).html() : '';
                        }
                    },
                    {
                        data: 'payment_method_webhook_label',
                        name: 'payment_method_webhook_label',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (!data) {
                                return '<span class="text-muted">—</span>';
                            }
                            return $('<div/>').text(data).html();
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data, type, row) {
                            if (type === 'display') return renderStatusBadge(data);
                            return data;
                        }
                    },
                    {
                        data: 'out_sum',
                        name: 'out_sum',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                if (data === null || data === undefined || data === '') return '';
                                var n = Math.round(parseFloat(data));
                                if (isNaN(n)) return '';
                                return formatNumber(n) + ' руб';
                            }
                            return data;
                        }
                    },
                    {
                        data: 'payment_date',
                        name: 'payment_date',
                        render: function (data, type, row) {
                            return renderPaymentMonth(data, type);
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
                        data: 'paid_at',
                        name: 'paid_at',
                        render: function (data, type, row) {
                            return renderDateTimeTwoLines(data, type);
                        }
                    },
                    {
                        data: 'meta',
                        name: 'meta',
                        orderable: false,
                        render: function (data, type, row) {
                            if (!data) return '';
                            var text = String(data);
                            if (type !== 'display') {
                                return text;
                            }
                            var safe = $('<div/>').text(text).html();
                            return (
                                '<div class="payment-intent-meta-actions payment-intent-meta-cell">' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary js-show-payment-intent-meta" title="Показать">' +
                                '<i class="fas fa-eye" aria-hidden="true"></i>' +
                                '<span class="visually-hidden">Показать</span>' +
                                '</button>' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary js-copy-payment-intent-meta" title="Копировать">' +
                                '<i class="fas fa-copy" aria-hidden="true"></i>' +
                                '<span class="visually-hidden">Копировать</span>' +
                                '</button>' +
                                '<span class="payment-intent-meta-full d-none">' + safe + '</span>' +
                                '</div>'
                            );
                        }
                    }
                ],
                order: [[0, 'desc']],
                scrollX: true,
                fixedColumns: {leftColumns: 2},
                language: dtLanguageRu()
            });

            var metaModalEl = document.getElementById('paymentIntentMetaModal');
            var metaModal = metaModalEl && typeof bootstrap !== 'undefined'
                ? bootstrap.Modal.getOrCreateInstance(metaModalEl)
                : null;

            $('#payment-intents-table').on('click', '.js-show-payment-intent-meta', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var full = $(this).closest('.payment-intent-meta-cell').find('.payment-intent-meta-full').text();
                var $pre = $('#paymentIntentMetaModal .payment-intent-meta-modal-pre');
                $pre.text(formatMetaPretty(full));
                if (metaModal) {
                    metaModal.show();
                }
            });

            $('#payment-intents-table').on('click', '.js-copy-payment-intent-meta', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var full = $(this).closest('.payment-intent-meta-cell').find('.payment-intent-meta-full').text();
                copyTextToClipboard(full, $(this));
            });

            $(document).on('click', '.js-copy-payment-intent-meta-modal', function (e) {
                e.preventDefault();
                var t = $('#paymentIntentMetaModal .payment-intent-meta-modal-pre').text();
                copyTextToClipboard(t, $(this));
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                table.ajax.reload();
            });

            $('#paymentIntentsResetBtn').on('click', function () {
                $form[0].reset();
                table.ajax.reload();
            });
        });
    </script>
@endpush
