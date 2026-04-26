@vite(['resources/css/payments-report.css'])

@php
    $et = $emailsToolbar ?? [];
    $etTotal  = $et['total_formatted']  ?? '0';
    $etSent   = $et['sent_formatted']   ?? '0';
    $etFailed = $et['failed_formatted'] ?? '0';
    $emailsFilterMailable = $emailsFilterMailable ?? null;
    $emailsHasActiveFilters = $emailsHasActiveFilters ?? false;

    $emFilterStatusRaw = $filters['status'] ?? [];
    if (is_string($emFilterStatusRaw)) {
        $emFilterStatusRaw = $emFilterStatusRaw !== '' ? [$emFilterStatusRaw] : [];
    }
    $emFilterStatus = is_array($emFilterStatusRaw) ? array_values(array_filter($emFilterStatusRaw, fn($v) => is_string($v) && $v !== '')) : [];
@endphp

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Исходящие письма</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 gap-md-4" id="emailsReportToolbarTotals">
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="emailsReportTotalStat">
                        <div class="payments-report-total-label text-muted small mb-0">Всего</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount">{{ $etTotal }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="emailsReportSentStat">
                        <div class="payments-report-total-label text-muted small mb-0">Отправлено</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount">{{ $etSent }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="emailsReportFailedStat">
                        <div class="payments-report-total-label text-muted small mb-0">Ошибки</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount">{{ $etFailed }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#emailsReportFiltersCollapse"
                            aria-expanded="{{ $emailsHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="emailsReportFiltersCollapse"
                            id="emailsReportFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownEmails"
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
                             aria-labelledby="columnsDropdownEmails">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="id" id="emColId" checked><label class="form-check-label" for="emColId">ID</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="created_at" id="emColCreatedAt" checked><label class="form-check-label" for="emColCreatedAt">Создано</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="sent_at" id="emColSentAt" checked><label class="form-check-label" for="emColSentAt">Отправлено</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="status" id="emColStatus" checked><label class="form-check-label" for="emColStatus">Статус</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="from_address" id="emColFrom" checked><label class="form-check-label" for="emColFrom">От кого</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="to_summary" id="emColTo" checked><label class="form-check-label" for="emColTo">Кому</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="subject" id="emColSubject" checked><label class="form-check-label" for="emColSubject">Тема</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="mailable_short" id="emColMailable" checked><label class="form-check-label" for="emColMailable">Класс</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="error_excerpt" id="emColErr" checked><label class="form-check-label" for="emColErr">Ошибка</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="send_attempts" id="emColAttempts" checked><label class="form-check-label" for="emColAttempts">Попытки</label></div>
                            <div class="form-check"><input class="form-check-input emails-column-toggle" type="checkbox" data-column-key="actions" id="emColActions" checked><label class="form-check-label" for="emColActions">Действия</label></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $emailsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="emailsReportFiltersCollapse">
    <form id="emails-report-filters" method="GET" action="/admin/reports/emails" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label" for="em-filter-created-from">Создано: с</label>
                <input class="form-control" id="em-filter-created-from" type="date" name="created_at_from" value="{{ $filters['created_at_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="em-filter-created-to">Создано: по</label>
                <input class="form-control" id="em-filter-created-to" type="date" name="created_at_to" value="{{ $filters['created_at_to'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="em-filter-sent-from">Отправлено: с</label>
                <input class="form-control" id="em-filter-sent-from" type="date" name="sent_at_from" value="{{ $filters['sent_at_from'] ?? '' }}">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="em-filter-sent-to">Отправлено: по</label>
                <input class="form-control" id="em-filter-sent-to" type="date" name="sent_at_to" value="{{ $filters['sent_at_to'] ?? '' }}">
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label" for="em-filter-status">Статус</label>
                <select class="form-select" id="em-filter-status" name="status[]" multiple data-placeholder="Все статусы">
                    <option value="sending" {{ in_array('sending', $emFilterStatus, true) ? 'selected' : '' }}>В процессе</option>
                    <option value="sent" {{ in_array('sent', $emFilterStatus, true) ? 'selected' : '' }}>Отправлено</option>
                    <option value="failed" {{ in_array('failed', $emFilterStatus, true) ? 'selected' : '' }}>Ошибка</option>
                </select>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label" for="em-filter-mailable">Класс письма</label>
                <select class="form-select payments-report-filter-select2"
                        id="em-filter-mailable"
                        name="mailable_class"
                        data-placeholder="Все классы"
                        data-search-url="{{ route('reports.emails.mailable.classes.search') }}">
                    <option value=""></option>
                    @if($emailsFilterMailable)
                        <option value="{{ $emailsFilterMailable['id'] }}" selected>{{ $emailsFilterMailable['text'] }}</option>
                    @endif
                </select>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label" for="em-filter-q">Поиск (тема, получатель, отправитель, ошибка)</label>
                <input class="form-control" id="em-filter-q" type="text" name="q" maxlength="255" value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="emailsReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered" id="emails-table">
    <thead>
    <tr>
        <th>№</th>
        <th>ID</th>
        <th>Создано</th>
        <th>Отправлено</th>
        <th>Статус</th>
        <th>От кого</th>
        <th>Кому</th>
        <th>Тема</th>
        <th>Класс</th>
        <th>Ошибка</th>
        <th>Попытки</th>
        <th>Действия</th>
    </tr>
    </thead>
</table>

@section('scripts')
<script type="text/javascript">
$(function () {
    var $form = $('#emails-report-filters');
    var $statTotal  = $('#emailsReportTotalStat');
    var $statSent   = $('#emailsReportSentStat');
    var $statFailed = $('#emailsReportFailedStat');
    var $toolbarRoot = $('#emailsReportToolbarTotals');

    function getFilterParams() {
        var statuses = ($form.find('[name="status[]"]').val() || []);
        return {
            created_at_from: $form.find('[name="created_at_from"]').val() || '',
            created_at_to: $form.find('[name="created_at_to"]').val() || '',
            sent_at_from: $form.find('[name="sent_at_from"]').val() || '',
            sent_at_to: $form.find('[name="sent_at_to"]').val() || '',
            'status[]': statuses,
            mailable_class: $form.find('[name="mailable_class"]').val() || '',
            q: $form.find('[name="q"]').val() || ''
        };
    }

    function setStat($stat, formatted) {
        var $amount = $stat.find('.payments-report-total-amount');
        if ($amount.length) {
            $amount.text(formatted || '0');
        }
    }

    function refreshTotals() {
        if ($toolbarRoot.length) {
            $toolbarRoot.find('.payments-report-total-stat').addClass('payments-report-total-stat--loading');
        }
        $.get(@json(route('reports.emails.total')), getFilterParams())
            .done(function (res) {
                setStat($statTotal,  res && res.total_formatted);
                setStat($statSent,   res && res.sent_formatted);
                setStat($statFailed, res && res.failed_formatted);
            })
            .always(function () {
                if ($toolbarRoot.length) {
                    $toolbarRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--loading');
                }
            });
    }

    // Select2 для класса письма (ajax)
    var $mailable = $('#em-filter-mailable');
    if ($mailable.length && $mailable.data('search-url')) {
        $mailable.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $mailable.data('placeholder') || '',
            allowClear: true,
            ajax: {
                url: $mailable.data('search-url'),
                delay: 250,
                data: function (params) { return {q: params.term || ''}; },
                processResults: function (data) { return data; }
            },
            minimumInputLength: 0
        });
    }

    // Select2 для статуса (multi)
    var $status = $('#em-filter-status');
    if ($status.length) {
        $status.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Все статусы',
            allowClear: true,
            multiple: true
        });
    }

    var defaultColumnsVisibility = {
        id: true,
        created_at: true,
        sent_at: true,
        status: true,
        from_address: true,
        to_summary: true,
        subject: true,
        mailable_short: true,
        error_excerpt: true,
        send_attempts: true,
        actions: true
    };

    // 0 — № (DT_RowIndex), всегда виден; ниже карта индексов колонок
    var columnsMap = {
        id: 1,
        created_at: 2,
        sent_at: 3,
        status: 4,
        from_address: 5,
        to_summary: 6,
        subject: 7,
        mailable_short: 8,
        error_excerpt: 9,
        send_attempts: 10,
        actions: 11
    };

    var currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);

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

    function applyVisibleColumns(config) {
        Object.keys(columnsMap).forEach(function (key) {
            var idx = columnsMap[key];
            var col = table.column(idx);
            var isVisible = toBool(config[key], defaultColumnsVisibility[key]);
            col.visible(isVisible);
            $('.emails-column-toggle[data-column-key="' + key + '"]').prop('checked', isVisible);
        });
        try { table.columns.adjust(); } catch (e) { /* no-op */ }
    }

    function loadColumnsConfigFromServer() {
        $.ajax({
            url: '/admin/reports/emails/columns-settings',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                var merged = {};
                Object.keys(defaultColumnsVisibility).forEach(function (key) {
                    merged[key] = toBool(
                        response && Object.prototype.hasOwnProperty.call(response, key) ? response[key] : defaultColumnsVisibility[key],
                        defaultColumnsVisibility[key]
                    );
                });
                currentColumnsConfig = merged;
                applyVisibleColumns(currentColumnsConfig);
            },
            error: function () {
                currentColumnsConfig = Object.assign({}, defaultColumnsVisibility);
                applyVisibleColumns(currentColumnsConfig);
            }
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function statusBadge(s) {
        if (!s) return '';
        if (s === 'sent') return '<span class="badge bg-success">отправлено</span>';
        if (s === 'sending') return '<span class="badge bg-warning text-dark">в процессе</span>';
        if (s === 'failed') return '<span class="badge bg-danger">ошибка</span>';
        return '<span class="badge bg-secondary">' + escapeHtml(s) + '</span>';
    }

    var columns = [
        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
        {data: 'id', name: 'id'},
        {data: 'created_at', name: 'created_at'},
        {data: 'sent_at', name: 'sent_at'},
        {
            data: 'status', name: 'status',
            render: function (data) { return statusBadge(data); }
        },
        {data: 'from_address', name: 'from_address'},
        {
            data: 'to_summary', name: 'to_summary',
            render: function (data) { return escapeHtml(data); }
        },
        {
            data: 'subject', name: 'subject',
            render: function (data) { return escapeHtml(data); }
        },
        {
            data: 'mailable_short', name: 'mailable_short',
            render: function (data) { return escapeHtml(data); }
        },
        {
            data: 'error_excerpt', name: 'error_excerpt',
            orderable: false, searchable: false,
            render: function (data) {
                if (!data) return '';
                return '<span class="text-danger">' + escapeHtml(data) + '</span>';
            }
        },
        {data: 'send_attempts', name: 'send_attempts'},
        {
            data: 'show_url', name: 'actions',
            orderable: false, searchable: false,
            render: function (url, type, row) {
                return '<a class="btn btn-sm btn-outline-primary" href="' + url + '" title="Открыть">Открыть</a>';
            }
        }
    ];

    var table = $('#emails-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('reports.emails.data') }}",
            data: function (d) {
                var f = getFilterParams();
                Object.keys(f).forEach(function (k) {
                    if (Array.isArray(f[k])) {
                        d[k] = f[k];
                    } else {
                        d[k] = f[k];
                    }
                });
            }
        },
        columns: columns,
        order: [[2, 'desc']],
        scrollX: true,
        language: {
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
            "paginate": {"first": "", "previous": "", "next": "", "last": ""}
        }
    });

    loadColumnsConfigFromServer();

    $form.on('submit', function (e) {
        e.preventDefault();
        refreshTotals();
        table.ajax.reload();
    });

    $('#emailsReportFiltersResetBtn').on('click', function () {
        $form[0].reset();
        $('#em-filter-mailable').val(null).trigger('change');
        $('#em-filter-status').val(null).trigger('change');
        refreshTotals();
        table.ajax.reload();
    });

    $('.emails-column-toggle').on('change', function () {
        var key = $(this).data('column-key');
        var checked = $(this).is(':checked');
        currentColumnsConfig[key] = checked ? 1 : 0;
        applyVisibleColumns(currentColumnsConfig);
        $.ajax({
            url: '/admin/reports/emails/columns-settings',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                columns: currentColumnsConfig
            },
            error: function () { console.error('Не удалось сохранить настройки колонок'); }
        });
    });
});
</script>
@endsection
