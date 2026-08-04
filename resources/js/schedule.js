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
            KidsCrmTooltip.init(scheduleTableEl, { scopes: ['hint'] });
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
        return dateObj.getDate() + ' ' + months[dateObj.getMonth()] + ' ' + dateObj.getFullYear();
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    $('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change', function () {
        var newUrl = new URL(window.location.href);
        newUrl.searchParams.set('year', $('#filter-year').val());
        newUrl.searchParams.set('month', $('#filter-month').val());
        newUrl.searchParams.set('team', $('#filter-team').val());
        if ($('.schedule-fullscreen-wrapper').hasClass('fullscreen')) {
            newUrl.searchParams.set('fullscreen', '1');
        } else {
            newUrl.searchParams.delete('fullscreen');
        }
        window.location.href = newUrl.toString();
    });

    var cellEditModal = new bootstrap.Modal(document.getElementById('cellEditModal'), {});
    var dayOccurrencesModal = new bootstrap.Modal(document.getElementById('dayOccurrencesModal'), {});
    var abonementPlaceModal = new bootstrap.Modal(document.getElementById('abonementPlaceModal'), {});
    var currentCell = null;
    var visitedStatusId = window.SCHEDULE_VISITED_STATUS_ID
        ? parseInt(window.SCHEDULE_VISITED_STATUS_ID, 10)
        : null;
    var cellContextCache = null;
    var abonementContextCache = null;
    var abonementUserId = null;

    var weekdayLabels = [
        {id: 1, label: 'Пн'},
        {id: 2, label: 'Вт'},
        {id: 3, label: 'Ср'},
        {id: 4, label: 'Чт'},
        {id: 5, label: 'Пт'},
        {id: 6, label: 'Сб'},
        {id: 7, label: 'Вс'}
    ];

    function isVisitedStatusId(statusId) {
        return visitedStatusId && parseInt(statusId, 10) === visitedStatusId;
    }

    function populateTrainerSelect(trainers, selectedValue) {
        var $sel = $('#cell-trainer-profile-id');
        $sel.empty();
        $sel.append($('<option>', {value: '', text: 'Без тренера'}));
        (trainers || []).forEach(function (trainer) {
            $sel.append($('<option>', {value: trainer.id, text: trainer.name}));
        });
        $sel.val(selectedValue === null || selectedValue === undefined ? '' : String(selectedValue));
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
        var statusVal = $('input[name="lesson_occurrence_status_id"]:checked').val();
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

    $(document).on('change', 'input[name="lesson_occurrence_status_id"]', syncTrainerBlock);

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

    function clearCellFieldErrors() {
        $('#cell-status-error, #cell-trainer-error, #cell-comment-error').text('').hide();
        $('#cell-trainer-profile-id, #description').removeClass('is-invalid');
    }

    function showCellFieldErrors(errors) {
        clearCellFieldErrors();
        if (!errors) {
            return;
        }
        if (errors.lesson_occurrence_status_id) {
            $('#cell-status-error').text(errors.lesson_occurrence_status_id[0]).show();
        }
        if (errors.trainer_profile_id) {
            $('#cell-trainer-profile-id').addClass('is-invalid');
            $('#cell-trainer-error').text(errors.trainer_profile_id[0]).show();
        }
        if (errors.comment) {
            $('#description').addClass('is-invalid');
            $('#cell-comment-error').text(errors.comment[0]).show();
        }
        if (errors.utss_id) {
            $('#cell-status-error').text(errors.utss_id[0]).show();
        }
        if (errors.lesson_occurrence_status_id) {
            $('#cell-status-error').text(errors.lesson_occurrence_status_id[0]).show();
        }
        if (errors.team_id) {
            $('#cell-status-error').text(errors.team_id[0]).show();
        }
    }

    function hidePostpayTeamUi() {
        $('#edit-postpay-team-wrap').addClass('d-none');
        $('#edit-postpay-team-select').addClass('d-none').empty();
        $('#edit-postpay-team-readonly').addClass('d-none').text('');
        $('#edit-postpay-team-error').text('').hide();
    }

    function renderPostpayTeamUi(ctx, preferredTeamId) {
        var teams = (ctx && ctx.postpay_teams) ? ctx.postpay_teams : [];
        var $wrap = $('#edit-postpay-team-wrap');
        var $select = $('#edit-postpay-team-select');
        var $readonly = $('#edit-postpay-team-readonly');
        $('#edit-postpay-team-error').text('').hide();

        if (!teams.length) {
            hidePostpayTeamUi();
            return;
        }

        $wrap.removeClass('d-none');
        var selectedId = preferredTeamId
            || ctx.postpay_team_id
            || (teams[0] && teams[0].id)
            || '';
        selectedId = selectedId ? String(selectedId) : '';

        if (teams.length === 1) {
            $select.addClass('d-none').empty();
            $readonly.removeClass('d-none').text(teams[0].title || ('Группа #' + teams[0].id));
            $('#edit-team-id').val(String(teams[0].id));
            return;
        }

        $readonly.addClass('d-none').text('');
        $select.removeClass('d-none').empty();
        teams.forEach(function (team) {
            $select.append($('<option>', {
                value: team.id,
                text: team.title || ('Группа #' + team.id)
            }));
        });
        if (selectedId && $select.find('option[value="' + selectedId + '"]').length) {
            $select.val(selectedId);
        }
        $('#edit-team-id').val($select.val() || '');
    }

    $(document).on('change', '#edit-postpay-team-select', function () {
        $('#edit-team-id').val($(this).val() || '');
        $('#edit-postpay-team-error').text('').hide();
    });

    function openOccurrenceEditor(userId, date, utssId, userName, options) {
        options = options || {};
        var createPostpay = !!options.createPostpay;
        clearCellFieldErrors();
        hidePostpayTeamUi();
        $('#edit-user-id').val(userId);
        $('#edit-date').val(date);
        $('#edit-utss-id').val(utssId || '');
        $('#edit-create-postpay').val(createPostpay ? '1' : '0');
        $('#edit-team-id').val(options.teamId || scheduleJournalContextTeamId(currentCell) || '');
        $('#description').val('');
        $('input[name="lesson_occurrence_status_id"]').prop('checked', false);
        $('#edit-user-name-display').text(userName || '');
        $('#edit-date-display').text(formatDateHuman(date));
        $('#edit-user-teams-display').text('');
        $('#edit-occurrence-meta').text(createPostpay ? 'Постоплата' : '');
        cellContextCache = null;
        populateTrainerSelect([], '');
        $('#cell-trainer-wrap').addClass('d-none');

        if (createPostpay && !utssId) {
            $.ajax({
                url: '/schedule/cell-context',
                method: 'GET',
                data: {
                    user_id: userId,
                    date: date,
                    context_team_id: $('#edit-team-id').val() || ''
                },
                headers: {'Accept': 'application/json'},
                success: function (ctx) {
                    cellContextCache = ctx;
                    // Для постоплаты не дублируем полный список групп — показываем целевую группу.
                    $('#edit-user-teams-display').text('');
                    renderPostpayTeamUi(ctx, $('#edit-team-id').val());
                    populateTrainerSelect(ctx.trainers || [], trainerSelectValueForVisited(ctx));
                    syncTrainerBlock();
                    cellEditModal.show();
                },
                error: function () {
                    cellEditModal.show();
                }
            });
            return;
        }

        $.ajax({
            url: '/schedule/cell-context',
            method: 'GET',
            data: {
                user_id: userId,
                date: date,
                utss_id: utssId || '',
                context_team_id: scheduleJournalContextTeamId(currentCell)
            },
            headers: {'Accept': 'application/json'},
            success: function (ctx) {
                cellContextCache = ctx;
                var selected = ctx.selected;
                if (!selected) {
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Нет занятий', 'На эту дату нет записей занятий.');
                    }
                    return;
                }
                $('#edit-create-postpay').val('0');
                hidePostpayTeamUi();
                $('#edit-utss-id').val(selected.utss_id);
                $('#description').val(selected.comment || '');
                if (selected.lesson_occurrence_status_id) {
                    $('input[name="lesson_occurrence_status_id"][value="' + selected.lesson_occurrence_status_id + '"]').prop('checked', true);
                }
                var isPostpayOcc = !!selected.is_postpay;
                var metaParts = [];
                if (selected.team_title) {
                    metaParts.push(selected.team_title);
                }
                // Время слота для постоплаты техническое — не показываем.
                if (!isPostpayOcc && selected.time_start && selected.time_end) {
                    metaParts.push(selected.time_start + '–' + selected.time_end);
                }
                if (selected.package_name) {
                    metaParts.push(selected.package_name);
                } else if (isPostpayOcc) {
                    metaParts.push('Постоплата');
                }
                $('#edit-occurrence-meta').text(metaParts.join(' · '));
                if (isPostpayOcc && selected.team_title) {
                    $('#edit-user-teams-display').text('Группа: ' + selected.team_title);
                } else if (ctx.teams_label) {
                    $('#edit-user-teams-display').text('Группы: ' + ctx.teams_label);
                }
                if (selected.team_id) {
                    $('#edit-team-id').val(selected.team_id);
                }
                populateTrainerSelect(ctx.trainers || [], '');
                syncTrainerBlock();
                cellEditModal.show();
            }
        });
    }

    function renderDayOccurrencesList(ctx, userId, date, userName) {
        var items = ctx.occurrences || [];
        var $body = $('#dayOccurrencesModalBody').empty();
        $('#dayOccurrencesModalLabel').text('Занятия: ' + (userName || '') + ', ' + formatDateHuman(date));
        if (!items.length) {
            $body.append($('<div class="text-muted">').text('Нет занятий на эту дату.'));
            return;
        }
        items.forEach(function (item) {
            var parts = [];
            if (item.team_title) {
                parts.push(item.team_title);
            }
            if (!item.is_postpay && item.time_start && item.time_end) {
                parts.push((item.time_start || '?') + '–' + (item.time_end || '?'));
            }
            parts.push(item.package_name || (item.is_postpay ? 'Постоплата' : ''));
            if (item.status_title) {
                parts.push(item.status_title);
            }
            var label = parts.filter(Boolean).join(' · ');
            var $btn = $('<button type="button" class="btn btn-outline-secondary w-100 mb-2 text-start">')
                .text(label)
                .on('click', function () {
                    dayOccurrencesModal.hide();
                    openOccurrenceEditor(userId, date, item.utss_id, userName);
                });
            $body.append($btn);
        });
    }

    $(document).on('click', '.schedule-cell', function () {
        currentCell = $(this);
        var count = parseInt($(this).attr('data-occurrence-count') || '0', 10);
        var isPostpay = $(this).attr('data-postpay') === '1';
        var isPostpayLocked = $(this).attr('data-postpay-locked') === '1';
        var userId = $(this).data('user-id');
        var date = $(this).data('date');
        var userName = $(this).data('user-name');
        var utssId = $(this).data('utss-id');

        if (isPostpayLocked) {
            return;
        }

        if (!count) {
            if (isPostpay) {
                openOccurrenceEditor(userId, date, '', userName, {
                    createPostpay: true,
                    teamId: scheduleJournalContextTeamId(currentCell)
                });
            }
            return;
        }

        if (count === 1) {
            openOccurrenceEditor(userId, date, utssId, userName);
            return;
        }

        $.ajax({
            url: '/schedule/cell-context',
            method: 'GET',
            data: {
                user_id: userId,
                date: date,
                context_team_id: scheduleJournalContextTeamId(currentCell)
            },
            headers: {'Accept': 'application/json'},
            success: function (ctx) {
                renderDayOccurrencesList(ctx, userId, date, userName);
                dayOccurrencesModal.show();
            }
        });
    });

    $('#cellEditForm').on('submit', function (e) {
        e.preventDefault();
        clearCellFieldErrors();
        $('#edit-postpay-team-error').text('').hide();

        if ($('#edit-create-postpay').val() === '1') {
            if (!$('#edit-postpay-team-select').hasClass('d-none')) {
                $('#edit-team-id').val($('#edit-postpay-team-select').val() || '');
            }
            if (!$('#edit-team-id').val()) {
                $('#edit-postpay-team-error').text('Выберите группу для отметки.').show();
                return;
            }
        }

        var formData = $(this).serializeArray();
        var chosenStatus = $('input[name="lesson_occurrence_status_id"]:checked').val();
        if (!chosenStatus) {
            $('#cell-status-error').text('Выберите статус.').show();
            return;
        }
        if (!isVisitedStatusId(chosenStatus)) {
            formData = formData.filter(function (item) {
                return item.name !== 'trainer_profile_id';
            });
        }

        $.ajax({
            url: '/schedule/update',
            method: 'POST',
            data: $.param(formData),
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (response) {
                if (response.success) {
                    window.location.reload();
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showCellFieldErrors(xhr.responseJSON.errors);
                    if (xhr.responseJSON.errors.team_id) {
                        $('#edit-postpay-team-error').text(xhr.responseJSON.errors.team_id[0]).show();
                    }
                }
            }
        });
    });

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

    function clearAbonementErrors() {
        ['ulp', 'team', 'start-date', 'weekdays'].forEach(function (key) {
            $('#abonement-' + key + '-error').text('').hide();
        });
        $('#abonement-ulp-id, #abonement-team-id, #abonement-start-date').removeClass('is-invalid');
    }

    function showAbonementErrors(errors) {
        clearAbonementErrors();
        if (!errors) {
            return;
        }
        if (errors.user_lesson_package_id) {
            $('#abonement-ulp-id').addClass('is-invalid');
            $('#abonement-ulp-error').text(errors.user_lesson_package_id[0]).show();
        }
        if (errors.team_id) {
            $('#abonement-team-id').addClass('is-invalid');
            $('#abonement-team-error').text(errors.team_id[0]).show();
        }
        if (errors.start_date) {
            $('#abonement-start-date').addClass('is-invalid');
            $('#abonement-start-date-error').text(errors.start_date[0]).show();
        }
        if (errors.weekdays) {
            $('#abonement-weekdays-error').text(errors.weekdays[0]).show();
        }
    }

    function fillAbonementWeekdays(selectedIds) {
        var selected = {};
        (selectedIds || []).forEach(function (id) {
            selected[parseInt(id, 10)] = true;
        });
        var $wrap = $('#abonement-weekdays').empty();
        weekdayLabels.forEach(function (d) {
            var checked = selected[d.id] ? ' checked' : '';
            var highlight = selected[d.id] ? ' border border-primary' : '';
            $wrap.append(
                '<label class="day-checkbox' + highlight + '" style="margin-right:0.25rem;">' +
                '<input class="form-check-input abonement-day-chk" type="checkbox" value="' + d.id + '"' + checked + '> ' +
                d.label +
                '</label>'
            );
        });
    }

    function applyTeamWeekdayTemplate() {
        var teamId = parseInt($('#abonement-team-id').val(), 10);
        var weekdays = [];
        if (abonementContextCache && abonementContextCache.teams) {
            abonementContextCache.teams.forEach(function (team) {
                if (parseInt(team.id, 10) === teamId) {
                    weekdays = team.weekdays || [];
                }
            });
        }
        fillAbonementWeekdays(weekdays);
    }

    function fillAbonementForm(ctx) {
        abonementContextCache = ctx;
        $('#abonement-user-name').text(ctx.user.name || '');
        var $ulp = $('#abonement-ulp-id').empty();
        (ctx.assignments || []).forEach(function (a) {
            var label = a.name + ' (' + a.lessons_remaining + '/' + a.lessons_total + ')';
            var $opt = $('<option>', {value: a.id, text: label});
            if (!a.placeable) {
                $opt.prop('disabled', true);
                $opt.text(label + ' — уже разложен');
            }
            $ulp.append($opt);
        });
        var placeable = (ctx.assignments || []).filter(function (a) { return a.placeable; });
        if (placeable.length) {
            $ulp.val(String(placeable[0].id));
        }

        var $team = $('#abonement-team-id').empty();
        (ctx.teams || []).forEach(function (team) {
            $team.append($('<option>', {value: team.id, text: team.title}));
        });
        if (ctx.teams && ctx.teams.length) {
            $team.val(String(ctx.teams[0].id));
        }
        $('#abonement-start-date').val(ctx.default_start_date || '');
        applyTeamWeekdayTemplate();
        $('#abonement-preview-wrap').hide();
        $('#abonement-preview-text').text('');
        clearAbonementErrors();
    }

    $(document).on('change', '#abonement-team-id', applyTeamWeekdayTemplate);

    $(document).on('click', '.journal-abonement-btn:not(:disabled)', function () {
        abonementUserId = $(this).data('user-id');
        $.ajax({
            url: '/schedule/user/' + abonementUserId + '/abonement-context',
            method: 'GET',
            headers: {'Accept': 'application/json'},
            success: function (ctx) {
                if (!ctx.success) {
                    return;
                }
                fillAbonementForm(ctx);
                abonementPlaceModal.show();
            }
        });
    });

    function collectedWeekdays() {
        var days = [];
        $('.abonement-day-chk:checked').each(function () {
            days.push(parseInt($(this).val(), 10));
        });
        return days;
    }

    function postAbonementPlacement(preview) {
        clearAbonementErrors();
        var payload = {
            user_lesson_package_id: $('#abonement-ulp-id').val(),
            team_id: $('#abonement-team-id').val(),
            start_date: $('#abonement-start-date').val(),
            weekdays: collectedWeekdays(),
            preview: preview ? 1 : 0
        };

        $.ajax({
            url: '/schedule/user/' + abonementUserId + '/place-fixed-abonement',
            method: 'POST',
            data: payload,
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (resp) {
                if (!resp.success) {
                    return;
                }
                if (preview) {
                    var r = resp.result || {};
                    var dates = (r.preview_dates || []).slice(0, 12).join(', ');
                    var more = (r.preview_dates || []).length > 12 ? '…' : '';
                    $('#abonement-preview-text').text(
                        'Занятий: ' + (r.linked_count || 0) +
                        '; период ' + (r.starts_at || '') + ' — ' + (r.ends_at || '') +
                        (dates ? ('; даты: ' + dates + more) : '')
                    );
                    $('#abonement-preview-wrap').show();
                    return;
                }
                abonementPlaceModal.hide();
                window.location.reload();
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showAbonementErrors(xhr.responseJSON.errors);
                }
            }
        });
    }

    $('#btnAbonementPreview').on('click', function () {
        postAbonementPlacement(true);
    });

    $('#abonementPlaceForm').on('submit', function (e) {
        e.preventDefault();
        postAbonementPlacement(false);
    });
});

document.addEventListener('DOMContentLoaded', function () {
    showLogModal('/schedule/logs-data');
});
