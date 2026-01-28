@extends('layouts.admin2')

@section('content')
            <style>
        .status-filters-container {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-filters-row {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: nowrap;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .filter-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin: 0;
        }

        .filter-item-label {
            font-size: 14px;
            font-weight: 500;
            color: #212529;
            cursor: pointer;
            white-space: nowrap;
        }

        .filter-item input[type="checkbox"]:not(:checked) + .filter-item-label {
            color: #6c757d;
        }
    </style>


    <div class="main-content text-start">

        <h4 class="pt-3">Заявки с лендинга</h4>
        <hr>

        <div class="container">

                        {{-- ФИЛЬТР ПО СТАТУСУ --}}

            <div class="status-filters-container">
                <div class="status-filters-row">
                    <span class="text-muted fw-semibold">Статусы:</span>

                    <label class="filter-item">
                        <input type="checkbox" class="status-filter-checkbox" value="new" checked>
                        <span class="filter-item-label">Новый</span>
                    </label>

                    <label class="filter-item">
                        <input type="checkbox" class="status-filter-checkbox" value="processing" checked>
                        <span class="filter-item-label">Обработка</span>
                    </label>

                    <label class="filter-item">
                        <input type="checkbox" class="status-filter-checkbox" value="sale">
                        <span class="filter-item-label">Продажа</span>
                    </label>

                    <label class="filter-item">
                        <input type="checkbox" class="status-filter-checkbox" value="rejected">
                        <span class="filter-item-label">Отказ</span>
                    </label>

                    <label class="filter-item">
                        <input type="checkbox" class="status-filter-checkbox" value="spam">
                        <span class="filter-item-label">Спам</span>
                    </label>
                </div>
            </div>






            <table id="leads-table" class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>#</th>
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
                <tbody>
                    {{-- DataTables заполняет тело сам через AJAX --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- Модалка редактирования статуса/комментария --}}
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

    {{-- Модалка удаления --}}
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



    {{-- Toast для уведомлений --}}
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="mainToast" class="toast align-items-center text-white bg-success border-0" role="alert"
            aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="mainToastBody">
                    <!-- Сообщение -->
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
@endsection

{{-- Скрипты прямо во вьюхе, чтобы не ломать существующий стек --}}

@section('scripts')
    <script>
        $(document).ready(function() {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            var editLeadModal = new bootstrap.Modal(document.getElementById('editLeadModal'));
            var deleteLeadModal = new bootstrap.Modal(document.getElementById('deleteLeadModal'));
            var leadIdToDelete = null;

            // ---- Toast helper ----
            var toastEl = document.getElementById('mainToast');
            var toastBodyEl = document.getElementById('mainToastBody');
            var toastInstance = new bootstrap.Toast(toastEl, {
                delay: 2500
            });

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

            // ---- Хелперы для статуса ----
            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'new':
                        return 'bg-secondary';
                    case 'processing':
                        return 'bg-warning text-dark';
                    case 'sale':
                        return 'bg-success';
                    case 'rejected':
                        return 'bg-danger';
                    case 'spam':
                        return 'bg-dark';
                    default:
                        return 'bg-secondary';
                }
            }

            function buildStatusOptionsHtml(selectedStatus) {
                var statuses = [{
                        value: '',
                        label: '— не выбран —'
                    },
                    {
                        value: 'new',
                        label: 'Новый'
                    },
                    {
                        value: 'processing',
                        label: 'Обработка'
                    },
                    {
                        value: 'sale',
                        label: 'Продажа'
                    },
                    {
                        value: 'rejected',
                        label: 'Отказ'
                    },
                    {
                        value: 'spam',
                        label: 'Спам'
                    }
                ];

                var html = '';
                statuses.forEach(function(st) {
                    var selected = (st.value === (selectedStatus || '')) ? ' selected' : '';
                    html += '<option value="' + st.value + '"' + selected + '>' + st.label + '</option>';
                });

                return html;
            }

            // ---- DataTable ----
            var table = $('#leads-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('admin.leads.data') }}',
                    type: 'GET',
                    data: function(d) {
                        // Собираем чекбоксы статусов
                        var statuses = [];
                        $('.status-filter-checkbox:checked').each(function() {
                            statuses.push($(this).val());
                        });
                        d.statuses = statuses;
                    }
                },
                columns: [{
                        data: 'id',
                        name: 'id'
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'phone',
                        name: 'phone'
                    },
                    {
                        data: 'email',
                        name: 'email',
                        render: function(data) {
                            return data ? data : '—';
                        }
                    },
                    {
                        data: 'website',
                        name: 'website',
                        render: function(data) {
                            if (!data) {
                                return '—';
                            }
                            var short = data.length > 30 ? data.substring(0, 27) + '...' : data;
                            return '<a href="' + data + '" target="_blank" rel="noopener">' +
                                short + '</a>';
                        }
                    },
                    {
                        data: 'message',
                        name: 'message',
                        render: function(data) {
                            if (!data) {
                                return '';
                            }
                            return $('<div/>').text(data).html();
                        }
                    },
                    {
                        data: 'status_label',
                        name: 'status',
                        render: function(data, type, row) {
                            var status = row.status; // 'new', 'processing' и т.д.
                            var label = row.status_label || '—';
                            var badgeClass = getStatusBadgeClass(status);
                            var optionsHtml = buildStatusOptionsHtml(status);

                            return '' +
                                '<div class="d-flex align-items-center gap-1">' +
                                '<span class="badge ' + badgeClass + ' lead-status-badge" ' +
                                'data-id="' + row.id + '" ' +
                                'data-status="' + (status || '') + '">' +
                                label +
                                '</span>' +
                                '<select class="form-select form-select-sm lead-status-select d-none" ' +
                                'data-id="' + row.id + '">' +
                                optionsHtml +
                                '</select>' +
                                '</div>';
                        }
                    },
                    {
                        data: 'comment',
                        name: 'comment',
                        render: function(data) {
                            if (!data) {
                                return '';
                            }
                            return $('<div/>').text(data).html();
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return '' +
                                '<button type="button" class="btn btn-sm btn-primary me-1 edit-lead" data-id="' +
                                row.id + '">' +
                                '<i class="fa fa-edit"></i>' +
                                '</button>' +
                                '<button type="button" class="btn btn-sm btn-danger delete-lead" data-id="' +
                                row.id + '">' +
                                '<i class="fa fa-trash"></i>' +
                                '</button>';
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ],
                language: {
                    processing: "Загрузка...",
                    search: "Поиск:",
                    lengthMenu: "Показать _MENU_ записей",
                    info: "Показаны _START_–_END_ из _TOTAL_",
                    infoEmpty: "Нет записей",
                    infoFiltered: "(отфильтровано из _MAX_ записей)",
                    loadingRecords: "Загрузка...",
                    zeroRecords: "Совпадений не найдено",
                    emptyTable: "Данные отсутствуют",
                    paginate: {
                        first: "Первая",
                        previous: "Предыдущая",
                        next: "Следующая",
                        last: "Последняя"
                    }
                }
            });

            // ---- фильтр по статусам ----
            $(document).on('change', '.status-filter-checkbox', function() {
                table.ajax.reload();
            });

            // ---- Открытие модалки редактирования (статус + комментарий) ----
            $('#leads-table').on('click', '.edit-lead', function() {
                var rowData = table.row($(this).closest('tr')).data();

                $('#editLeadId').val(rowData.id);
                $('#leadStatus').val(rowData.status || '');
                $('#leadComment').val(rowData.comment || '');

                $('#editLeadError').addClass('d-none').text('');
                $('#editLeadSuccess').addClass('d-none').text('');

                editLeadModal.show();
            });

            // ---- Сохранение из модалки ----
            $('#saveLeadBtn').on('click', function() {
                var id = $('#editLeadId').val();
                var status = $('#leadStatus').val();
                var comment = $('#leadComment').val();

                $('#editLeadError').addClass('d-none').text('');
                $('#editLeadSuccess').addClass('d-none').text('');

                $.ajax({
                    url: '/admin/leads/' + id,
                    type: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    data: {
                        status: status,
                        comment: comment
                    },
                    success: function(response) {
                        $('#editLeadSuccess').removeClass('d-none').text(response.message ||
                            'Сохранено.');
                        table.ajax.reload(null, false);
                        showToast(response.message || 'Изменения сохранены.', 'success');
                        setTimeout(function() {
                            editLeadModal.hide();
                        }, 600);
                    },
                    error: function(xhr) {
                        var message = 'Ошибка сохранения.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        $('#editLeadError').removeClass('d-none').text(message);
                        showToast(message, 'error');
                    }
                });
            });

            // ---- Клик по бейджу статуса = включаем селект ----
            $('#leads-table').on('click', '.lead-status-badge', function() {
                var $badge = $(this);
                var $container = $badge.closest('div');
                var $select = $container.find('.lead-status-select');

                $badge.addClass('d-none');
                $select.removeClass('d-none').focus();
            });

            // ---- Изменение статуса через inline-селект ----
            $('#leads-table').on('change', '.lead-status-select', function() {
                var $select = $(this);
                var id = $select.data('id');
                var newStatus = $select.val();

                $.ajax({
                    url: '/admin/leads/' + id,
                    type: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    data: {
                        status: newStatus
                    },
                    success: function(response) {
                        var $container = $select.closest('div');
                        var $badge = $container.find('.lead-status-badge');

                        var badgeClass = getStatusBadgeClass(response.status);

                        $badge
                            .removeClass(
                                'bg-secondary bg-warning text-dark bg-success bg-danger bg-dark'
                            )
                            .addClass(badgeClass)
                            .attr('data-status', response.status || '')
                            .text(response.status_label || '—');

                        $select.addClass('d-none');
                        $badge.removeClass('d-none');

                        showToast(response.message || 'Статус обновлён.', 'success');
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var message = 'Ошибка обновления статуса.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        showToast(message, 'error');

                        // откат селекта к старому значению
                        var $badge = $select.closest('div').find('.lead-status-badge');
                        var oldStatus = $badge.data('status') || '';
                        $select.val(oldStatus);

                        $select.addClass('d-none');
                        $badge.removeClass('d-none');
                    }
                });
            });

            // ---- Потеря фокуса селекта статуса ----
            $('#leads-table').on('blur', '.lead-status-select', function() {
                var $select = $(this);
                setTimeout(function() {
                    if (!$select.is(':focus')) {
                        $select.addClass('d-none');
                        $select.closest('div').find('.lead-status-badge').removeClass('d-none');
                    }
                }, 150);
            });

            // ---- Удаление ----
            $('#leads-table').on('click', '.delete-lead', function() {
                var rowData = table.row($(this).closest('tr')).data();
                leadIdToDelete = rowData.id;
                deleteLeadModal.show();
            });

            $('#confirmDeleteLeadBtn').on('click', function() {
                if (!leadIdToDelete) {
                    return;
                }

                $.ajax({
                    url: '/admin/leads/' + leadIdToDelete,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        table.ajax.reload(null, false);
                        showToast(response.message || 'Заявка удалена.', 'success');
                    },
                    error: function() {
                        deleteLeadModal.hide();
                        leadIdToDelete = null;
                        showToast('Ошибка при удалении заявки.', 'error');
                    }
                });
            });
        });
    </script>
@endsection
