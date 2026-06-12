@php
    $leadStats = $leadStats ?? ['total' => 0, 'new' => 0, 'processing' => 0];
    $leadsHasActiveFilters = false;
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2" id="partnerLeadsReportToolbar">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">
                Лиды
            </h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 gap-md-4" id="partnerLeadsReportToolbarTotals">
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="partnerLeadsStatNew">
                        <div class="payments-report-total-label text-muted small mb-0">Новых</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount partner-leads-stat-new">{{ number_format($leadStats['new'], 0, '', ' ') }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="partnerLeadsStatProcessing">
                        <div class="payments-report-total-label text-muted small mb-0">В обработке</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount partner-leads-stat-processing">{{ number_format($leadStats['processing'], 0, '', ' ') }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="payments-report-total-inline payments-report-total-stat text-end" id="partnerLeadsStatTotal">
                        <div class="payments-report-total-label text-muted small mb-0">Всего</div>
                        <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                            <span class="payments-report-total-value-inner">
                                <span class="payments-report-total-amount partner-leads-stat-total">{{ number_format($leadStats['total'], 0, '', ' ') }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#partnerLeadsFiltersCollapse"
                            aria-expanded="{{ $leadsHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="partnerLeadsFiltersCollapse"
                            id="partnerLeadsFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownPartnerLeads"
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
                             aria-labelledby="columnsDropdownPartnerLeads">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="name" id="plColName" checked>
                                <label class="form-check-label" for="plColName">Имя</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="phone" id="plColPhone" checked>
                                <label class="form-check-label" for="plColPhone">Телефон</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="email" id="plColEmail" checked>
                                <label class="form-check-label" for="plColEmail">Email</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="website" id="plColWebsite" checked>
                                <label class="form-check-label" for="plColWebsite">Сайт</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="message" id="plColMessage" checked>
                                <label class="form-check-label" for="plColMessage">Сообщение</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="status" id="plColStatus" checked>
                                <label class="form-check-label" for="plColStatus">Статус</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="comment" id="plColComment" checked>
                                <label class="form-check-label" for="plColComment">Комментарий</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input partner-leads-column-toggle" type="checkbox" data-column-key="actions" id="plColActions" checked>
                                <label class="form-check-label" for="plColActions">Действия</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $leadsHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="partnerLeadsFiltersCollapse">
    <form id="partner-leads-filters" class="border rounded p-2 p-md-3 bg-light" action="#" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-12">
                <label class="form-label d-block mb-2">Статусы</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input status-filter-checkbox" type="checkbox" value="new" id="plFilterStatusNew" checked>
                        <label class="form-check-label" for="plFilterStatusNew">Новый</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input status-filter-checkbox" type="checkbox" value="processing" id="plFilterStatusProcessing" checked>
                        <label class="form-check-label" for="plFilterStatusProcessing">Обработка</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input status-filter-checkbox" type="checkbox" value="sale" id="plFilterStatusSale">
                        <label class="form-check-label" for="plFilterStatusSale">Продажа</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input status-filter-checkbox" type="checkbox" value="rejected" id="plFilterStatusRejected">
                        <label class="form-check-label" for="plFilterStatusRejected">Отказ</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input status-filter-checkbox" type="checkbox" value="spam" id="plFilterStatusSpam">
                        <label class="form-check-label" for="plFilterStatusSpam">Спам</label>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="partnerLeadsFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

    <table id="leads-table" class="table table-bordered table-striped align-middle w-100 dt-columns-managed">
    <thead>
        <tr>
            <th>№</th>
            <th>Имя</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Сайт</th>
            <th>Сообщение</th>
            <th>Статус</th>
            <th>Комментарий</th>
            <th style="width: 120px;">Действия</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<div class="modal fade" id="editLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редактирование заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="editLeadForm">
                    <input type="hidden" id="editLeadId">

                    <div class="mb-3">
                        <label for="leadStatus" class="form-label">Статус</label>
                        <select id="leadStatus" class="form-select">
                            <option value="">— не выбран —</option>
                            <option value="new">Новый</option>
                            <option value="processing">Обработка</option>
                            <option value="sale">Продажа</option>
                            <option value="rejected">Отказ</option>
                            <option value="spam">Спам</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="leadComment" class="form-label">Комментарий</label>
                        <textarea id="leadComment" class="form-control" rows="3"></textarea>
                    </div>
                </form>
                <div class="alert alert-danger d-none" id="editLeadError"></div>
                <div class="alert alert-success d-none" id="editLeadSuccess"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveLeadBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteLeadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Удаление заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                Вы действительно хотите удалить эту заявку? Действие можно будет отменить только через БД.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteLeadBtn">Удалить</button>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="mainToast" class="toast align-items-center text-white bg-success border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="mainToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(document).ready(function () {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            var $filtersForm = $('#partner-leads-filters');
            var $statNew = $('.partner-leads-stat-new');
            var $statProcessing = $('.partner-leads-stat-processing');
            var $statTotal = $('.partner-leads-stat-total');
            var $toolbarRoot = $('#partnerLeadsReportToolbar');

            var editLeadModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
            var deleteLeadModal = new bootstrap.Modal(document.getElementById('deleteLeadModal'));
            var leadIdToDelete = null;

            var toastEl = document.getElementById('mainToast');
            var toastBodyEl = document.getElementById('mainToastBody');
            var toastInstance = new bootstrap.Toast(toastEl, { delay: 2500 });

            var defaultStatusFilters = ['new', 'processing'];

            function readFiltersFromForm() {
                var statuses = [];
                $filtersForm.find('.status-filter-checkbox:checked').each(function () {
                    statuses.push($(this).val());
                });
                return { statuses: statuses };
            }

            var appliedFilters = readFiltersFromForm();

            function partnerLeadsFormatCount(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function updatePartnerLeadsStats(stats) {
                if (!stats) {
                    return;
                }
                if ($toolbarRoot.length) {
                    $toolbarRoot.find('.payments-report-total-stat').addClass('payments-report-total-stat--flash');
                    setTimeout(function () {
                        $toolbarRoot.find('.payments-report-total-stat').removeClass('payments-report-total-stat--flash');
                    }, 400);
                }
                if (stats.new !== undefined) {
                    $statNew.text(partnerLeadsFormatCount(stats.new));
                }
                if (stats.processing !== undefined) {
                    $statProcessing.text(partnerLeadsFormatCount(stats.processing));
                }
                if (stats.total !== undefined) {
                    $statTotal.text(partnerLeadsFormatCount(stats.total));
                }
            }

            function resetFiltersFormToDefault() {
                $filtersForm.find('.status-filter-checkbox').each(function () {
                    var val = $(this).val();
                    $(this).prop('checked', defaultStatusFilters.indexOf(val) !== -1);
                });
            }

            function showToast(message, type) {
                var $toast = $('#mainToast');
                $toast.removeClass('bg-success bg-danger bg-info bg-warning text-dark');
                switch (type) {
                    case 'error':
                        $toast.addClass('bg-danger');
                        break;
                    case 'info':
                        $toast.addClass('bg-info');
                        break;
                    case 'warning':
                        $toast.addClass('bg-warning text-dark');
                        break;
                    default:
                        $toast.addClass('bg-success');
                }
                toastBodyEl.textContent = message;
                toastInstance.show();
            }

            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'new': return 'bg-secondary';
                    case 'processing': return 'bg-warning text-dark';
                    case 'sale': return 'bg-success';
                    case 'rejected': return 'bg-danger';
                    case 'spam': return 'bg-dark';
                    default: return 'bg-secondary';
                }
            }

            const leadStatusInlineSelectOptions = [
                { value: '', label: '— не выбран —' },
                { value: 'new', label: 'Новый' },
                { value: 'processing', label: 'Обработка' },
                { value: 'sale', label: 'Продажа' },
                { value: 'rejected', label: 'Отказ' },
                { value: 'spam', label: 'Спам' },
            ];

            var dtApi = KidsCrmDataTable.create('#leads-table', {
                columnsSettings: {
                    defaults: {
                        name: true,
                        phone: true,
                        email: true,
                        website: true,
                        message: true,
                        status: true,
                        comment: true,
                        actions: true,
                    },
                    toggleSelector: '.partner-leads-column-toggle',
                    urls: {
                        get: @json(route('admin.partner-leads.columns-settings.get')),
                        save: @json(route('admin.partner-leads.columns-settings.save')),
                    },
                    csrfToken: csrfToken,
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.partner-leads.data')),
                        type: 'GET',
                        data: function (d) {
                            d.statuses = appliedFilters.statuses;
                        },
                        dataSrc: function (json) {
                            updatePartnerLeadsStats(json.stats);
                            return json.data;
                        },
                    },
                    order: [[0, 'desc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { type: 'id', data: 'id', name: 'id' },
                    { key: 'name', type: 'text', data: 'name', name: 'name' },
                    { key: 'phone', type: 'text', data: 'phone', name: 'phone', className: 'dt-col-text text-nowrap' },
                    {
                        key: 'email',
                        type: 'text',
                        data: 'email',
                        name: 'email',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return data ? $('<div/>').text(data).html() : '—';
                        },
                    },
                    {
                        key: 'website',
                        type: 'link',
                        data: 'website',
                        name: 'website',
                        className: 'dt-col-text',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            if (!data) {
                                return '<span class="dt-cell-empty text-muted">—</span>';
                            }

                            var short = data.length > 30 ? data.substring(0, 27) + '...' : data;
                            return '<a href="' + $('<div/>').text(data).html() + '" target="_blank" rel="noopener">'
                                + $('<div/>').text(short).html()
                                + '</a>';
                        },
                    },
                    {
                        key: 'message',
                        type: 'text',
                        data: 'message',
                        name: 'message',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return data ? $('<div/>').text(data).html() : '';
                        },
                    },
                    {
                        key: 'status',
                        type: 'inline-select',
                        data: 'status_label',
                        name: 'status',
                        className: 'dt-col-inline-select',
                        inlineSelect: {
                            statusKey: 'status',
                            labelKey: 'status_label',
                            rowIdKey: 'id',
                            badgeSelector: 'lead-status-badge',
                            selectSelector: 'lead-status-select',
                            badgeExtraClass: '',
                            selectExtraClass: 'form-select form-select-sm d-none',
                            badgeClassFn: getStatusBadgeClass,
                            options: leadStatusInlineSelectOptions,
                        },
                    },
                    {
                        key: 'comment',
                        type: 'text-long',
                        data: 'comment',
                        name: 'comment',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data != null ? data : '';
                            }

                            return data ? $('<div/>').text(data).html() : '';
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            return '' +
                                '<button type="button" class="btn btn-sm btn-primary me-1 edit-lead" data-id="' + row.id + '" title="Редактировать"><i class="fa fa-edit"></i></button>' +
                                '<button type="button" class="btn btn-sm btn-danger delete-lead" data-id="' + row.id + '" title="Удалить"><i class="fa fa-trash"></i></button>';
                        },
                    },
                ],
            });

            var table = dtApi.table;

            $filtersForm.on('submit', function (e) {
                e.preventDefault();
                appliedFilters = readFiltersFromForm();
                dtApi.reload({ keepPage: true });
            });

            $('#partnerLeadsFiltersResetBtn').on('click', function () {
                resetFiltersFormToDefault();
                appliedFilters = readFiltersFromForm();
                dtApi.reload({ keepPage: true });
            });

            $('#leads-table').on('click', '.edit-lead', function () {
                var rowData = table.row($(this).closest('tr')).data();
                $('#editLeadId').val(rowData.id);
                $('#leadStatus').val(rowData.status || '');
                $('#leadComment').val(rowData.comment || '');
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');
                editLeadModal.show();
            });

            $('#saveLeadBtn').on('click', function () {
                var id = $('#editLeadId').val();
                $('#editLeadError, #editLeadSuccess').addClass('d-none').text('');

                $.ajax({
                    url: '/admin/partner-leads/' + id,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: {
                        status: $('#leadStatus').val(),
                        comment: $('#leadComment').val()
                    },
                    success: function (response) {
                        $('#editLeadSuccess').removeClass('d-none').text(response.message || 'Сохранено.');
                        dtApi.reload({ keepPage: true });
                        showToast(response.message || 'Изменения сохранены.', 'success');
                        setTimeout(function () { editLeadModal.hide(); }, 600);
                    },
                    error: function (xhr) {
                        var message = 'Ошибка сохранения.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        $('#editLeadError').removeClass('d-none').text(message);
                        showToast(message, 'error');
                    }
                });
            });

            $('#leads-table').on('click', '.lead-status-badge', function () {
                var $badge = $(this);
                var $container = $badge.closest('div');
                $badge.addClass('d-none');
                $container.find('.lead-status-select').removeClass('d-none').focus();
            });

            $('#leads-table').on('change', '.lead-status-select', function () {
                var $select = $(this);
                var id = $select.data('id');
                $.ajax({
                    url: '/admin/partner-leads/' + id,
                    type: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { status: $select.val() },
                    success: function (response) {
                        var $container = $select.closest('div');
                        var $badge = $container.find('.lead-status-badge');
                        $badge
                            .removeClass('bg-secondary bg-warning text-dark bg-success bg-danger bg-dark')
                            .addClass(getStatusBadgeClass(response.status))
                            .attr('data-status', response.status || '')
                            .text(response.status_label || '—');
                        $select.addClass('d-none');
                        $badge.removeClass('d-none');
                        showToast(response.message || 'Статус обновлён.', 'success');
                        dtApi.reload({ keepPage: true });
                    },
                    error: function (xhr) {
                        var message = 'Ошибка обновления статуса.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        showToast(message, 'error');
                        var $badge = $select.closest('div').find('.lead-status-badge');
                        $select.val($badge.data('status') || '');
                        $select.addClass('d-none');
                        $badge.removeClass('d-none');
                    }
                });
            });

            $('#leads-table').on('blur', '.lead-status-select', function () {
                var $select = $(this);
                setTimeout(function () {
                    if (!$select.is(':focus')) {
                        $select.addClass('d-none');
                        $select.closest('div').find('.lead-status-badge').removeClass('d-none');
                    }
                }, 150);
            });

            $('#leads-table').on('click', '.delete-lead', function () {
                leadIdToDelete = table.row($(this).closest('tr')).data().id;
                deleteLeadModal.show();
            });

            $('#confirmDeleteLeadBtn').on('click', function () {
                if (!leadIdToDelete) {
                    return;
                }
                $.ajax({
                    url: '/admin/partner-leads/' + leadIdToDelete,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function (response) {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        dtApi.reload({ keepPage: true });
                        showToast(response.message || 'Заявка удалена.', 'success');
                    },
                    error: function () {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        showToast('Ошибка при удалении заявки.', 'error');
                    }
                });
            });
        });
    </script>
@endpush
