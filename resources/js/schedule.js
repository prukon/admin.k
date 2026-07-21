document.addEventListener('DOMContentLoaded', function () {
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
        var checked = $('input[name="lesson_occurrence_status_id"]:checked');
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

    $(document).on('change', 'input[name="lesson_occurrence_status_id"]', function () {
        syncTrainerBlock();
    });

    function scheduleJournalContextTeamId(cellEl) {
        var fromCell = cellEl ? $(cellEl).data('context-team-id') : null;
        if (fromCell) {
            return String(fromCell);
        }
        var filterVal = $('#filter-team').val();
        if (filterVal && filterVal !== 'all' && filterVal !== 'none') {
            return String(filterVal);
        }
        return '';
    }

    function showChooseGroupFieldError(message) {
        var $select = $('#journalUserTeamIds');
        var $feedback = $('[data-error-for="team_ids"]');
        if (message) {
            if (window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.markInvalid($select);
            } else {
                $select.addClass('is-invalid');
            }
            $feedback.text(message).show();
        } else {
            if (window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.clearInvalid($select);
            } else {
                $select.removeClass('is-invalid');
            }
            $feedback.text('').hide();
        }
    }

    function initJournalTeamsMultiselect() {
        var $select = $('#journalUserTeamIds');
        if (!$select.length || !window.KidsCrmGenericMultiselectSelect2) {
            return;
        }
        KidsCrmGenericMultiselectSelect2.init($select, {
            dropdownParent: $('#chooseGroupModal'),
        });
    }

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
        $('input[name="lesson_occurrence_status_id"]').prop('checked', false);
        if (statusId) {
            $('input[name="lesson_occurrence_status_id"][value="' + statusId + '"]').prop('checked', true);
        } else {
            $('#status-empty').prop('checked', true);
        }

        $('#edit-user-name-display').text(userName);
        $('#edit-date-display').text(formatDateHuman(date));
        $('#edit-user-teams-display').text('');

        cellContextCache = null;
        populateTrainerSelect([], '');
        $('#cell-trainer-wrap').addClass('d-none');
        $('#cell-trainer-hint').text('');

        $.ajax({
            url: '/schedule/cell-context',
            method: 'GET',
            data: {
                user_id: userId,
                date: date,
                context_team_id: scheduleJournalContextTeamId(currentCell)
            },
            headers: { 'Accept': 'application/json' },
            success: function (ctx) {
                cellContextCache = ctx;
                populateTrainerSelect(ctx.trainers || [], '');
                syncTrainerBlock();
                if (ctx.teams_label) {
                    $('#edit-user-teams-display').text('Группы: ' + ctx.teams_label);
                }
            },
        });

        cellEditModal.show();
    });

    //Сохранить Редактирование ячейки
    $('#cellEditForm').on('submit', function (e) {
        e.preventDefault();

        var formData = $(this).serializeArray();
        var chosenStatus = $('input[name="lesson_occurrence_status_id"]:checked').val();
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
                    let chosenRadio = $('input[name="lesson_occurrence_status_id"]:checked');
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
            url: '/schedule/user-schedule/' + userId,
            method: 'GET',
            data: {
                context_team_id: scheduleJournalContextTeamId(null)
            },
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
                let hasTeams = Array.isArray(user.team_ids) && user.team_ids.length > 0;
                let teamsLabel = user.team_titles || user.team_title || '';

                let html = `<div><p><strong>ФИО:</strong> ${user.name}</p>`;
                if (!hasTeams) {
                    html += `
                        <p><strong>Группы:</strong> <span class="text-danger">не выбраны</span></p>
                    `;
                } else {
                    html += `<p><strong>Группы:</strong> ${teamsLabel}</p>`;
                }

                html += `
                    <button type="button"
                            class="btn btn-primary mb-3 me-2"
                            id="btnChooseGroup"
                            data-user-id="${user.id}"
                            data-team-ids='${JSON.stringify(user.team_ids || [])}'>
                        Изменить группы
                    </button>
                `;
                if (hasTeams) {
                    html += `
                        <button type="button"
                                class="btn btn-outline-danger mb-3"
                                id="btnDetachAllGroups"
                                data-user-id="${user.id}">
                            Снять все группы
                        </button>
                    `;
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

  // Вызов «Изменить группы»
    $(document).on('click', '#btnChooseGroup', function () {
        let userId = $(this).data('user-id');
        let teamIds = [];
        try {
            teamIds = JSON.parse($(this).attr('data-team-ids') || '[]');
        } catch (e) {
            teamIds = [];
        }
        $('#chooseGroupModal').data('user-id', userId);
        showChooseGroupFieldError('');
        initJournalTeamsMultiselect();
        if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.setValues($('#journalUserTeamIds'), teamIds);
        } else {
            $('#journalUserTeamIds').val(teamIds.map(String));
        }
        chooseGroupModal.show();
    });

    function postUserTeamsSync(userId, teamIds, onSuccess) {
        $.ajax({
            url: '/schedule/user/' + userId + '/sync-teams',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            data: {team_ids: teamIds},
            success: function (resp) {
                if (resp.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(resp);
                    }
                } else {
                    $('#errorModal').modal('show');
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var teamErrors = errors.team_ids || errors['team_ids.0'] || [];
                    var firstError = Array.isArray(teamErrors) ? teamErrors[0] : teamErrors;
                    showChooseGroupFieldError(firstError || 'Проверьте выбранные группы.');
                    return;
                }
                $('#errorModal').modal('show');
            }
        });
    }


    $(document).on('click', '#btnDetachAllGroups', function () {
        let userId = $(this).data('user-id');
        postUserTeamsSync(userId, [], function (resp) {
            showSuccessModal('Группы ученика', resp.message || 'Ученик снят со всех групп.', 1);
        });
    });


    //Сохранить группы
    $('#btnSaveUserGroup').on('click', function () {
        let userId = $('#chooseGroupModal').data('user-id');
        let teamIds = $('#journalUserTeamIds').val() || [];
        if (!Array.isArray(teamIds)) {
            teamIds = teamIds ? [teamIds] : [];
        }
        teamIds = teamIds.map(function (id) {
            return parseInt(id, 10);
        }).filter(function (id) {
            return id > 0;
        });

        showChooseGroupFieldError('');

        postUserTeamsSync(userId, teamIds, function (resp) {
            chooseGroupModal.hide();
            showSuccessModal('Группы ученика', resp.message || 'Группы успешно обновлены.', 1);
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
