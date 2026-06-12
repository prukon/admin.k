// Настройки
document.addEventListener('DOMContentLoaded', function () {
    // Модалки
    var settingsModalEl = document.getElementById('settingsModal');
    var settingsModal = new bootstrap.Modal(settingsModalEl);

    var createStatusModalEl = document.getElementById('createStatusModal');
    var createStatusModal = new bootstrap.Modal(createStatusModalEl);

    var editStatusModalEl = document.getElementById('editStatusModal');
    var editStatusModal = new bootstrap.Modal(editStatusModalEl);

    // Кнопка "Настройки"
    var btnSettings = document.getElementById('btn-settings');
    // Таблица статусов
    var statusesTableBody = document.getElementById('statuses-table-body');
    // Кнопка "Новый статус"
    var btnNewStatus = document.getElementById('btn-new-status');

    // При клике "Настройки" — грузим статусы и показываем модалку
    btnSettings.addEventListener('click', function () {
        settingsModal.show();
    });

    // "Новый статус" — сбрасываем форму и показываем модалку создания
    btnNewStatus.addEventListener('click', function () {
        document.getElementById('createStatusForm').reset();
        // Сбрасываем выбранные иконки
        document.querySelectorAll('#createIconList .icon-item').forEach(i => i.classList.remove('selected'));
        document.getElementById('createIcon').value = '';
        createStatusModal.show();
    });

    function clearScheduleStatusFormErrors(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('[data-error-for]').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
    }

    function showScheduleStatusFormErrors(form, errors) {
        if (!form || !errors) {
            return;
        }
        Object.keys(errors).forEach(field => {
            const input = form.querySelector('[name="' + field + '"]');
            const feedback = form.querySelector('[data-error-for="' + field + '"]');
            const messages = errors[field];
            if (!messages || !messages.length) {
                return;
            }
            if (input) {
                input.classList.add('is-invalid');
            }
            if (feedback) {
                feedback.textContent = messages[0];
                feedback.style.display = 'block';
            }
        });
    }

    function parseJsonResponse(resp) {
        return resp.json().then(body => ({ ok: resp.ok, status: resp.status, body }));
    }

    // Загрузка статусов
    function loadStatuses() {
        if (!statusesTableBody) {
            return;
        }

        fetch("/schedule/statuses")
            .then(resp => resp.json())
            .then(data => {
                if (!Array.isArray(data.statuses)) {
                    return;
                }

                statusesTableBody.innerHTML = '';
                data.statuses.forEach(st => {
                    const sortOrder = (st.sort_order !== undefined && st.sort_order !== null)
                        ? Number(st.sort_order)
                        : 0;
                    const isSystem = !!st.is_system;

                    let tr = document.createElement('tr');

                    let tdName = document.createElement('td');
                    tdName.appendChild(document.createTextNode(st.name || ''));
                    if (isSystem) {
                        let hint = document.createElement('i');
                        hint.className = 'fas fa-question-circle ms-1';
                        hint.setAttribute('data-kids-tooltip-hint', '1');
                        hint.setAttribute('data-bs-toggle', 'tooltip');
                        hint.setAttribute('title', 'Системный статус. Нельзя изменить или удалить');
                        tdName.appendChild(document.createTextNode(' '));
                        tdName.appendChild(hint);
                    }
                    tr.appendChild(tdName);

                    let tdSort = document.createElement('td');
                    let sortSpan = document.createElement('span');
                    if (isSystem) {
                        sortSpan.className = 'text-muted';
                    }
                    sortSpan.textContent = String(sortOrder);
                    tdSort.appendChild(sortSpan);
                    tr.appendChild(tdSort);

                    let tdIcon = document.createElement('td');
                    if (st.icon) {
                        let iconEl = document.createElement('i');
                        iconEl.className = st.icon;
                        iconEl.style.backgroundColor = st.color || '';
                        iconEl.style.color = '#000000';
                        iconEl.style.padding = '5px';
                        iconEl.style.borderRadius = '3px';
                        tdIcon.appendChild(iconEl);
                    }
                    tr.appendChild(tdIcon);

                    let tdActions = document.createElement('td');
                    if (!isSystem) {
                        let btnEdit = document.createElement('button');
                        btnEdit.type = 'button';
                        btnEdit.className = 'btn btn-sm btn-success me-1';
                        btnEdit.textContent = 'Изменить';
                        btnEdit.dataset.action = 'edit';
                        btnEdit.dataset.id = String(st.id);
                        btnEdit.dataset.name = st.name || '';
                        btnEdit.dataset.icon = st.icon || '';
                        btnEdit.dataset.color = st.color || '';
                        btnEdit.dataset.sortOrder = String(sortOrder);

                        let btnDelete = document.createElement('button');
                        btnDelete.type = 'button';
                        btnDelete.className = 'btn btn-sm btn-danger';
                        btnDelete.textContent = 'Удалить';
                        btnDelete.dataset.action = 'delete';
                        btnDelete.dataset.id = String(st.id);

                        tdActions.appendChild(btnEdit);
                        tdActions.appendChild(btnDelete);
                    }
                    tr.appendChild(tdActions);

                    statusesTableBody.appendChild(tr);
                });

                if (window.KidsCrmTooltip) {
                    KidsCrmTooltip.init(statusesTableBody, { scopes: ['hint'] });
                }
            })
            .catch(err => console.error(err));
    }

    // СОЗДАНИЕ СТАТУСА
    var createStatusForm = document.getElementById('createStatusForm');
    createStatusForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearScheduleStatusFormErrors(createStatusForm);
        let formData = new FormData(createStatusForm);

        fetch("/schedule/statuses", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: formData
        })
            .then(parseJsonResponse)
            .then(({ ok, body }) => {
                if (ok && body.success) {
                    loadStatuses();
                    createStatusModal.hide();
                    showSuccessModal("Создание статуса", "Статус успешно создан.", 0);
                    return;
                }
                if (body.errors) {
                    showScheduleStatusFormErrors(createStatusForm, body.errors);
                    return;
                }
                $('#errorModal').modal('show');
            })
            .catch(err => console.error(err));
    });

    // РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ
    var editStatusForm = document.getElementById('editStatusForm');
    statusesTableBody.addEventListener('click', function (e) {
        let action = e.target.dataset.action;
        if (action === 'edit') {
            let id = e.target.dataset.id;
            let name = e.target.dataset.name;
            let icon = e.target.dataset.icon;
            let color = e.target.dataset.color || '#ffffff';
            let sortOrder = e.target.dataset.sortOrder ?? '0';

            clearScheduleStatusFormErrors(editStatusForm);
            document.getElementById('editStatusId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editIcon').value = icon;
            document.getElementById('editColor').value = color;
            document.getElementById('editSortOrder').value = sortOrder;

            document.querySelectorAll('#editIconList .icon-item').forEach(item => {
                item.classList.remove('selected');
                if (item.dataset.icon === icon) {
                    item.classList.add('selected');
                }
            });
            editStatusModal.show();
        } else if (action === 'delete') {
            showConfirmDeleteModal(
                "Удаление статуса",
                "Вы уверены, что хотите удалить этот статус? (Ранее установленные значения для дней с этим статусом останутся без изменений.)",
                function () {
                    let id = e.target.dataset.id;

                    fetch("/schedule/statuses/" + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                        .then(resp => resp.json())
                        .then(d => {
                            if (d.success) {
                                loadStatuses();
                                showSuccessModal("Удаление статуса", "Статус успешно удален.", 0);
                            } else {
                                $('#errorModal').modal('show');
                            }
                        })
                        .catch(err => console.error(err));
                });
        }
    });

    // Сабмит формы редактирования (исправление 405: POST + _method=PATCH)
    editStatusForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearScheduleStatusFormErrors(editStatusForm);

        let statusId = document.getElementById('editStatusId').value;
        let formData = new FormData(editStatusForm);
        formData.append('_method', 'PATCH');

        fetch("/schedule/statuses/" + statusId, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: formData
        })
            .then(parseJsonResponse)
            .then(({ ok, body }) => {
                if (ok && body.success) {
                    loadStatuses();
                    editStatusModal.hide();
                    showSuccessModal("Редактирование статуса", "Статус успешно обновлен.", 0);
                    return;
                }
                if (body.errors) {
                    showScheduleStatusFormErrors(editStatusForm, body.errors);
                    return;
                }
                $('#errorModal').modal('show');
            })
            .catch(err => console.error(err));
    });

    // Выбор иконки (Create)
    let createIconList = document.getElementById('createIconList');
    createIconList.querySelectorAll('.icon-item').forEach(item => {
        item.addEventListener('click', function () {
            createIconList.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('createIcon').value = this.dataset.icon;
        });
    });

    // Выбор иконки (Edit)
    let editIconList = document.getElementById('editIconList');
    editIconList.querySelectorAll('.icon-item').forEach(item => {
        item.addEventListener('click', function () {
            editIconList.querySelectorAll('.icon-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('editIcon').value = this.dataset.icon;
        });
    });






    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('fullscreen') == '1') {
        $('.schedule-fullscreen-wrapper').addClass('fullscreen');
        $('body').addClass('no-scroll');
        $('#btn-fullscreen').html('<i class="fas fa-compress"></i>');
    }

    var numDays = $('.schedule-day-header').length;

    var dtColumns = [
        {orderable: false},
        {orderable: true},
        {orderable: true},
        {orderable: false}
    ];
    for (var i = 0; i < numDays; i++) {
        dtColumns.push({orderable: false});
    }
    var table = $('#schedule-table').DataTable({
        paging: false,
        info: false,
        ordering: true,
        order: [],
        columns: dtColumns,
        dom: 'lrtip',
        language: {
            search: "Поиск:",
            zeroRecords: "Ничего не найдено",
            infoEmpty: "",
        }
    });

    if (window.KidsCrmTooltip) {
        var scheduleTableEl = document.getElementById('schedule-table');
        if (scheduleTableEl) {
            KidsCrmTooltip.bindDataTable(scheduleTableEl);
        }
    }

    $('#table-search').on('keyup', function () {
        table.search(this.value).draw();
    });



    function formatDateHuman(dateStr) {
        const months = [
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];

        const dateObj = new Date(dateStr);
        const day = dateObj.getDate();
        const month = months[dateObj.getMonth()];
        const year = dateObj.getFullYear();

        return `${day} ${month} ${year}`;
    }

    //Изменение фильтров
    $('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change', function () {
        var year = $('#filter-year').val();
        var month = $('#filter-month').val();
        var team = $('#filter-team').val();

        var newUrl = new URL(window.location.href);
        newUrl.searchParams.set('year', year);
        newUrl.searchParams.set('month', month);
        newUrl.searchParams.set('team', team);

        if ($('.schedule-fullscreen-wrapper').hasClass('fullscreen')) {
            newUrl.searchParams.set('fullscreen', '1');
        } else {
            newUrl.searchParams.delete('fullscreen');
        }
        window.location.href = newUrl.toString();
    });

    var cellEditModal = new bootstrap.Modal(document.getElementById('cellEditModal'), {});
    var currentCell;
    var visitedStatusId = window.SCHEDULE_VISITED_STATUS_ID
        ? parseInt(window.SCHEDULE_VISITED_STATUS_ID, 10)
        : null;
    var cellContextCache = null;

    function isVisitedStatusId(statusId) {
        return visitedStatusId && parseInt(statusId, 10) === visitedStatusId;
    }

    function populateTrainerSelect(trainers, selectedValue) {
        var $sel = $('#cell-trainer-profile-id');
        $sel.empty();
        $sel.append($('<option>', { value: '', text: 'Без тренера' }));
        (trainers || []).forEach(function (trainer) {
            $sel.append($('<option>', { value: trainer.id, text: trainer.name }));
        });
        if (selectedValue === null || selectedValue === undefined) {
            $sel.val('');
        } else {
            $sel.val(String(selectedValue));
        }
    }

    function trainerSelectValueForVisited(ctx) {
        if (!ctx) {
            return '';
        }
        if (ctx.trainer_profile_id_for_select !== null && ctx.trainer_profile_id_for_select !== undefined) {
            return ctx.trainer_profile_id_for_select;
        }
        if (ctx.team_default_trainer_profile_id) {
            return String(ctx.team_default_trainer_profile_id);
        }
        return '';
    }

    function syncTrainerBlock() {
        var checked = $('input[name="status_id"]:checked');
        var statusVal = checked.val();
        if (!isVisitedStatusId(statusVal)) {
            $('#cell-trainer-wrap').addClass('d-none');
            $('#cell-trainer-profile-id').val('');
            $('#cell-trainer-hint').text('');
            return;
        }

        $('#cell-trainer-wrap').removeClass('d-none');
        var selectVal = trainerSelectValueForVisited(cellContextCache);
        if ($('#cell-trainer-profile-id option').length <= 1 && cellContextCache && cellContextCache.trainers) {
            populateTrainerSelect(cellContextCache.trainers, selectVal);
        } else {
            $('#cell-trainer-profile-id').val(selectVal === null ? '' : String(selectVal));
        }

        var hint = '';
        if (cellContextCache && cellContextCache.team_default_trainer_profile_id && selectVal === String(cellContextCache.team_default_trainer_profile_id)) {
            hint = 'По умолчанию — первый тренер группы.';
        }
        $('#cell-trainer-hint').text(hint);
    }

    $(document).on('change', 'input[name="status_id"]', function () {
        syncTrainerBlock();
    });

    //Вызов Редактирование ячейки
    $(document).on('click', '.schedule-cell', function () {
        currentCell = $(this);
        let userId = $(this).data('user-id');
        let date = $(this).data('date');
        let statusId = $(this).data('status-id');
        let comment = $(this).attr('data-comment') || '';
        let userName = $(this).data('user-name');

        $('#edit-user-id').val(userId);
        $('#edit-date').val(date);
        $('#description').val(comment);
        $('input[name="status_id"]').prop('checked', false);
        if (statusId) {
            $('input[name="status_id"][value="' + statusId + '"]').prop('checked', true);
        } else {
            $('#status-empty').prop('checked', true);
        }

        $('#edit-user-name-display').text(userName);
        $('#edit-date-display').text(formatDateHuman(date));

        cellContextCache = null;
        populateTrainerSelect([], '');
        $('#cell-trainer-wrap').addClass('d-none');
        $('#cell-trainer-hint').text('');

        $.ajax({
            url: '/schedule/cell-context',
            method: 'GET',
            data: { user_id: userId, date: date },
            headers: { 'Accept': 'application/json' },
            success: function (ctx) {
                cellContextCache = ctx;
                populateTrainerSelect(ctx.trainers || [], '');
                syncTrainerBlock();
            },
        });

        cellEditModal.show();
    });

    //Сохранить Редактирование ячейки
    $('#cellEditForm').on('submit', function (e) {
        e.preventDefault();

        var formData = $(this).serializeArray();
        var chosenStatus = $('input[name="status_id"]:checked').val();
        if (!isVisitedStatusId(chosenStatus)) {
            formData = formData.filter(function (item) {
                return item.name !== 'trainer_profile_id';
            });
        }

        $.ajax({
            url: "/schedule/update",
            method: "POST",
            data: $.param(formData),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            success: function (response) {
                if (response.success) {
                    let chosenRadio = $('input[name="status_id"]:checked');
                    let chosenStatusId = chosenRadio.val();
                    let icon = chosenRadio.data('icon') || '';
                    let color = chosenRadio.data('color') || '';
                    let text = chosenRadio.closest('.form-check').find('label').text().trim();

                    let comment = $('#description').val().trim();

                    // Очищаем текущее содержимое ячейки
                    currentCell.empty();


                    // Добавляем иконку или текст

                    if (icon) {
                        currentCell.html('<i class="' + icon + '"></i>');
                    } else {
                        currentCell.text(text);
                    }

                    // Добавляем индикатор комментария, если комментарий есть
                    if (comment !== '') {
                        currentCell.append('<div class="cell-comment-indicator" style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>');
                    }

                    // Обновляем фон, data-атрибуты
                    currentCell.css('background-color', color);
                    currentCell.attr('data-status-id', chosenStatusId);
                    // currentCell.attr('data-comment', $('#description').val().trim());
                    currentCell.attr('data-comment', comment);


                    cellEditModal.hide();
                }
            }
        });
    });


    //Клик переход в полноэкранный режим
    $('#btn-fullscreen').on('click', function () {
        $('.schedule-fullscreen-wrapper').toggleClass('fullscreen');
        $('body').toggleClass('no-scroll');

        var newUrl = new URL(window.location.href);
        if ($('.schedule-fullscreen-wrapper').hasClass('fullscreen')) {
            $('#btn-fullscreen').html('<i class="fas fa-compress"></i>');
            newUrl.searchParams.set('fullscreen', '1');
            $('.wrap-filter-year').hide();
        } else {
            $('#btn-fullscreen').html('<i class="fas fa-expand"></i>');
            newUrl.searchParams.delete('fullscreen');
            $('.wrap-filter-year').show();
        }
        window.history.replaceState({}, '', newUrl);
    });


    let userScheduleModalEl = document.getElementById('userScheduleModal');
    let userScheduleModal = new bootstrap.Modal(userScheduleModalEl);


    //Вызов Личное расписание ученика
    $(document).on('click', '.edit-user-schedule', function () {
        let userId = $(this).data('user-id');

        $('#userScheduleModalContent').html('Загрузка...');
        $.ajax({
            // url: '/admin/user-schedule/' + userId,
            url: '/schedule/user-schedule/' + userId,
            method: 'GET',
            success: function (resp) {

                if (!resp.success) {
                    $('#userScheduleModalContent').html('Ошибка при получении данных.');
                    userScheduleModal.show();
                    return;
                }

                let user = resp.user;
                let groupWeekdays = resp.groupWeekdays;
                let defaultFrom = resp.defaultFrom;
                let defaultTo = resp.defaultTo;

                let html = `<div><p><strong>ФИО:</strong> ${user.name}</p>`;
                if (!user.team_id) {
                    html += `
                        <p><strong>Группа:</strong> <span class="text-danger">не выбрана</span></p>
                        <buton type="button"
                                class="btn btn-primary mb-3"
                                id="btnChooseGroup"
                                data-user-id="${user.id}">
                            Выбрать группу
                        </buton>
                    `;
                } else {
                    html += `<p><strong>Группа:</strong> ${user.team_title}</p>`;
                }

                let days = [
                    {id: 1, label: 'Пн'},
                    {id: 2, label: 'Вт'},
                    {id: 3, label: 'Ср'},
                    {id: 4, label: 'Чт'},
                    {id: 5, label: 'Пт'},
                    {id: 6, label: 'Суб'},
                    {id: 7, label: 'Вск'},
                ];
                html += `<div class="mb-3"><div class="label-ind-day"><strong>Новое расписание:</strong></div>`;
                days.forEach((d) => {
                    let highlight = groupWeekdays.includes(d.id) ? 'highlight-border' : '';
                    html += `
                        <label class="day-checkbox ${highlight}" style="margin-right: 0.5rem;">
                            <input class="form-check-input user-day-chk"
                                   type="checkbox"
                                   value="${d.id}"
                                   id="chk_${d.id}"
                                   style="margin-right: 0.3rem;" />
                            ${d.label}
                        </label>
                    `;
                });
                html += `</div>`;

                html += `
                    <div><strong>Период действия нового расписания:</strong></div>
                    <div class="mb-3">
                        <label for="dateFrom" class="form-label">От:</label>
                        <input type="date" id="dateFrom" class="form-control" value="${defaultFrom}">
                    </div>
                    <div class="mb-3">
                        <label for="dateTo" class="form-label">До:</label>
                        <input type="date" id="dateTo" class="form-control" value="${defaultTo}">
                    </div>
                    <button type="button" class="btn btn-success" id="btnSaveUserSchedule" data-user-id="${user.id}">
                        Изменить расписание
                    </button>
                </div>`;

                $('#userScheduleModalContent').html(html);
                $('.highlight-border').css('border', '2px dashed #0d6efd');
                userScheduleModal.show();
            },
            error: function () {
                $('#userScheduleModalContent').html('Ошибка AJAX-запроса.');
                userScheduleModal.show();
            }
        });
    });

    let chooseGroupModalEl = document.getElementById('chooseGroupModal');
    let chooseGroupModal = new bootstrap.Modal(chooseGroupModalEl);

  // Вызов Выбрать группу
    $(document).on('click', '#btnChooseGroup', function () {
        let userId = $(this).data('user-id');
        $('#chooseGroupModal').data('user-id', userId);
        $('#selectGroup').val('');
        chooseGroupModal.show();
    });


    //Сохранить группу
    $('#btnSaveUserGroup').on('click', function () {
        let userId = $('#chooseGroupModal').data('user-id');
        let teamId = $('#selectGroup').val();

        $.ajax({
            // url: '/admin/user/' + userId + '/set-group',
            url: '/schedule/user/' + userId + '/set-group',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            data: {team_id: teamId},
            success: function (resp) {
                if (resp.success) {
                    $('.edit-user-schedule[data-user-id="' + userId + '"]').trigger('click');
                    chooseGroupModal.hide();
                    showSuccessModal("Установка группы", "Группа успешно назначена пользователю.", 0);
                } else {
                    $('#errorModal').modal('show');
                }
            }
        });
    });


    //Сохранить Личное расписание ученика
    $(document).on('click', '#btnSaveUserSchedule', function () {
        let userId = $(this).data('user-id');
        showConfirmDeleteModal(
            "Изменение расписания",
            "Вы уверены, что хотите изменить расписание пользователя?",
            function () {
                let checked = [];
                $('.user-day-chk:checked').each(function () {
                    checked.push($(this).val());
                });
                let dateFrom = $('#dateFrom').val();
                let dateTo = $('#dateTo').val();

                $.ajax({
                    // url: '/admin/user/' + userId + '/update-schedule-range',
                    url: '/schedule/user/' + userId + '/update-schedule-range',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    data: {
                        weekdays: checked,
                        date_from: dateFrom,
                        date_to: dateTo
                    },
                    success: function (resp) {
                        if (resp.success) {
                            showSuccessModal("Изменение расписания", "Индивидуальное расписание пользователя изменено.", 1);
                        } else {
                            $('#errorModal').modal('show');
                        }
                    }
                });
            });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    showLogModal("/schedule/logs-data");
});
