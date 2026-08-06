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
    var cellDeleteConfirmModalEl = document.getElementById('cellDeleteConfirmModal');
    var cellDeleteConfirmModal = cellDeleteConfirmModalEl
        ? new bootstrap.Modal(cellDeleteConfirmModalEl, {})
        : null;
    var cellEditPostpayBilling = false;
    var cellEditModalEl = document.getElementById('cellEditModal');
    var DELETE_HINT_ABONEMENT = 'Удаляет занятие ученика в выбранную дату. Счетчик занятия вернется в абонемент';
    var DELETE_HINT_POSTPAY = 'Удаляет занятие ученика в выбранную дату. Стоимость абонемента будет перерасчитана.';
    var DELETE_HINT_GENERIC = 'Удаляет занятие ученика в выбранную дату.';
    var DELETE_HINT_LOCKED = 'Нельзя удалить: абонемент за этот месяц уже оплачен.';

    if (cellDeleteConfirmModalEl) {
        cellDeleteConfirmModalEl.addEventListener('shown.bs.modal', function () {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = '1060';
            }
        });
    }

    cellEditModalEl.addEventListener('shown.bs.modal', function () {
        applyPostpayBillingHints(cellEditPostpayBilling);
        if (window.KidsCrmTooltip) {
            KidsCrmTooltip.init(cellEditModalEl, {scopes: ['hint']});
        }
    });
    cellEditModalEl.addEventListener('hidden.bs.modal', function () {
        cellEditPostpayBilling = false;
        applyPostpayBillingHints(false);
        hideCellDeleteButton();
    });
    var dayOccurrencesModal = new bootstrap.Modal(document.getElementById('dayOccurrencesModal'), {});
    var abonementPlaceModal = new bootstrap.Modal(document.getElementById('abonementPlaceModal'), {});
    var flexiblePlaceModalEl = document.getElementById('flexiblePlaceModal');
    var flexiblePlaceModal = flexiblePlaceModalEl
        ? new bootstrap.Modal(flexiblePlaceModalEl, {})
        : null;
    var currentCell = null;
    var visitedStatusId = window.SCHEDULE_VISITED_STATUS_ID
        ? parseInt(window.SCHEDULE_VISITED_STATUS_ID, 10)
        : null;
    var cellContextCache = null;
    var abonementContextCache = null;
    var abonementUserId = null;
    var flexibleContextCache = null;
    var flexibleUserId = null;

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

    function hideCellDeleteButton() {
        var $wrap = $('#btn-cell-delete-wrap');
        var $btn = $('#btn-cell-delete');
        if (window.KidsCrmTooltip && cellEditModalEl) {
            KidsCrmTooltip.dispose(cellEditModalEl, {scopes: ['hint']});
        }
        $btn.prop('disabled', false).removeAttr('aria-disabled');
        $btn.removeAttr('title data-kids-tooltip-hint data-bs-toggle data-bs-placement data-bs-custom-class');
        $wrap.removeAttr('title data-kids-tooltip-hint data-bs-toggle data-bs-placement data-bs-custom-class tabindex');
        $wrap.addClass('d-none');
        $('#cell-delete-error').text('').hide();
    }

    function syncCellDeleteButton(selected, options) {
        options = options || {};
        var $wrap = $('#btn-cell-delete-wrap');
        var $btn = $('#btn-cell-delete');
        var utssId = selected && selected.utss_id ? selected.utss_id : $('#edit-utss-id').val();
        if (!utssId || options.createPostpay) {
            hideCellDeleteButton();
            return;
        }

        var isPostpay = occurrenceIsPostpayBilling(selected);
        var hasUlp = !!(selected && selected.user_lesson_package_id);
        var hint = DELETE_HINT_GENERIC;
        if (isPostpay) {
            hint = DELETE_HINT_POSTPAY;
        } else if (hasUlp) {
            hint = DELETE_HINT_ABONEMENT;
        }

        var locked = !!(currentCell && currentCell.attr('data-postpay-locked') === '1' && isPostpay);
        if (locked) {
            hint = DELETE_HINT_LOCKED;
        }

        if (window.KidsCrmTooltip && cellEditModalEl) {
            KidsCrmTooltip.dispose(cellEditModalEl, {scopes: ['hint']});
        }

        // Disabled button не получает hover — tooltip на обёртке.
        $wrap.removeAttr('data-kids-tooltip-hint data-bs-toggle data-bs-placement data-bs-custom-class title');
        $btn.removeAttr('data-kids-tooltip-hint data-bs-toggle data-bs-placement data-bs-custom-class');

        if (locked) {
            $btn.prop('disabled', true).attr('aria-disabled', 'true').attr('title', '');
            $wrap
                .attr('data-kids-tooltip-hint', '1')
                .attr('data-bs-toggle', 'tooltip')
                .attr('data-bs-placement', 'top')
                .attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip')
                .attr('title', hint)
                .attr('tabindex', '0');
        } else {
            $btn.prop('disabled', false).removeAttr('aria-disabled');
            $btn
                .attr('data-kids-tooltip-hint', '1')
                .attr('data-bs-toggle', 'tooltip')
                .attr('data-bs-placement', 'top')
                .attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip')
                .attr('title', hint);
            $wrap.removeAttr('tabindex');
        }
        $wrap.removeClass('d-none');
        $('#cell-delete-error').text('').hide();

        if (window.KidsCrmTooltip && cellEditModalEl) {
            KidsCrmTooltip.init(cellEditModalEl, {scopes: ['hint']});
        }
    }

    var POSTPAY_BILLING_HINT_SELECTOR = '.cell-status-postpay-billing-hint';

    function occurrenceIsPostpayBilling(selected) {
        if (!selected) {
            return false;
        }
        if (selected.is_postpay === true || selected.is_postpay === 1 || selected.is_postpay === '1') {
            return true;
        }
        var ulp = selected.user_lesson_package_id;
        var hasUlp = ulp !== null && ulp !== undefined && ulp !== '' && Number(ulp) > 0;
        if (hasUlp || selected.is_trial_lesson) {
            return false;
        }
        // Fallback: ученик на постоплате в месяце, занятие без абонемента.
        return !!(currentCell && currentCell.attr('data-postpay') === '1');
    }

    function applyPostpayBillingHints(isPostpay) {
        var modalEl = document.getElementById('cellEditModal');
        if (!modalEl) {
            return;
        }

        if (window.KidsCrmTooltip) {
            KidsCrmTooltip.dispose(modalEl, { scopes: ['hint'] });
        }

        modalEl.querySelectorAll(POSTPAY_BILLING_HINT_SELECTOR).forEach(function (el) {
            if (isPostpay) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });

        if (isPostpay && window.KidsCrmTooltip) {
            KidsCrmTooltip.init(modalEl, { scopes: ['hint'] });
        }
    }

    function syncPostpayBillingHints(isPostpay) {
        cellEditPostpayBilling = !!isPostpay;
        applyPostpayBillingHints(cellEditPostpayBilling);
    }

    function renderPostpayTeamUi(ctx, preferredTeamId) {
        var teams = (ctx && ctx.postpay_teams) ? ctx.postpay_teams : [];
        var $wrap = $('#edit-postpay-team-wrap');
        var $select = $('#edit-postpay-team-select');
        var $readonly = $('#edit-postpay-team-readonly');
        $('#edit-postpay-team-error').text('').hide();

        if (!teams.length) {
            hidePostpayTeamUi();
            syncPostpayCreateMeta();
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
            syncPostpayCreateMeta();
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
        syncPostpayCreateMeta();
    }

    $(document).on('change', '#edit-postpay-team-select', function () {
        $('#edit-team-id').val($(this).val() || '');
        $('#edit-postpay-team-error').text('').hide();
        syncPostpayCreateMeta();
    });

    function formatPostpayLessonPrice(price) {
        var n = Number(price);
        if (!Number.isFinite(n) || n < 0) {
            return '';
        }
        var label = Math.abs(n - Math.round(n)) < 0.001
            ? String(Math.round(n))
            : n.toFixed(2);
        return label + ' ₽ / занятие';
    }

    /** Если в названии шаблона уже есть ставка («Постоплата — 800 ₽/занятие») — не дублируем. */
    function packageNameHasLessonPrice(name) {
        return /₽/.test(String(name || ''));
    }

    function appendPostpayPricePart(parts, packageName, price) {
        var priceLabel = formatPostpayLessonPrice(price);
        if (!priceLabel || packageNameHasLessonPrice(packageName)) {
            return;
        }
        parts.push(priceLabel);
    }

    function findPostpayTeamById(teamId) {
        var teams = (cellContextCache && cellContextCache.postpay_teams) || [];
        var id = teamId != null ? String(teamId) : '';
        if (!id) {
            return null;
        }
        for (var i = 0; i < teams.length; i++) {
            if (String(teams[i].id) === id) {
                return teams[i];
            }
        }
        return null;
    }

    function syncPostpayCreateMeta() {
        if ($('#edit-create-postpay').val() !== '1') {
            return;
        }
        var team = findPostpayTeamById($('#edit-team-id').val());
        var parts = [];
        if (team && team.title) {
            parts.push(team.title);
        }
        var packageName = (team && team.package_name) ? team.package_name : 'Постоплата';
        parts.push(packageName);
        appendPostpayPricePart(parts, packageName, team ? team.price_per_lesson : null);
        $('#edit-occurrence-meta').text(parts.join(' · '));
    }

    function hideAddFlexibleButton() {
        $('#edit-add-flexible-wrap').addClass('d-none');
    }

    function showAddFlexibleButtonIfNeeded(fromCell) {
        hideAddFlexibleButton();
        var cell = fromCell || currentCell;
        if (!cell || !$(cell).length) {
            return;
        }
        if ($(cell).attr('data-flexible') !== '1') {
            return;
        }
        $('#edit-add-flexible-wrap').removeClass('d-none');
    }

    function clearFlexibleErrors() {
        $('#flexible-ulp-error, #flexible-date-error, #flexible-team-error, #flexible-status-error, #flexible-trainer-error, #flexible-comment-error')
            .text('').hide();
        $('#flexible-team-id, #flexible-trainer-profile-id, #flexible-comment').removeClass('is-invalid');
    }

    function showFlexibleErrors(errors) {
        clearFlexibleErrors();
        if (!errors) {
            return;
        }
        if (errors.user_lesson_package_id) {
            $('#flexible-ulp-error').text(errors.user_lesson_package_id[0]).show();
        }
        if (errors.occurrence_date) {
            $('#flexible-date-error').text(errors.occurrence_date[0]).show();
        }
        if (errors.team_id) {
            $('#flexible-team-id').addClass('is-invalid');
            $('#flexible-team-error').text(errors.team_id[0]).show();
        }
        if (errors.lesson_occurrence_status_id) {
            $('#flexible-status-error').text(errors.lesson_occurrence_status_id[0]).show();
        }
        if (errors.trainer_profile_id) {
            $('#flexible-trainer-profile-id').addClass('is-invalid');
            $('#flexible-trainer-error').text(errors.trainer_profile_id[0]).show();
        }
        if (errors.comment) {
            $('#flexible-comment').addClass('is-invalid');
            $('#flexible-comment-error').text(errors.comment[0]).show();
        }
    }

    function populateFlexibleTrainerSelect(trainers, selectedValue) {
        var $sel = $('#flexible-trainer-profile-id');
        $sel.empty();
        $sel.append($('<option>', {value: '', text: 'Без тренера'}));
        (trainers || []).forEach(function (trainer) {
            $sel.append($('<option>', {value: trainer.id, text: trainer.name}));
        });
        $sel.val(selectedValue === null || selectedValue === undefined ? '' : String(selectedValue));
    }

    function syncFlexibleTrainerBlock() {
        var statusVal = $('input[name="flexible_lesson_occurrence_status_id"]:checked').val();
        if (!isVisitedStatusId(statusVal)) {
            $('#flexible-trainer-wrap').addClass('d-none');
            $('#flexible-trainer-profile-id').val('');
            $('#flexible-trainer-hint').text('');
            return;
        }
        $('#flexible-trainer-wrap').removeClass('d-none');
        var selectVal = '';
        if (flexibleContextCache && flexibleContextCache.team_default_trainer_profile_id) {
            selectVal = String(flexibleContextCache.team_default_trainer_profile_id);
        }
        if ($('#flexible-trainer-profile-id option').length <= 1 && flexibleContextCache && flexibleContextCache.trainers) {
            populateFlexibleTrainerSelect(flexibleContextCache.trainers, selectVal);
        } else {
            $('#flexible-trainer-profile-id').val(selectVal);
        }
        var hint = '';
        if (flexibleContextCache && flexibleContextCache.team_default_trainer_profile_id && selectVal === String(flexibleContextCache.team_default_trainer_profile_id)) {
            hint = 'По умолчанию — первый тренер группы.';
        }
        $('#flexible-trainer-hint').text(hint);
    }

    $(document).on('change', 'input[name="flexible_lesson_occurrence_status_id"]', syncFlexibleTrainerBlock);

    function resetFlexibleStatusDefault() {
        var scheduledId = flexibleContextCache && flexibleContextCache.scheduled_status_id
            ? String(flexibleContextCache.scheduled_status_id)
            : null;
        var $radios = $('input[name="flexible_lesson_occurrence_status_id"]');
        if (scheduledId && $radios.filter('[value="' + scheduledId + '"]').length) {
            $radios.filter('[value="' + scheduledId + '"]').prop('checked', true);
        } else if ($radios.filter(':checked').length === 0 && $radios.length) {
            $radios.first().prop('checked', true);
        }
        syncFlexibleTrainerBlock();
    }

    function assignmentForFlexibleTeam(teamId) {
        if (!flexibleContextCache || !Array.isArray(flexibleContextCache.assignments)) {
            return null;
        }
        var id = parseInt(teamId, 10);
        for (var i = 0; i < flexibleContextCache.assignments.length; i++) {
            if (parseInt(flexibleContextCache.assignments[i].team_id, 10) === id) {
                return flexibleContextCache.assignments[i];
            }
        }
        return null;
    }

    function syncFlexiblePackageSummary() {
        var teamId = $('#flexible-team-id').val() || (flexibleContextCache && flexibleContextCache.team_id);
        var row = assignmentForFlexibleTeam(teamId) || (flexibleContextCache && flexibleContextCache.assignment);
        if (!row) {
            $('#flexible-package-summary').text('Нет доступного гибкого абонемента на эту дату.');
            $('#flexible-ulp-id').val('');
            $('#btnFlexiblePlace').prop('disabled', true);
            return;
        }
        $('#flexible-ulp-id').val(row.id);
        $('#flexible-package-summary').text(
            '"' + (row.name || 'Гибкий абонемент') + '" — осталось занятий к назначению: ' +
            (row.slots_remaining != null ? row.slots_remaining : '?')
        );
        $('#btnFlexiblePlace').prop('disabled', false);
    }

    function renderFlexibleTeamUi(ctx, preferredTeamId) {
        var teams = ctx.teams || [];
        var $select = $('#flexible-team-id');
        var $readonly = $('#flexible-team-readonly');
        $select.empty().removeClass('d-none');
        $readonly.addClass('d-none').text('');

        teams.forEach(function (team) {
            $select.append($('<option>', {value: team.id, text: team.title || ('Группа #' + team.id)}));
        });

        var resolved = preferredTeamId || ctx.team_id || (teams[0] && teams[0].id) || '';
        if (resolved) {
            $select.val(String(resolved));
        }

        if (teams.length <= 1) {
            $select.addClass('d-none');
            $readonly.removeClass('d-none').text(
                teams.length === 1 ? (teams[0].title || ('Группа #' + teams[0].id)) : '—'
            );
        }

        syncFlexiblePackageSummary();
        syncFlexibleTrainerBlock();
    }

    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function rebuildFlexibleHintTooltip($hint, items) {
        if (!$hint || !$hint.length) {
            return;
        }
        var lines = [];
        (items || []).forEach(function (item) {
            if (!item || Number(item.slots_remaining) < 1) {
                return;
            }
            lines.push(
                String(item.slots_remaining) + '/' + String(item.lessons_total) +
                ' остаток занятий в текущем месяце по абонементу "' + (item.name || 'Гибкий абонемент') + '"'
            );
        });
        if (!lines.length) {
            $hint.remove();
            return;
        }
        var title = lines.join('\n');
        $hint.attr('title', title).attr('aria-label', title);
        if ($hint.hasClass('journal-flexible-hint--ratio') && items.length === 1) {
            var only = items[0];
            $hint.text(String(only.slots_remaining) + '/' + String(only.lessons_total));
            $hint.attr('data-slots-remaining', only.slots_remaining);
            $hint.attr('data-lessons-total', only.lessons_total);
            title = 'Остаток занятий в текущем месяце по абонементу "' + (only.name || 'Гибкий абонемент') + '"';
            $hint.attr('title', title).attr('aria-label', title);
        } else if ($hint.hasClass('journal-flexible-hint--multi')) {
            $hint.attr('data-flexible-items', JSON.stringify(items.filter(function (item) {
                return item && Number(item.slots_remaining) > 0;
            })));
        }
        if (window.KidsCrmTooltip && document.getElementById('schedule-table')) {
            KidsCrmTooltip.init(document.getElementById('schedule-table'), { scopes: ['hint'] });
        }
    }

    function updateFlexibleHintAfterPlace(userId, result) {
        var $row = $('#schedule-table tbody tr[data-user-id="' + userId + '"]');
        if (!$row.length) {
            return;
        }
        var $hint = $row.find('.journal-flexible-hint').first();
        var remaining = Number(result.slots_remaining || 0);
        var total = Number(result.lessons_total || 0);
        var ulpId = Number(result.user_lesson_package_id || 0);

        if (!$hint.length) {
            if (remaining < 1) {
                $row.find('.schedule-cell[data-flexible="1"]').attr('data-flexible', '0')
                    .find('i.schedule-cell-empty-dot.text-primary').remove();
            }
            return;
        }

        if ($hint.hasClass('journal-flexible-hint--ratio')) {
            if (remaining < 1) {
                $hint.remove();
                $row.find('.schedule-cell[data-flexible="1"]').attr('data-flexible', '0')
                    .find('i.schedule-cell-empty-dot.text-primary').remove();
                return;
            }
            rebuildFlexibleHintTooltip($hint, [{
                id: ulpId,
                name: $hint.attr('data-package-name') || 'Гибкий абонемент',
                slots_remaining: remaining,
                lessons_total: total || Number($hint.attr('data-lessons-total') || 0)
            }]);
            return;
        }

        var items = [];
        try {
            items = JSON.parse($hint.attr('data-flexible-items') || '[]');
        } catch (e) {
            items = [];
        }
        items = (items || []).map(function (item) {
            if (Number(item.id) === ulpId) {
                return {
                    id: ulpId,
                    name: item.name,
                    slots_remaining: remaining,
                    lessons_total: total || item.lessons_total
                };
            }
            return item;
        }).filter(function (item) {
            return Number(item.slots_remaining) > 0;
        });

        if (!items.length) {
            $hint.remove();
            $row.find('.schedule-cell[data-flexible="1"]').attr('data-flexible', '0')
                .find('i.schedule-cell-empty-dot.text-primary').remove();
            return;
        }
        rebuildFlexibleHintTooltip($hint, items);
    }

    /**
     * Точечный DOM-апдейт ячейки журнала после create/update/delete статуса.
     * options.increment = true — новое занятие (flexible place / create_postpay);
     * options.deleted = true — аннуляция;
     * иначе — правка существующего (при count > 1 визуал ×N не меняется).
     */
    function renderScheduleCellFromResult($cell, result, options) {
        if (!$cell || !$cell.length || !result) {
            return;
        }
        options = options || {};
        if (options.deleted === true || result.deleted === true) {
            renderScheduleCellAfterDelete($cell, result);
            return;
        }
        var increment = options.increment === true;
        var prevCount = parseInt($cell.attr('data-occurrence-count') || '0', 10);
        if (!increment && prevCount > 1) {
            return;
        }
        var count = increment ? prevCount + 1 : Math.max(prevCount, 1);
        paintScheduleCellOccurrence($cell, count, {
            utss_id: result.utss_id,
            comment: result.comment || '',
            status: result.status || {}
        });
    }

    function renderScheduleCellAfterDelete($cell, result) {
        var count = parseInt(result.occurrence_count, 10);
        if (isNaN(count)) {
            count = Math.max(0, parseInt($cell.attr('data-occurrence-count') || '0', 10) - 1);
        }
        $cell.find('.schedule-cell-empty-dot').remove();
        $cell.find('.schedule-cell__swatch').remove();
        $cell.find('.cell-comment-indicator').remove();

        if (count <= 0) {
            $cell.attr('data-occurrence-count', '0');
            $cell.removeAttr('data-utss-id');
            $cell.removeAttr('data-status-id');
            $cell.removeAttr('data-comment');
            if ($cell.attr('data-flexible') === '1' || result.is_flexible) {
                $cell.attr('data-flexible', '1');
                $cell.css('cursor', 'pointer');
                $cell.append(
                    '<i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" ' +
                    'title="Гибкий абонемент: поставить занятие"></i>'
                );
            } else if ($cell.attr('data-postpay') === '1' || result.is_postpay) {
                $cell.attr('data-postpay', '1');
                $cell.css('cursor', 'pointer');
                $cell.append(
                    '<i class="fa-regular fa-circle text-muted schedule-cell-empty-dot" style="opacity: 0.45;" ' +
                    'title="Постоплата: отметить посещение"></i>'
                );
            }
            return;
        }

        if (count === 1 && result.remaining) {
            paintScheduleCellOccurrence($cell, 1, {
                utss_id: result.remaining.utss_id,
                comment: result.remaining.comment || '',
                status: result.remaining.status || {}
            });
            return;
        }

        paintScheduleCellOccurrence($cell, count, null);
    }

    function paintScheduleCellOccurrence($cell, count, payload) {
        $cell.attr('data-occurrence-count', String(count));
        $cell.css('cursor', 'pointer');
        $cell.find('.schedule-cell-empty-dot').remove();
        $cell.find('.schedule-cell__swatch').remove();
        $cell.find('.cell-comment-indicator').remove();

        if (count === 1 && payload) {
            var status = payload.status || {};
            var color = status.color || '#e9ecef';
            var icon = status.icon || '';
            var title = status.title || '';
            var comment = payload.comment || '';
            $cell.attr('data-utss-id', payload.utss_id);
            $cell.attr('data-status-id', status.id || '');
            $cell.attr('data-comment', comment);
            var inner = '';
            if (icon) {
                inner = '<i class="' + escapeHtml(icon) + ' schedule-cell-status-icon" aria-hidden="true"></i>';
            } else {
                inner = escapeHtml(title || '•');
            }
            $cell.append(
                '<span class="schedule-cell__swatch" style="background-color: ' + escapeHtml(color) + ';">' +
                inner +
                '</span>'
            );
            if (comment) {
                $cell.append(
                    '<div class="cell-comment-indicator" style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 5px solid red; border-left: 5px solid transparent;"></div>'
                );
            }
            return;
        }

        $cell.removeAttr('data-utss-id');
        $cell.removeAttr('data-status-id');
        $cell.removeAttr('data-comment');
        $cell.append(
            '<span class="schedule-cell__swatch" style="background-color: #e9ecef;">' +
            '<span class="badge bg-primary">×' + count + '</span>' +
            '</span>'
        );
    }

    function renderScheduleCellAfterFlexiblePlace($cell, result) {
        renderScheduleCellFromResult($cell, result, {increment: true});
    }

    function renderScheduleCellAfterStatusSave($cell, result) {
        var created = !!(result && result.created);
        renderScheduleCellFromResult($cell, result, {increment: created});
    }

    function syncFlexibleHintAfterAnnul(userId, result) {
        if (!result || !result.is_flexible || !result.user_lesson_package_id) {
            return;
        }

        var remaining = Number(result.slots_remaining);
        if (isNaN(remaining)) {
            return;
        }

        var $row = $('#schedule-table tbody tr[data-user-id="' + userId + '"]');
        if (!$row.length) {
            return;
        }

        if (remaining > 0) {
            $row.find('.schedule-cell').each(function () {
                var $c = $(this);
                var occ = parseInt($c.attr('data-occurrence-count') || '0', 10);
                if (occ === 0) {
                    $c.attr('data-flexible', '1');
                    if (!$c.find('.schedule-cell__swatch').length && !$c.find('.schedule-cell-empty-dot.text-primary').length) {
                        $c.find('.schedule-cell-empty-dot').remove();
                        $c.append(
                            '<i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" ' +
                            'title="Гибкий абонемент: поставить занятие"></i>'
                        );
                    }
                }
            });
        }

        var $hint = $row.find('.journal-flexible-hint').first();
        if ($hint.length || remaining < 1) {
            updateFlexibleHintAfterPlace(userId, result);
            return;
        }

        var $host = $row.find('.journal-abonement-cell');
        if (!$host.length) {
            return;
        }
        var total = Number(result.lessons_total || 0);
        var name = result.package_name || 'Гибкий абонемент';
        var ratio = remaining + '/' + (total || remaining);
        var title = 'Остаток занятий в текущем месяце по абонементу "' + name + '"';
        var $span = $(
            '<span class="kids-tooltip-hint text-muted journal-flexible-hint journal-flexible-hint--ratio" ' +
            'tabindex="0" role="img"></span>'
        );
        $span
            .attr('aria-label', title)
            .attr('title', title)
            .attr('data-kids-tooltip-hint', '1')
            .attr('data-bs-toggle', 'tooltip')
            .attr('data-bs-placement', 'top')
            .attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip')
            .attr('data-flexible-ulp-id', String(result.user_lesson_package_id))
            .attr('data-slots-remaining', String(remaining))
            .attr('data-lessons-total', String(total))
            .attr('data-package-name', name)
            .text(ratio);
        $host.append($span);
        if (window.KidsCrmTooltip && document.getElementById('schedule-table')) {
            KidsCrmTooltip.init(document.getElementById('schedule-table'), {scopes: ['hint']});
        }
    }

    function openFlexiblePlaceModal(userId, date, userName, options) {
        if (!flexiblePlaceModal) {
            return;
        }
        options = options || {};
        clearFlexibleErrors();
        flexibleUserId = userId;
        flexibleContextCache = null;
        $('#flexible-user-id').val(userId);
        $('#flexible-user-name').text(userName || '');
        $('#flexible-occurrence-date').val(date);
        $('#flexible-date-display').text(formatDateHuman(date));
        $('#flexible-package-summary').text('Загрузка…');
        $('#flexible-ulp-id').val('');
        $('#flexible-comment').val('');
        $('#btnFlexiblePlace').prop('disabled', true);
        $('#flexible-trainer-wrap').addClass('d-none');

        var contextTeamId = options.teamId || scheduleJournalContextTeamId(currentCell) || '';

        $.ajax({
            url: '/schedule/user/' + userId + '/flexible-context',
            method: 'GET',
            data: {
                occurrence_date: date,
                context_team_id: contextTeamId
            },
            headers: {'Accept': 'application/json'},
            success: function (ctx) {
                flexibleContextCache = ctx;
                if (ctx.visited_status_id) {
                    visitedStatusId = parseInt(ctx.visited_status_id, 10) || visitedStatusId;
                }
                resetFlexibleStatusDefault();
                if (!ctx.can_place || !(ctx.assignments || []).length) {
                    $('#flexible-package-summary').text('Нет доступного гибкого абонемента на эту дату.');
                    renderFlexibleTeamUi({teams: []}, '');
                    flexiblePlaceModal.show();
                    return;
                }
                populateFlexibleTrainerSelect(ctx.trainers || [], '');
                renderFlexibleTeamUi(ctx, contextTeamId);
                flexiblePlaceModal.show();
            },
            error: function () {
                $('#flexible-package-summary').text('Не удалось загрузить данные абонемента.');
                flexiblePlaceModal.show();
            }
        });
    }

    $('#flexible-team-id').on('change', function () {
        syncFlexiblePackageSummary();
        syncFlexibleTrainerBlock();
    });

    $('#btn-add-flexible-lesson').on('click', function () {
        var userId = $('#edit-user-id').val();
        var date = $('#edit-date').val();
        var userName = $('#edit-user-name-display').text();
        if (!userId || !date) {
            return;
        }
        cellEditModal.hide();
        openFlexiblePlaceModal(userId, date, userName, {
            teamId: $('#edit-team-id').val() || scheduleJournalContextTeamId(currentCell)
        });
    });

    $('#flexiblePlaceForm').on('submit', function (e) {
        e.preventDefault();
        clearFlexibleErrors();
        if (!$('#flexible-team-id').hasClass('d-none')) {
            // keep select value
        } else if (flexibleContextCache && flexibleContextCache.team_id) {
            $('#flexible-team-id').val(String(flexibleContextCache.team_id));
        }
        syncFlexiblePackageSummary();

        var userId = $('#flexible-user-id').val() || flexibleUserId;
        var ulpId = $('#flexible-ulp-id').val();
        var teamId = $('#flexible-team-id').val();
        var date = $('#flexible-occurrence-date').val();
        var statusId = $('input[name="flexible_lesson_occurrence_status_id"]:checked').val();
        var trainerId = '';
        if (isVisitedStatusId(statusId)) {
            trainerId = $('#flexible-trainer-profile-id').val() || '';
        }
        var comment = $('#flexible-comment').val() || '';

        if (!teamId) {
            $('#flexible-team-error').text('Выберите группу.').show();
            return;
        }
        if (!ulpId) {
            $('#flexible-ulp-error').text('Нет доступного гибкого абонемента.').show();
            return;
        }
        if (!statusId) {
            $('#flexible-status-error').text('Выберите статус.').show();
            return;
        }

        $('#btnFlexiblePlace').prop('disabled', true);

        $.ajax({
            url: '/schedule/user/' + userId + '/place-flexible-abonement',
            method: 'POST',
            data: {
                user_lesson_package_id: ulpId,
                team_id: teamId,
                occurrence_date: date,
                lesson_occurrence_status_id: statusId,
                trainer_profile_id: trainerId,
                comment: comment
            },
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (response) {
                if (response.success) {
                    flexiblePlaceModal.hide();
                    var result = response.result || {};
                    var $cell = currentCell && currentCell.length
                        ? currentCell
                        : $('#schedule-table .schedule-cell[data-user-id="' + userId + '"][data-date="' + date + '"]');
                    renderScheduleCellAfterFlexiblePlace($cell, result);
                    updateFlexibleHintAfterPlace(userId, result);
                    currentCell = $cell;
                    return;
                }
                $('#btnFlexiblePlace').prop('disabled', false);
                showFlexibleErrors(response.errors || {});
            },
            error: function (xhr) {
                $('#btnFlexiblePlace').prop('disabled', false);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showFlexibleErrors(xhr.responseJSON.errors);
                    return;
                }
                $('#flexible-ulp-error').text('Не удалось поставить занятие.').show();
            }
        });
    });

    function openOccurrenceEditor(userId, date, utssId, userName, options) {
        options = options || {};
        var createPostpay = !!options.createPostpay;
        clearCellFieldErrors();
        hidePostpayTeamUi();
        hideAddFlexibleButton();
        hideCellDeleteButton();
        syncPostpayBillingHints(false);
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
                    syncPostpayBillingHints(true);
                    hideCellDeleteButton();
                    cellEditModal.show();
                },
                error: function () {
                    syncPostpayBillingHints(true);
                    hideCellDeleteButton();
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
                var isPostpayOcc = occurrenceIsPostpayBilling(selected);
                // В журнале время слота техническое — не показываем (только дата / группа / абонемент).
                var metaParts = [];
                if (selected.team_title) {
                    metaParts.push(selected.team_title);
                }
                if (selected.package_name) {
                    metaParts.push(selected.package_name);
                } else if (isPostpayOcc) {
                    metaParts.push('Постоплата');
                }
                if (isPostpayOcc) {
                    var packageNameForPrice = selected.package_name || '';
                    var priceSource = selected.price_per_lesson;
                    if (priceSource == null || priceSource === '') {
                        var postpayTeam = findPostpayTeamById(
                            selected.team_id || $('#edit-team-id').val()
                        );
                        if (postpayTeam) {
                            priceSource = postpayTeam.price_per_lesson;
                            if (!packageNameForPrice && postpayTeam.package_name) {
                                packageNameForPrice = postpayTeam.package_name;
                            }
                        }
                    }
                    appendPostpayPricePart(metaParts, packageNameForPrice, priceSource);
                }
                $('#edit-occurrence-meta').text(metaParts.join(' · '));
                $('#edit-user-teams-display').text('');
                if (selected.team_id) {
                    $('#edit-team-id').val(selected.team_id);
                }
                populateTrainerSelect(ctx.trainers || [], '');
                syncTrainerBlock();
                syncPostpayBillingHints(isPostpayOcc);
                showAddFlexibleButtonIfNeeded(currentCell);
                syncCellDeleteButton(selected, {createPostpay: false});
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

        if (currentCell && $(currentCell).attr('data-flexible') === '1') {
            var $addFlex = $('<button type="button" class="btn btn-outline-primary w-100 mt-2">')
                .html('<i class="fa-solid fa-plus me-1"></i>Добавить занятие из гибкого абонемента')
                .on('click', function () {
                    dayOccurrencesModal.hide();
                    openFlexiblePlaceModal(userId, date, userName, {
                        teamId: scheduleJournalContextTeamId(currentCell)
                    });
                });
            $body.append($addFlex);
        }
    }

    $(document).on('click', '.schedule-cell', function () {
        currentCell = $(this);
        var count = parseInt($(this).attr('data-occurrence-count') || '0', 10);
        var isPostpay = $(this).attr('data-postpay') === '1';
        var isPostpayLocked = $(this).attr('data-postpay-locked') === '1';
        var isFlexible = $(this).attr('data-flexible') === '1';
        var userId = $(this).data('user-id');
        var date = $(this).data('date');
        var userName = $(this).data('user-name');
        var utssId = $(this).data('utss-id');

        if (isPostpayLocked) {
            return;
        }

        if (!count) {
            if (isFlexible) {
                openFlexiblePlaceModal(userId, date, userName, {
                    teamId: scheduleJournalContextTeamId(currentCell)
                });
                return;
            }
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
                if (!response.success) {
                    return;
                }
                var result = response.result || {};
                var userId = $('#edit-user-id').val();
                var date = $('#edit-date').val();
                var $cell = currentCell && currentCell.length
                    ? currentCell
                    : $('#schedule-table .schedule-cell[data-user-id="' + userId + '"][data-date="' + date + '"]');
                cellEditModal.hide();
                renderScheduleCellAfterStatusSave($cell, result);
                currentCell = $cell;
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

    $('#btn-cell-delete').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }
        var utssId = $('#edit-utss-id').val();
        if (!utssId || !cellDeleteConfirmModal) {
            return;
        }
        var userName = $('#edit-user-name-display').text() || '';
        var dateHuman = $('#edit-date-display').text() || '';
        var statusTitle = $('input[name="lesson_occurrence_status_id"]:checked')
            .closest('.cell-status-option, .form-check')
            .find('.cell-status-option__title, .form-check-label .ms-1')
            .first()
            .text() || '';
        var selected = cellContextCache && cellContextCache.selected ? cellContextCache.selected : null;
        var contextParts = [];
        if (selected && selected.team_title) {
            contextParts.push(selected.team_title);
        }
        if (selected && selected.package_name) {
            contextParts.push(selected.package_name);
        } else if (occurrenceIsPostpayBilling(selected)) {
            contextParts.push('Постоплата');
        }
        var contextText = contextParts.join(' · ');

        $('#cell-delete-confirm-name').text(userName);
        $('#cell-delete-confirm-date').text(dateHuman);

        var $statusChip = $('#cell-delete-confirm-status');
        var $contextChip = $('#cell-delete-confirm-context');
        var $chips = $('#cell-delete-confirm-chips');
        var hasChips = false;
        if (statusTitle) {
            $statusChip.text(statusTitle).removeClass('d-none');
            hasChips = true;
        } else {
            $statusChip.text('').addClass('d-none');
        }
        if (contextText) {
            $contextChip.text(contextText).removeClass('d-none');
            hasChips = true;
        } else {
            $contextChip.text('').addClass('d-none');
        }
        $chips.toggleClass('d-none', !hasChips);

        var hint = DELETE_HINT_GENERIC;
        if (occurrenceIsPostpayBilling(selected)) {
            hint = DELETE_HINT_POSTPAY;
        } else if (selected && selected.user_lesson_package_id) {
            hint = DELETE_HINT_ABONEMENT;
        }
        $('#cell-delete-confirm-hint').text(hint);
        cellDeleteConfirmModal.show();
    });

    $('#btn-cell-delete-confirm').on('click', function () {
        var utssId = $('#edit-utss-id').val();
        var date = $('#edit-date').val();
        var userId = $('#edit-user-id').val();
        if (!utssId) {
            return;
        }
        var $confirmBtn = $(this);
        $confirmBtn.prop('disabled', true);
        $('#cell-delete-error').text('').hide();

        $.ajax({
            url: '/schedule/occurrence/' + utssId,
            method: 'DELETE',
            data: {
                occurrence_date: date
            },
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (response) {
                $confirmBtn.prop('disabled', false);
                if (!response.success) {
                    return;
                }
                var result = response.result || {};
                var $cell = currentCell && currentCell.length
                    ? currentCell
                    : $('#schedule-table .schedule-cell[data-user-id="' + userId + '"][data-date="' + date + '"]');
                if (cellDeleteConfirmModal) {
                    cellDeleteConfirmModal.hide();
                }
                cellEditModal.hide();
                if (typeof dayOccurrencesModal !== 'undefined' && dayOccurrencesModal) {
                    try {
                        dayOccurrencesModal.hide();
                    } catch (e) {
                        // ignore
                    }
                }
                renderScheduleCellFromResult($cell, result, {deleted: true});
                syncFlexibleHintAfterAnnul(userId, result);
                currentCell = $cell;
            },
            error: function (xhr) {
                $confirmBtn.prop('disabled', false);
                var msg = 'Не удалось удалить занятие.';
                if (xhr.status === 422 && xhr.responseJSON) {
                    if (xhr.responseJSON.errors && xhr.responseJSON.errors.utss_id) {
                        msg = xhr.responseJSON.errors.utss_id[0];
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                }
                if (cellDeleteConfirmModal) {
                    cellDeleteConfirmModal.hide();
                }
                $('#cell-delete-error').text(msg).show();
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
        $('#abonement-start-date-hint').hide().text('');
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

    function selectedAbonementAssignment() {
        if (!abonementContextCache || !Array.isArray(abonementContextCache.assignments)) {
            return null;
        }
        var id = parseInt($('#abonement-ulp-id').val(), 10);
        if (!id) {
            return null;
        }
        for (var i = 0; i < abonementContextCache.assignments.length; i++) {
            if (parseInt(abonementContextCache.assignments[i].id, 10) === id) {
                return abonementContextCache.assignments[i];
            }
        }
        return null;
    }

    /**
     * Месячный ULP из установки цен: старт пустой, конец readonly.
     * Классика: старт = default_start_date (сегодня), без readonly ends_at.
     * Валидация даты — только Laravel (novalidate на форме), без HTML5 min/max/required.
     */
    function applySelectedUlpPeriodUi() {
        var a = selectedAbonementAssignment();
        var $start = $('#abonement-start-date');
        var $endsWrap = $('#abonement-ends-at-wrap');
        var $ends = $('#abonement-ends-at');
        var $hint = $('#abonement-start-date-hint');

        $start.removeAttr('min').removeAttr('max').prop('required', false);
        $hint.hide().text('');

        if (a && a.from_setting_prices && a.billing_month) {
            var month = String(a.billing_month).slice(0, 7);
            var min = month + '-01';
            var max = a.ends_at || '';
            if (!max && month.length === 7) {
                // fallback: последний день месяца через Date
                var parts = month.split('-');
                var last = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10), 0);
                var dd = String(last.getDate()).padStart(2, '0');
                max = month + '-' + dd;
            }
            $start.val('');
            $ends.val(max || '');
            $endsWrap.show();
            $hint.text('Укажите дату начала в пределах месяца начисления (' + min + ' … ' + max + ').').show();
            return;
        }

        $endsWrap.hide();
        $ends.val('');
        $start.val((abonementContextCache && abonementContextCache.default_start_date) || '');
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
        applySelectedUlpPeriodUi();
        applyTeamWeekdayTemplate();
        $('#abonement-preview-wrap').hide();
        $('#abonement-preview-text').text('');
        clearAbonementErrors();
    }

    $(document).on('change', '#abonement-team-id', function () {
        applyTeamWeekdayTemplate();
        $('#abonement-team-id').removeClass('is-invalid');
        $('#abonement-team-error').text('').hide();
    });
    $(document).on('change', '#abonement-ulp-id', function () {
        clearAbonementErrors();
        applySelectedUlpPeriodUi();
    });
    $(document).on('change input', '#abonement-start-date', function () {
        $('#abonement-start-date').removeClass('is-invalid');
        $('#abonement-start-date-error').text('').hide();
    });
    $(document).on('change', '.abonement-day-chk', function () {
        $('#abonement-weekdays-error').text('').hide();
    });

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
