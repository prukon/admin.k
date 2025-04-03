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
    var statusesTableBody = document.querySelector('#statuses-table tbody');
    // Кнопка "Новый статус"
    var btnNewStatus = document.getElementById('btn-new-status');

    // При клике "Настройки" — грузим статусы и показываем модалку
    btnSettings.addEventListener('click', function () {
        loadStatuses();
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

    // Загрузка статусов
    function loadStatuses() {
        fetch("/schedule/statuses")
            .then(resp => resp.json())
            .then(data => {
                statusesTableBody.innerHTML = '';
                data.statuses.forEach(st => {
                    let tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            ${st.name}
                            ${
                        st.is_system
                            ? `<i class="fas fa-question-circle ms-1"
                                      data-bs-toggle="tooltip"
                                      title="Системный статус. Невозможно удалить"
                                   ></i>`
                            : ''
                        }
                        </td>
                        <td>
                            ${
                        st.icon
                            ? `<i class="${st.icon}"
                                     style="background-color: ${st.color};
                                            color: #000000;
                                            padding: 5px;
                                            border-radius: 3px;"></i>`
                            : ''
                        }
                        </td>
                        <td>
                            ${
                        st.is_system
                            ? ''
                            : `<button class="btn btn-sm btn-success"
                                           data-action="edit"
                                           data-id="${st.id}"
                                           data-name="${st.name}"
                                           data-icon="${st.icon ?? ''}"
                                           data-color="${st.color ?? ''}">
                                       Изменить
                                   </button>
                                   <button class="btn btn-sm btn-danger"
                                           data-action="delete"
                                           data-id="${st.id}">
                                       Удалить
                                   </button>`
                        }
                        </td>
                    `;
                    statusesTableBody.appendChild(tr);
                });

                // Инициализируем Bootstrap Tooltip
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            })
            .catch(err => console.error(err));
    }

    // СОЗДАНИЕ СТАТУСА
    var createStatusForm = document.getElementById('createStatusForm');
    createStatusForm.addEventListener('submit', function (e) {
        e.preventDefault();
        let formData = new FormData(createStatusForm);

        fetch("/schedule/statuses", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal("Создание статуса", "Статус успешно создан.", 1);
                } else {
                    $('#errorModal').modal('show');
                }
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

            document.getElementById('editStatusId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editIcon').value = icon;
            document.getElementById('editColor').value = color;

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
                                showSuccessModal("Удаление статуса", "Статус успешно удален.", 1);
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

        let statusId = document.getElementById('editStatusId').value;
        let formData = new FormData(editStatusForm);
        formData.append('_method', 'PATCH');

        fetch("/schedule/statuses/" + statusId, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
            .then(resp => resp.json())
            .then(d => {
                if (d.success) {
                    showSuccessModal("Редактирование статуса", "Статус успешно обновлен.", 1);
                } else {
                    $('#errorModal').modal('show');
                }
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

    $('#table-search').on('keyup', function () {
        table.search(this.value).draw();
    });




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

    //Вызов Редактирование ячейки
    $(document).on('click', '.schedule-cell', function () {
        currentCell = $(this);
        let userId = $(this).data('user-id');
        let date = $(this).data('date');
        let statusId = $(this).data('status-id') || '';
        // let comment = $(this).data('comment') || '';
        let comment = $(this).attr('data-comment') || '';;

        console.log(comment);


        $('#edit-user-id').val(userId);
        $('#edit-date').val(date);
        $('#edit-status-id').val(statusId);
        $('#description').val(comment);

        cellEditModal.show();
    })

    //Сохранить Редактирование ячейки
    $('#cellEditForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: "/schedule/update",
            method: "POST",
            data: $(this).serialize(),
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
