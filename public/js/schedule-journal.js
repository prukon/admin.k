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
    function revealScheduleJournalTable() {
        var stage = document.getElementById('schedule-journal-stage');
        if (!stage || stage.classList.contains('is-ready')) {
            return;
        }
        stage.classList.add('is-ready');
        stage.setAttribute('aria-busy', 'false');
    }

    var table;
    try {
        table = $('#schedule-table').DataTable({
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
        revealScheduleJournalTable();
    } catch (err) {
        revealScheduleJournalTable();
        throw err;
    }

    if (window.KidsCrmTooltip) {
        var scheduleTableEl = document.getElementById('schedule-table');
        if (scheduleTableEl) {
            KidsCrmTooltip.bindDataTable(scheduleTableEl);
            KidsCrmTooltip.init(scheduleTableEl, { scopes: ['hint'] });
        }
    }

    function formatDateHuman(dateStr) {
        const months = [
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];
        const dateObj = new Date(dateStr);
        return dateObj.getDate() + ' ' + months[dateObj.getMonth()] + ' ' + dateObj.getFullYear();
    }

    /** Y-m-d → «1 августа 2026» без сдвига таймзоны. */
    function formatDateHumanYmd(ymd) {
        var months = [
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];
        var parts = String(ymd || '').split('-');
        if (parts.length !== 3) {
            return String(ymd || '');
        }
        var year = parts[0];
        var monthIdx = parseInt(parts[1], 10) - 1;
        var day = parseInt(parts[2], 10);
        if (!year || monthIdx < 0 || monthIdx > 11 || !day) {
            return String(ymd || '');
        }
        return day + ' ' + months[monthIdx] + ' ' + year;
    }

    /**
     * Текст превью «Разложить абонемент»:
     * Занятий: N; период 1 августа 2026 — 31 августа 2026
     * 1 занятие: 1 августа 2026
     * …
     */
    function formatAbonementPreviewText(result) {
        var r = result || {};
        var count = Number(r.linked_count || 0);
        var lines = [
            'Занятий: ' + count +
            '; период ' + formatDateHumanYmd(r.starts_at) +
            ' — ' + formatDateHumanYmd(r.ends_at)
        ];
        (r.preview_dates || []).forEach(function (ymd, idx) {
            lines.push((idx + 1) + ' занятие: ' + formatDateHumanYmd(ymd));
        });
        return lines.join('\n');
    }

    /** Y-m-d → д.м.г без сдвига таймзоны. */
    function formatDateDmY(ymd) {
        var parts = String(ymd || '').split('-');
        if (parts.length !== 3) {
            return String(ymd || '');
        }
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function todayYmdLocal() {
        var d = new Date();
        return d.getFullYear()
            + '-' + String(d.getMonth() + 1).padStart(2, '0')
            + '-' + String(d.getDate()).padStart(2, '0');
    }

    function resolveAbonementTodayYmd() {
        if (abonementContextCache && abonementContextCache.default_start_date) {
            return String(abonementContextCache.default_start_date);
        }
        return todayYmdLocal();
    }

    function billingMonthRange(assignment) {
        if (!assignment || !assignment.from_setting_prices || !assignment.billing_month) {
            return null;
        }
        var month = String(assignment.billing_month).slice(0, 7);
        var min = month + '-01';
        var max = assignment.ends_at || '';
        if (!max && month.length === 7) {
            var parts = month.split('-');
            var last = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10), 0);
            var dd = String(last.getDate()).padStart(2, '0');
            max = month + '-' + dd;
        }
        return {min: min, max: max};
    }

    function syncAbonementStartDateQuickPicks() {
        var $wrap = $('#abonement-start-date-quick');
        var $monthBtn = $('#abonement-start-quick-month-start');
        var $todayBtn = $('#abonement-start-quick-today');
        if (!$wrap.length) {
            return;
        }

        var a = selectedAbonementAssignment();
        var today = resolveAbonementTodayYmd();
        var monthStart = today.slice(0, 7) + '-01';
        var showToday = true;
        var range = billingMonthRange(a);
        if (range) {
            monthStart = range.min;
            showToday = today >= range.min && (!range.max || today <= range.max);
        }

        $monthBtn.attr('data-date', monthStart).text(formatDateDmY(monthStart));
        if (showToday) {
            $todayBtn
                .attr('data-date', today)
                .text(formatDateDmY(today))
                .removeClass('d-none');
        } else {
            $todayBtn.addClass('d-none').attr('data-date', '').text('');
        }
        $wrap.removeClass('d-none');
    }

    function applyAbonementStartDateQuickPick(ymd) {
        if (!ymd) {
            return;
        }
        $('#abonement-start-date').val(ymd).trigger('change');
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    $('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change', function () {
        var newUrl = new URL(window.location.href);
        newUrl.searchParams.set('year', $('#filter-year').val());
        newUrl.searchParams.set('month', $('#filter-month').val());
        newUrl.searchParams.set('team', $('#filter-team').val());
        newUrl.searchParams.delete('page');
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
    var emptyCellPlaceModalEl = document.getElementById('emptyCellPlaceModal');
    var emptyCellPlaceModal = emptyCellPlaceModalEl
        ? new bootstrap.Modal(emptyCellPlaceModalEl, {})
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
    var emptyCellContextCache = null;
    var emptyCellUserId = null;
    var emptyCellSelectedOption = null;

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

    function trainerMultiselectDropdownParent($select) {
        var selectId = $select.attr('id') || '';
        if (selectId === 'cell-trainer-profile-ids') {
            return $('#cellEditModal');
        }
        if (selectId === 'flexible-trainer-profile-ids') {
            return $('#flexiblePlaceModal');
        }
        return $select.closest('.modal');
    }

    function initTrainerMultiselect($select) {
        if (!$select.length || !window.KidsCrmGenericMultiselectSelect2) {
            return;
        }
        // Как на /admin/districts («Объекты»): явный dropdownParent на модалку.
        KidsCrmGenericMultiselectSelect2.init($select, {
            placeholder: $select.data('placeholder') || 'Без тренера',
            allowClear: true,
            dropdownParent: trainerMultiselectDropdownParent($select)
        });
    }

    function populateTrainerMultiselect($select, trainers, selectedIds) {
        var ids = Array.isArray(selectedIds)
            ? selectedIds.map(String)
            : (selectedIds ? [String(selectedIds)] : []);
        // Сначала destroy Select2, потом options — иначе список остаётся пустым.
        if ($select.data('select2')) {
            $select.off('.kidsCrmGenericMultiselect');
            $select.select2('destroy');
        }
        $select.empty();
        (trainers || []).forEach(function (trainer) {
            if (!trainer || trainer.id === undefined || trainer.id === null) {
                return;
            }
            $select.append($('<option>', {
                value: String(trainer.id),
                text: trainer.name || ('#' + trainer.id)
            }));
        });
        initTrainerMultiselect($select);
        if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.setValues($select, ids);
        } else {
            $select.val(ids).trigger('change');
        }
    }

    function clearTrainerMultiselect($select) {
        if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.reset($select);
            KidsCrmGenericMultiselectSelect2.clearInvalid($select);
        } else {
            $select.val(null).trigger('change');
            $select.removeClass('is-invalid');
        }
    }

    function trainerIdsForVisited(ctx) {
        if (!ctx) {
            return [];
        }
        // Сохранённый «Посетил» (в т.ч. с пустым списком = «Без тренера») —
        // не подменять дефолтом группы при повторном открытии.
        if (Array.isArray(ctx.trainer_profile_ids_for_select) && isVisitedStatusId(ctx.current_status_id)) {
            return ctx.trainer_profile_ids_for_select.map(String);
        }
        if (Array.isArray(ctx.trainer_profile_ids_for_select) && ctx.trainer_profile_ids_for_select.length) {
            return ctx.trainer_profile_ids_for_select.map(String);
        }
        if (ctx.trainer_profile_id_for_select !== null && ctx.trainer_profile_id_for_select !== undefined && ctx.trainer_profile_id_for_select !== '') {
            return [String(ctx.trainer_profile_id_for_select)];
        }
        if (ctx.team_default_trainer_profile_id) {
            return [String(ctx.team_default_trainer_profile_id)];
        }
        return [];
    }

    function defaultTrainerIdsFromContext(ctx) {
        if (ctx && ctx.team_default_trainer_profile_id) {
            return [String(ctx.team_default_trainer_profile_id)];
        }
        return [];
    }

    function selectedTrainerNames($select) {
        var names = $select.find('option:selected').map(function () {
            return String($(this).text() || '').trim();
        }).get().filter(Boolean);
        return names.length ? names.join(', ') : null;
    }

    function syncTrainerBlock() {
        var statusVal = $('input[name="lesson_occurrence_status_id"]:checked').val();
        var $sel = $('#cell-trainer-profile-ids');
        if (!isVisitedStatusId(statusVal)) {
            $('#cell-trainer-wrap').addClass('d-none');
            clearTrainerMultiselect($sel);
            $('#cell-trainer-hint').text('');
            return;
        }
        $('#cell-trainer-wrap').removeClass('d-none');
        var selectIds = trainerIdsForVisited(cellContextCache);
        var trainers = cellContextCache && cellContextCache.trainers ? cellContextCache.trainers : [];
        // Всегда пересобираем options из cell-context — иначе Select2 может остаться без пунктов.
        if (trainers.length || $sel.find('option').length === 0) {
            populateTrainerMultiselect($sel, trainers, selectIds);
        } else if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.setValues($sel, selectIds);
        } else {
            $sel.val(selectIds).trigger('change');
        }
        var hint = '';
        var defaultId = cellContextCache && cellContextCache.team_default_trainer_profile_id
            ? String(cellContextCache.team_default_trainer_profile_id)
            : '';
        if (defaultId && selectIds.length === 1 && selectIds[0] === defaultId) {
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
        return scheduleJournalFilterTeamId();
    }

    function scheduleJournalFilterTeamId() {
        var filterVal = $('#filter-team').val();
        if (filterVal && filterVal !== 'all' && filterVal !== 'none') {
            return String(filterVal);
        }
        return '';
    }

    function clearCellFieldErrors() {
        $('#cell-status-error, #cell-trainer-error, #cell-comment-error').text('').hide();
        $('#description').removeClass('is-invalid');
        if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.clearInvalid($('#cell-trainer-profile-ids'));
        } else {
            $('#cell-trainer-profile-ids').removeClass('is-invalid');
        }
    }

    function showCellFieldErrors(errors) {
        clearCellFieldErrors();
        if (!errors) {
            return;
        }
        if (errors.lesson_occurrence_status_id) {
            $('#cell-status-error').text(errors.lesson_occurrence_status_id[0]).show();
        }
        if (errors.trainer_profile_ids || errors['trainer_profile_ids.0'] || errors.trainer_profile_id) {
            var trainerErr = (errors.trainer_profile_ids
                || errors['trainer_profile_ids.0']
                || errors.trainer_profile_id)[0];
            if (window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.markInvalid($('#cell-trainer-profile-ids'));
            } else {
                $('#cell-trainer-profile-ids').addClass('is-invalid');
            }
            $('#cell-trainer-error').text(trainerErr).show();
        }
        if (errors.comment) {
            $('#description').addClass('is-invalid');
            $('#cell-comment-error').text(errors.comment[0]).show();
        }
        if (errors.utss_id) {
            $('#cell-status-error').text(errors.utss_id[0]).show();
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

    function setCellEditTeamDisplay(title) {
        $('#edit-user-teams-display').text(title ? String(title) : '');
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
            setCellEditTeamDisplay('');
            syncPostpayCreateMeta();
            return;
        }

        var selectedId = preferredTeamId
            || ctx.postpay_team_id
            || (teams[0] && teams[0].id)
            || '';
        selectedId = selectedId ? String(selectedId) : '';

        if (teams.length === 1) {
            var onlyTitle = teams[0].title || ('Группа #' + teams[0].id);
            $select.addClass('d-none').empty().append($('<option>', {
                value: teams[0].id,
                text: onlyTitle,
                selected: true
            }));
            $readonly.addClass('d-none').text('');
            $wrap.addClass('d-none');
            $('#edit-team-id').val(String(teams[0].id));
            setCellEditTeamDisplay(onlyTitle);
            syncPostpayCreateMeta();
            return;
        }

        $wrap.removeClass('d-none');
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
        setCellEditTeamDisplay('');
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
        $('#flexible-team-id, #flexible-comment').removeClass('is-invalid');
        if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.clearInvalid($('#flexible-trainer-profile-ids'));
        } else {
            $('#flexible-trainer-profile-ids').removeClass('is-invalid');
        }
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
        if (errors.trainer_profile_ids || errors['trainer_profile_ids.0'] || errors.trainer_profile_id) {
            var trainerErr = (errors.trainer_profile_ids
                || errors['trainer_profile_ids.0']
                || errors.trainer_profile_id)[0];
            if (window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.markInvalid($('#flexible-trainer-profile-ids'));
            } else {
                $('#flexible-trainer-profile-ids').addClass('is-invalid');
            }
            $('#flexible-trainer-error').text(trainerErr).show();
        }
        if (errors.comment) {
            $('#flexible-comment').addClass('is-invalid');
            $('#flexible-comment-error').text(errors.comment[0]).show();
        }
    }

    function populateFlexibleTrainerSelect(trainers, selectedIds) {
        populateTrainerMultiselect($('#flexible-trainer-profile-ids'), trainers, selectedIds);
    }

    function syncFlexibleTrainerBlock() {
        var statusVal = $('input[name="flexible_lesson_occurrence_status_id"]:checked').val();
        var $sel = $('#flexible-trainer-profile-ids');
        if (!isVisitedStatusId(statusVal)) {
            $('#flexible-trainer-wrap').addClass('d-none');
            clearTrainerMultiselect($sel);
            $('#flexible-trainer-hint').text('');
            return;
        }
        $('#flexible-trainer-wrap').removeClass('d-none');
        var selectIds = defaultTrainerIdsFromContext(flexibleContextCache);
        var trainers = flexibleContextCache && flexibleContextCache.trainers ? flexibleContextCache.trainers : [];
        if (trainers.length || $sel.find('option').length === 0) {
            populateFlexibleTrainerSelect(trainers, selectIds);
        } else if (window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.setValues($sel, selectIds);
        } else {
            $sel.val(selectIds).trigger('change');
        }
        var hint = '';
        var defaultId = flexibleContextCache && flexibleContextCache.team_default_trainer_profile_id
            ? String(flexibleContextCache.team_default_trainer_profile_id)
            : '';
        if (defaultId && selectIds.length === 1 && selectIds[0] === defaultId) {
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
        var $wrap = $('#flexible-team-wrap');
        var $select = $('#flexible-team-id');
        var $readonly = $('#flexible-team-readonly');
        var $display = $('#flexible-team-display');
        $select.empty().removeClass('d-none is-invalid');
        $readonly.addClass('d-none').text('');
        $wrap.removeClass('d-none');
        $display.text('');

        function teamTitleById(id) {
            var title = '';
            var want = parseInt(id, 10);
            teams.forEach(function (team) {
                if (parseInt(team.id, 10) === want) {
                    title = team.title || '';
                }
            });
            return title || ('#' + id);
        }

        if (!teams.length) {
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $display.text('');
            syncFlexiblePackageSummary();
            syncFlexibleTrainerBlock();
            return;
        }

        teams.forEach(function (team) {
            $select.append($('<option>', {value: team.id, text: team.title || ('#' + team.id)}));
        });

        var resolved = preferredTeamId || ctx.team_id || (teams[0] && teams[0].id) || '';
        if (resolved) {
            $select.val(String(resolved));
        }

        if ((ctx.team_locked && resolved) || teams.length === 1) {
            var lockedTitle = teams.length === 1
                ? (teams[0].title || ('#' + teams[0].id))
                : teamTitleById(resolved);
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $readonly.removeClass('d-none').text(lockedTitle || '—');
            $display.text(lockedTitle || '');
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

    /** Видимая подпись колонки абонементов: «10/12\\nГибкий». */
    function flexibleAbonementColumnLabel(remaining, total) {
        return String(remaining) + '/' + String(total) + '\nГибкий';
    }

    /** Как Money::formatRub($cents, ' руб') — для ховера гибкого без F5. */
    function formatFeeRubFromCents(cents) {
        var n = Math.max(0, Math.floor(Number(cents) || 0));
        var rub = Math.floor(n / 100);
        var kop = n % 100;
        var body = String(rub).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        if (kop !== 0) {
            body += ',' + String(kop).padStart(2, '0');
        }
        return body + ' руб';
    }

    /**
     * Ховер гибкого в колонке абонементов (с ценой, в т.ч. «за 0 руб»).
     * withRatio: «10/12 остаток … по абонементу "X" за Y руб»
     */
    function flexibleAbonementColumnHoverLine(name, feeCents, withRatio, remaining, total) {
        var nm = (name && String(name).trim()) ? String(name).trim() : 'Гибкий абонемент';
        var tail = ' по абонементу "' + nm + '" за ' + formatFeeRubFromCents(feeCents);
        if (withRatio) {
            return String(remaining) + '/' + String(total) +
                ' остаток занятий в текущем месяце' + tail;
        }
        return 'Остаток занятий в текущем месяце' + tail;
    }

    function rebuildFlexibleHintTooltip($hint, items) {
        if (!$hint || !$hint.length) {
            return;
        }
        var lines = [];
        (items || []).forEach(function (item) {
            if (!item) {
                return;
            }
            lines.push(flexibleAbonementColumnHoverLine(
                item.name,
                item.fee_amount_cents,
                true,
                item.slots_remaining,
                item.lessons_total
            ));
        });
        if (!lines.length) {
            $hint.remove();
            return;
        }
        var title = lines.join('\n');
        $hint.attr('title', title).attr('aria-label', title);
        if ($hint.hasClass('journal-flexible-hint--ratio') && items.length === 1) {
            var only = items[0];
            $hint.text(flexibleAbonementColumnLabel(only.slots_remaining, only.lessons_total));
            $hint.attr('data-slots-remaining', only.slots_remaining);
            $hint.attr('data-lessons-total', only.lessons_total);
            $hint.attr('data-fee-amount-cents', String(only.fee_amount_cents != null ? only.fee_amount_cents : 0));
            if (only.name) {
                $hint.attr('data-package-name', only.name);
            }
            title = flexibleAbonementColumnHoverLine(only.name, only.fee_amount_cents, false);
            $hint.attr('title', title).attr('aria-label', title);
        } else if ($hint.hasClass('journal-flexible-hint--multi')) {
            $hint.attr('data-flexible-items', JSON.stringify(items.filter(function (item) {
                return !!item;
            })));
        }
        if (window.KidsCrmTooltip && document.getElementById('schedule-table')) {
            KidsCrmTooltip.init(document.getElementById('schedule-table'), { scopes: ['hint'] });
        }
    }

    function syncFlexibleEmptyCellAffordance($row, remaining) {
        if (!$row || !$row.length) {
            return;
        }
        var rem = Number(remaining);
        if (isNaN(rem)) {
            rem = 0;
        }
        var hasEmptyLessonPermission = !!document.getElementById('emptyCellPlaceModal');
        $row.find('.schedule-cell').each(function () {
            var $c = $(this);
            $c.attr('data-flexible', '1');
            $c.attr('data-flexible-remaining', String(rem));
            var occ = parseInt($c.attr('data-occurrence-count') || '0', 10);
            if (occ !== 0) {
                return;
            }
            var isPostpay = $c.attr('data-postpay') === '1';
            if (rem > 0) {
                $c.attr('data-empty-lesson', '0');
                if (!$c.find('.schedule-cell__swatch').length) {
                    $c.find('.schedule-cell-empty-dot').remove();
                    $c.append(
                        '<i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" ' +
                        'title="Гибкий абонемент: поставить занятие"></i>'
                    );
                }
                return;
            }
            if (isPostpay) {
                $c.attr('data-empty-lesson', '0');
                return;
            }
            if (hasEmptyLessonPermission) {
                $c.attr('data-empty-lesson', '1');
                if (!$c.find('.schedule-cell__swatch').length) {
                    $c.find('.schedule-cell-empty-dot').remove();
                    $c.append(
                        '<i class="fa-regular fa-circle text-secondary schedule-cell-empty-dot" style="opacity: 0.35;" ' +
                        'title="Пробное, разовое или занятие из гибкого абонемента"></i>'
                    );
                }
                return;
            }
            $c.attr('data-empty-lesson', '0');
            if (!$c.find('.schedule-cell__swatch').length) {
                $c.find('.schedule-cell-empty-dot').remove();
                $c.append(
                    '<i class="fa-regular fa-circle text-primary schedule-cell-empty-dot" style="opacity: 0.4;" ' +
                    'title="Гибкий абонемент: поставить занятие"></i>'
                );
            }
        });
    }

    function updateFlexibleHintAfterPlace(userId, result) {
        var $row = $('#schedule-table tbody tr[data-user-id="' + userId + '"]');
        if (!$row.length) {
            return;
        }
        var $hint = $row.find('.journal-flexible-hint').first();
        var remaining = Number(result.slots_remaining);
        if (isNaN(remaining)) {
            remaining = 0;
        }
        var total = Number(result.lessons_total || 0);
        var ulpId = Number(result.user_lesson_package_id || 0);

        syncFlexibleEmptyCellAffordance($row, remaining);

        if (!$hint.length) {
            return;
        }

        if ($hint.hasClass('journal-flexible-hint--ratio')) {
            rebuildFlexibleHintTooltip($hint, [{
                id: ulpId,
                name: $hint.attr('data-package-name') || 'Гибкий абонемент',
                slots_remaining: remaining,
                lessons_total: total || Number($hint.attr('data-lessons-total') || 0),
                fee_amount_cents: result.fee_amount_cents != null
                    ? Number(result.fee_amount_cents)
                    : Number($hint.attr('data-fee-amount-cents') || 0)
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
                    lessons_total: total || item.lessons_total,
                    fee_amount_cents: result.fee_amount_cents != null
                        ? Number(result.fee_amount_cents)
                        : Number(item.fee_amount_cents || 0)
                };
            }
            return item;
        }).filter(function (item) {
            return !!item;
        });

        if (!items.length) {
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
        var prevHover = String($cell.attr('data-package-hover') || '').trim();
        var nextHover = result.package_hover != null ? String(result.package_hover).trim() : '';
        var packageHover = nextHover;
        if (increment && prevCount >= 1 && prevHover && nextHover) {
            packageHover = stripTrainerHoverLines(prevHover) + '\n' + stripTrainerHoverLines(nextHover);
        } else if (!nextHover && prevHover) {
            packageHover = prevHover;
        }
        packageHover = applyTrainerHoverToCellText(packageHover, result);
        paintScheduleCellOccurrence($cell, count, {
            utss_id: result.utss_id,
            comment: result.comment || '',
            status: result.status || {},
            package_hover: packageHover
        });
    }

    function stripTrainerHoverLines(text) {
        return String(text || '')
            .split('\n')
            .map(function (line) { return String(line || '').trim(); })
            .filter(function (line) {
                if (!line) {
                    return false;
                }
                if (line === 'Тренер не выбран') {
                    return false;
                }
                return !/^Тренер:\s*/.test(line);
            })
            .join('\n');
    }

    function applyTrainerHoverToCellText(baseHover, result) {
        var base = stripTrainerHoverLines(baseHover);
        var statusId = result && result.status ? result.status.id : null;
        if (!isVisitedStatusId(statusId)) {
            return base;
        }
        var trainerName = '';
        if (result && Object.prototype.hasOwnProperty.call(result, 'trainer_name') && result.trainer_name) {
            trainerName = String(result.trainer_name).trim();
        }
        var line = trainerName !== '' ? ('Тренер: ' + trainerName) : 'Тренер не выбран';
        return base !== '' ? (base + '\n' + line) : line;
    }

    function enrichResultTrainerNameFromSelect(result, selectSelector) {
        if (!result) {
            return result;
        }
        if (Object.prototype.hasOwnProperty.call(result, 'trainer_name')) {
            return result;
        }
        var statusId = result.status ? result.status.id : null;
        if (!isVisitedStatusId(statusId)) {
            result.trainer_name = null;
            return result;
        }
        var $sel = $(selectSelector);
        var val = $sel.val();
        var hasVal = Array.isArray(val) ? val.length > 0 : !!val;
        if (!hasVal) {
            result.trainer_name = null;
            return result;
        }
        result.trainer_name = selectedTrainerNames($sel);
        return result;
    }

    function clearScheduleCellPackageHover($cell) {
        if (!$cell || !$cell.length) {
            return;
        }
        var el = $cell.get(0);
        if (window.KidsCrmTooltip && el) {
            KidsCrmTooltip.dispose(el, {scopes: ['hint']});
        }
        $cell.removeAttr('data-package-hover');
        if ($cell.attr('data-postpay-locked') === '1') {
            $cell.attr('data-kids-tooltip-hint', '1')
                .attr('data-bs-toggle', 'tooltip')
                .attr('data-bs-placement', 'top')
                .attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip')
                .attr('title', 'Изменить данные нельзя, поскольку уже была произведена оплата');
            if (window.KidsCrmTooltip && el) {
                KidsCrmTooltip.init(el, {scopes: ['hint']});
            }
            return;
        }
        $cell.removeAttr('data-kids-tooltip-hint data-bs-toggle data-bs-placement data-bs-custom-class title aria-label');
    }

    function setScheduleCellPackageHover($cell, hoverText) {
        if (!$cell || !$cell.length) {
            return;
        }
        var text = String(hoverText || '').trim();
        if (!text) {
            clearScheduleCellPackageHover($cell);
            return;
        }
        var el = $cell.get(0);
        if (window.KidsCrmTooltip && el) {
            KidsCrmTooltip.dispose(el, {scopes: ['hint']});
        }
        $cell.attr('data-package-hover', text)
            .attr('data-kids-tooltip-hint', '1')
            .attr('data-bs-toggle', 'tooltip')
            .attr('data-bs-placement', 'top')
            .attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip')
            .attr('title', text)
            .attr('aria-label', text);
        if (window.KidsCrmTooltip && el) {
            KidsCrmTooltip.init(el, {scopes: ['hint']});
        }
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
            clearScheduleCellPackageHover($cell);
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
            } else if ($cell.attr('data-empty-lesson') === '1') {
                $cell.css('cursor', 'pointer');
                $cell.append(
                    '<i class="fa-regular fa-circle text-secondary schedule-cell-empty-dot" style="opacity: 0.35;" ' +
                    'title="Пробное или разовое занятие"></i>'
                );
            }
            return;
        }

        if (count === 1 && result.remaining) {
            paintScheduleCellOccurrence($cell, 1, {
                utss_id: result.remaining.utss_id,
                comment: result.remaining.comment || '',
                status: result.remaining.status || {},
                package_hover: result.remaining.package_hover || ''
            });
            return;
        }

        paintScheduleCellOccurrence($cell, count, {
            package_hover: result.package_hover || $cell.attr('data-package-hover') || ''
        });
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
            setScheduleCellPackageHover($cell, payload.package_hover || '');
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
        setScheduleCellPackageHover($cell, payload && payload.package_hover ? payload.package_hover : ($cell.attr('data-package-hover') || ''));
    }

    function renderScheduleCellAfterFlexiblePlace($cell, result) {
        renderScheduleCellFromResult($cell, result, {increment: true});
    }

    function renderScheduleCellAfterStatusSave($cell, result) {
        var created = !!(result && result.created);
        renderScheduleCellFromResult($cell, result, {increment: created});
        if (result && result.is_flexible && result.user_lesson_package_id != null) {
            var uid = $cell && $cell.length
                ? $cell.attr('data-user-id')
                : null;
            if (uid) {
                updateFlexibleHintAfterPlace(uid, result);
            }
        }
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

        syncFlexibleEmptyCellAffordance($row, remaining);

        var $hint = $row.find('.journal-flexible-hint').first();
        if ($hint.length) {
            updateFlexibleHintAfterPlace(userId, result);
            return;
        }

        var $host = $row.find('.journal-abonement-cell');
        if (!$host.length) {
            return;
        }
        var total = Number(result.lessons_total || 0);
        var name = result.package_name || 'Гибкий абонемент';
        var feeCents = result.fee_amount_cents != null ? Number(result.fee_amount_cents) : 0;
        var title = flexibleAbonementColumnHoverLine(name, feeCents, false);
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
            .attr('data-fee-amount-cents', String(feeCents))
            .attr('data-package-name', name)
            .text(flexibleAbonementColumnLabel(remaining, total || remaining));
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
        $('#flexible-team-display').text('');
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
                populateFlexibleTrainerSelect(ctx.trainers || [], []);
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
        var trainerIds = [];
        if (isVisitedStatusId(statusId)) {
            trainerIds = $('#flexible-trainer-profile-ids').val() || [];
            if (!Array.isArray(trainerIds)) {
                trainerIds = trainerIds ? [trainerIds] : [];
            }
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
                trainer_profile_ids: trainerIds,
                comment: comment
            },
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (response) {
                if (response.success) {
                    flexiblePlaceModal.hide();
                    var result = response.result || {};
                    enrichResultTrainerNameFromSelect(result, '#flexible-trainer-profile-ids');
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

    function clearEmptyCellErrors() {
        $('#empty-cell-choice-error, #empty-cell-team-error, #empty-cell-fee-error, #empty-cell-status-error, #empty-cell-trainer-error, #empty-cell-comment-error')
            .text('').hide();
        $('#empty-cell-team-id, #empty-cell-fee-amount, #empty-cell-trainer-profile-id, #empty-cell-comment').removeClass('is-invalid');
    }

    function showEmptyCellErrors(errors) {
        clearEmptyCellErrors();
        if (!errors) {
            return;
        }
        if (errors.kind || errors.choice) {
            $('#empty-cell-choice-error').text((errors.kind || errors.choice)[0]).show();
        }
        if (errors.team_id) {
            $('#empty-cell-team-id').addClass('is-invalid');
            $('#empty-cell-team-error').text(errors.team_id[0]).show();
        }
        if (errors.fee_amount) {
            $('#empty-cell-fee-amount').addClass('is-invalid');
            $('#empty-cell-fee-error').text(errors.fee_amount[0]).show();
        }
        if (errors.lesson_package_id || errors.user_lesson_package_id) {
            var pkgMsg = (errors.lesson_package_id || errors.user_lesson_package_id)[0];
            $('#empty-cell-choice-error').text(pkgMsg).show();
        }
        if (errors.lesson_occurrence_status_id) {
            $('#empty-cell-status-error').text(errors.lesson_occurrence_status_id[0]).show();
        }
        if (errors.trainer_profile_id) {
            $('#empty-cell-trainer-profile-id').addClass('is-invalid');
            $('#empty-cell-trainer-error').text(errors.trainer_profile_id[0]).show();
        }
        if (errors.comment) {
            $('#empty-cell-comment').addClass('is-invalid');
            $('#empty-cell-comment-error').text(errors.comment[0]).show();
        }
        if (errors.occurrence_date) {
            $('#empty-cell-choice-error').text(errors.occurrence_date[0]).show();
        }
    }

    function populateEmptyCellTrainerSelect(trainers, selectedValue) {
        var $sel = $('#empty-cell-trainer-profile-id');
        $sel.empty();
        $sel.append($('<option>', {value: '', text: 'Без тренера'}));
        (trainers || []).forEach(function (trainer) {
            $sel.append($('<option>', {value: trainer.id, text: trainer.name}));
        });
        $sel.val(selectedValue === null || selectedValue === undefined ? '' : String(selectedValue));
    }

    function resetEmptyCellStatusDefault() {
        var scheduledId = emptyCellContextCache && emptyCellContextCache.scheduled_status_id
            ? String(emptyCellContextCache.scheduled_status_id)
            : '';
        $('input[name="empty_cell_lesson_occurrence_status_id"]').prop('checked', false);
        if (scheduledId) {
            $('input[name="empty_cell_lesson_occurrence_status_id"][value="' + scheduledId + '"]').prop('checked', true);
        }
        syncEmptyCellTrainerBlock();
    }

    function syncEmptyCellTrainerBlock() {
        var statusId = $('input[name="empty_cell_lesson_occurrence_status_id"]:checked').val();
        if (!isVisitedStatusId(statusId)) {
            $('#empty-cell-trainer-wrap').addClass('d-none');
            return;
        }
        $('#empty-cell-trainer-wrap').removeClass('d-none');
        var defaultId = emptyCellContextCache && emptyCellContextCache.team_default_trainer_profile_id
            ? emptyCellContextCache.team_default_trainer_profile_id
            : '';
        if (!$('#empty-cell-trainer-profile-id').val() && defaultId) {
            $('#empty-cell-trainer-profile-id').val(String(defaultId));
        }
        $('#empty-cell-trainer-hint').text(
            defaultId ? 'По умолчанию подставлен тренер группы (можно сменить).' : ''
        );
    }

    function renderEmptyCellTeamUi(ctx) {
        var teams = ctx.teams || [];
        var $wrap = $('#empty-cell-team-wrap');
        var $select = $('#empty-cell-team-id');
        var $readonly = $('#empty-cell-team-readonly');
        var $display = $('#empty-cell-team-display');
        $select.empty().removeClass('d-none is-invalid');
        $readonly.addClass('d-none').text('');
        $wrap.removeClass('d-none');
        $display.text('');

        if (!teams.length) {
            $select.addClass('d-none');
            $readonly.removeClass('d-none').text('Ученик не состоит ни в одной группе');
            $display.text('');
            return;
        }

        function teamTitleById(id) {
            var title = '';
            var want = parseInt(id, 10);
            teams.forEach(function (team) {
                if (parseInt(team.id, 10) === want) {
                    title = team.title || '';
                }
            });
            return title || ('Группа #' + id);
        }

        if (ctx.team_locked && ctx.team_id) {
            var lockedTitle = teamTitleById(ctx.team_id);
            $select.append($('<option>', {
                value: ctx.team_id,
                text: lockedTitle,
                selected: true
            }));
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $display.text(lockedTitle);
            return;
        }

        if (teams.length === 1) {
            var only = teams[0];
            $select.append($('<option>', {
                value: only.id,
                text: only.title || ('Группа #' + only.id),
                selected: true
            }));
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $display.text(only.title || ('Группа #' + only.id));
            return;
        }

        teams.forEach(function (team) {
            $select.append($('<option>', {value: team.id, text: team.title}));
        });
        if (ctx.team_id) {
            $select.val(String(ctx.team_id));
        }
        $display.text('');
    }

    function renderEmptyCellChoiceOptions(ctx) {
        var $wrap = $('#empty-cell-choice-options').empty();
        var trial = ctx.trial || {};
        var trialId = 'empty-cell-choice-trial';
        var $trialLabel = $('<label class="cell-status-option__main" for="' + trialId + '"></label>');
        var $trialInput = $('<input class="form-check-input cell-status-option__input" type="radio" name="empty_cell_choice" id="' + trialId + '" value="trial">');
        if (!trial.allowed) {
            $trialInput.prop('disabled', true);
        }
        $trialLabel.append($trialInput);
        $trialLabel.append($('<span class="cell-status-option__title"></span>').text(trial.label || 'Пробное (бесплатное)'));
        var $trialRow = $('<div class="cell-status-option form-check"></div>').append($trialLabel);
        if (!trial.allowed) {
            $trialRow.addClass('cell-status-option--disabled');
            if (trial.reason) {
                $trialRow.append($('<div class="form-text text-muted small mt-1 px-3 pb-2"></div>').text(trial.reason));
            }
        }
        $wrap.append($trialRow);

        var singles = ctx.single_options || [];
        if (!singles.length) {
            var $blocked = $('<div class="form-text text-muted small mt-2"></div>');
            $blocked.text(ctx.single_blocked_reason || 'Нет доступных вариантов разового занятия.');
            $wrap.append($blocked);
        } else {
            singles.forEach(function (opt, idx) {
                var inputId = 'empty-cell-choice-single-' + idx;
                var $label = $('<label class="cell-status-option__main" for="' + inputId + '"></label>');
                var $input = $('<input class="form-check-input cell-status-option__input" type="radio" name="empty_cell_choice" id="' + inputId + '">');
                $input.attr('value', opt.key);
                $input.attr('data-mode', opt.mode);
                $input.attr('data-ulp-id', opt.user_lesson_package_id || '');
                $input.attr('data-package-id', opt.lesson_package_id || '');
                $input.attr('data-fee-amount', opt.fee_amount);
                $input.attr('data-discount-percent', opt.discount_percent != null ? opt.discount_percent : '');
                $input.attr('data-discount-comment', opt.discount_comment || '');
                $label.append($input);
                $label.append($('<span class="cell-status-option__title"></span>').text(opt.label));
                $wrap.append($('<div class="cell-status-option form-check"></div>').append($label));
            });
        }

        var flexOptions = ctx.flexible_options || [];
        flexOptions.forEach(function (opt, idx) {
            var inputId = 'empty-cell-choice-flexible-' + idx;
            var allowed = opt.allowed !== false && Number(opt.slots_remaining || 0) > 0;
            var $label = $('<label class="cell-status-option__main" for="' + inputId + '"></label>');
            var $input = $('<input class="form-check-input cell-status-option__input" type="radio" name="empty_cell_choice" id="' + inputId + '">');
            $input.attr('value', opt.key || ('flexible:' + (opt.user_lesson_package_id || idx)));
            $input.attr('data-mode', 'flexible');
            $input.attr('data-ulp-id', opt.user_lesson_package_id || '');
            $input.attr('data-team-id', opt.team_id || '');
            if (!allowed) {
                $input.prop('disabled', true);
            }
            $label.append($input);
            $label.append($('<span class="cell-status-option__title"></span>').text(opt.label || 'Гибкий абонемент'));
            var $row = $('<div class="cell-status-option form-check"></div>').append($label);
            if (!allowed) {
                $row.addClass('cell-status-option--disabled');
                $row.append($('<div class="form-text text-muted small mt-1 px-3 pb-2"></div>').text(
                    opt.reason || 'Достигнут лимит занятий по гибкому абонементу.'
                ));
            }
            $wrap.append($row);
        });
    }

    function syncEmptyCellFeeBadge($checked) {
        var api = window.KidsCrmUserDiscount;
        var feeInput = document.getElementById('empty-cell-fee-amount');
        var wrap = feeInput ? feeInput.closest('.kids-user-discount-price-wrap') : null;
        if (!api || !wrap) {
            return;
        }
        if (!$checked || !$checked.length || ($checked.attr('data-mode') || '') !== 'create_new') {
            api.hideBadge(wrap);
            return;
        }
        var pct = parseInt($checked.attr('data-discount-percent') || '0', 10) || 0;
        var def = Number($checked.attr('data-fee-amount'));
        var current = Number($(feeInput).val());
        if (pct >= 1 && Number.isFinite(current) && Number.isFinite(def) && Math.abs(current - def) < 0.001) {
            api.showBadge(wrap, pct, $checked.attr('data-discount-comment') || '');
            api.initHint(wrap);
        } else {
            api.hideBadge(wrap);
        }
    }

    function applyEmptyCellChoiceSelection() {
        clearEmptyCellErrors();
        emptyCellSelectedOption = null;
        $('#empty-cell-kind').val('');
        $('#empty-cell-mode').val('');
        $('#empty-cell-ulp-id').val('');
        $('#empty-cell-package-id').val('');
        $('#empty-cell-fee-wrap').addClass('d-none');
        $('#empty-cell-details-wrap').addClass('d-none');
        $('#btnEmptyCellPlace').prop('disabled', true);
        syncEmptyCellFeeBadge(null);

        var $checked = $('input[name="empty_cell_choice"]:checked');
        if (!$checked.length || $checked.prop('disabled')) {
            return;
        }

        var value = $checked.val();
        if (($checked.attr('data-mode') || '') === 'flexible' || String(value || '').indexOf('flexible:') === 0) {
            var flexUserId = $('#empty-cell-user-id').val() || emptyCellUserId;
            var flexDate = $('#empty-cell-occurrence-date').val();
            var flexName = $('#empty-cell-user-name').text() || '';
            var flexTeamId = $checked.attr('data-team-id') || '';
            if (emptyCellPlaceModal) {
                emptyCellPlaceModal.hide();
            }
            openFlexiblePlaceModal(flexUserId, flexDate, flexName, {
                teamId: flexTeamId || scheduleJournalContextTeamId(currentCell)
            });
            return;
        }

        if (value === 'trial') {
            $('#empty-cell-kind').val('trial');
            $('#empty-cell-details-wrap').removeClass('d-none');
            $('#btnEmptyCellPlace').prop('disabled', false);
            return;
        }

        $('#empty-cell-kind').val('single');
        $('#empty-cell-mode').val($checked.attr('data-mode') || '');
        $('#empty-cell-ulp-id').val($checked.attr('data-ulp-id') || '');
        $('#empty-cell-package-id').val($checked.attr('data-package-id') || '');
        var fee = $checked.attr('data-fee-amount');
        $('#empty-cell-fee-amount').val(fee !== undefined && fee !== '' ? fee : '');
        emptyCellSelectedOption = {
            mode: $checked.attr('data-mode'),
            fee: fee
        };
        if (($checked.attr('data-mode') || '') === 'create_new') {
            $('#empty-cell-fee-wrap').removeClass('d-none');
            syncEmptyCellFeeBadge($checked);
        }
        $('#empty-cell-details-wrap').removeClass('d-none');
        $('#btnEmptyCellPlace').prop('disabled', false);
    }

    function openEmptyCellPlaceModal(userId, date, userName) {
        if (!emptyCellPlaceModal) {
            return;
        }
        clearEmptyCellErrors();
        emptyCellUserId = userId;
        emptyCellContextCache = null;
        emptyCellSelectedOption = null;
        $('#empty-cell-user-id').val(userId);
        $('#empty-cell-user-name').text(userName || '');
        $('#empty-cell-team-display').text('');
        $('#empty-cell-occurrence-date').val(date);
        $('#empty-cell-date-display').text(formatDateHuman(date));
        $('#empty-cell-choice-options').empty();
        $('#empty-cell-choice-error').text('').hide();
        $('#empty-cell-details-wrap').addClass('d-none');
        $('#empty-cell-fee-wrap').addClass('d-none');
        $('#empty-cell-comment').val('');
        $('#empty-cell-kind').val('');
        $('#empty-cell-mode').val('');
        $('#empty-cell-ulp-id').val('');
        $('#empty-cell-package-id').val('');
        $('#btnEmptyCellPlace').prop('disabled', true);
        $('#empty-cell-trainer-wrap').addClass('d-none');

        var filterTeamId = scheduleJournalFilterTeamId();

        $.ajax({
            url: '/schedule/user/' + userId + '/empty-cell-context',
            method: 'GET',
            data: {
                occurrence_date: date,
                context_team_id: filterTeamId || undefined
            },
            headers: {'Accept': 'application/json'},
            success: function (ctx) {
                emptyCellContextCache = ctx;
                if (ctx.visited_status_id) {
                    visitedStatusId = parseInt(ctx.visited_status_id, 10) || visitedStatusId;
                }
                renderEmptyCellChoiceOptions(ctx);
                renderEmptyCellTeamUi(ctx);
                populateEmptyCellTrainerSelect(ctx.trainers || [], '');
                resetEmptyCellStatusDefault();
                emptyCellPlaceModal.show();
            },
            error: function (xhr) {
                var msg = 'Не удалось загрузить варианты установки.';
                if (xhr.status === 403) {
                    msg = 'Недостаточно прав для установки пробного или разового занятия.';
                }
                $('#empty-cell-choice-error').text(msg).show();
                emptyCellPlaceModal.show();
            }
        });
    }

    $(document).on('change', 'input[name="empty_cell_choice"]', function () {
        applyEmptyCellChoiceSelection();
    });

    $(document).on('input change', '#empty-cell-fee-amount', function () {
        syncEmptyCellFeeBadge($('input[name="empty_cell_choice"]:checked'));
    });

    $(document).on('change', 'input[name="empty_cell_lesson_occurrence_status_id"]', syncEmptyCellTrainerBlock);

    $('#empty-cell-team-id').on('change', function () {
        if (!emptyCellContextCache) {
            return;
        }
        var teamId = parseInt($(this).val(), 10) || null;
        // default trainer may change with team — keep simple: leave current selection
        emptyCellContextCache.team_id = teamId;
    });

    $('#emptyCellPlaceForm').on('submit', function (e) {
        e.preventDefault();
        clearEmptyCellErrors();

        var kind = $('#empty-cell-kind').val();
        var userId = $('#empty-cell-user-id').val() || emptyCellUserId;
        var date = $('#empty-cell-occurrence-date').val();
        var teamId = $('#empty-cell-team-id').val();
        var statusId = $('input[name="empty_cell_lesson_occurrence_status_id"]:checked').val();
        var trainerId = '';
        if (isVisitedStatusId(statusId)) {
            trainerId = $('#empty-cell-trainer-profile-id').val() || '';
        }
        var comment = $('#empty-cell-comment').val() || '';

        if (!kind) {
            $('#empty-cell-choice-error').text('Выберите тип занятия.').show();
            return;
        }
        if (!teamId) {
            $('#empty-cell-team-error').text('Выберите группу.').show();
            $('#empty-cell-team-id').addClass('is-invalid');
            return;
        }
        if (!statusId) {
            $('#empty-cell-status-error').text('Выберите статус.').show();
            return;
        }

        var url;
        var data = {
            team_id: teamId,
            occurrence_date: date,
            lesson_occurrence_status_id: statusId,
            trainer_profile_id: trainerId,
            comment: comment
        };

        if (kind === 'trial') {
            url = '/schedule/user/' + userId + '/place-trial-lesson';
        } else {
            url = '/schedule/user/' + userId + '/place-single-lesson';
            var mode = $('#empty-cell-mode').val();
            if (mode === 'bind_existing') {
                data.user_lesson_package_id = $('#empty-cell-ulp-id').val();
            } else {
                data.lesson_package_id = $('#empty-cell-package-id').val();
                data.fee_amount = $('#empty-cell-fee-amount').val();
                if (data.fee_amount === '' || data.fee_amount === null) {
                    $('#empty-cell-fee-error').text('Укажите стоимость разового занятия.').show();
                    $('#empty-cell-fee-amount').addClass('is-invalid');
                    return;
                }
            }
        }

        $('#btnEmptyCellPlace').prop('disabled', true);

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            headers: {'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json'},
            success: function (response) {
                if (response.success) {
                    emptyCellPlaceModal.hide();
                    var result = response.result || {};
                    enrichResultTrainerNameFromSelect(result, '#empty-cell-trainer-profile-id');
                    var $cell = currentCell && currentCell.length
                        ? currentCell
                        : $('#schedule-table .schedule-cell[data-user-id="' + userId + '"][data-date="' + date + '"]');
                    renderScheduleCellFromResult($cell, result, {increment: true});
                    currentCell = $cell;
                    return;
                }
                $('#btnEmptyCellPlace').prop('disabled', false);
                showEmptyCellErrors(response.errors || {});
            },
            error: function (xhr) {
                $('#btnEmptyCellPlace').prop('disabled', false);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    showEmptyCellErrors(xhr.responseJSON.errors);
                    return;
                }
                $('#empty-cell-choice-error').text('Не удалось установить занятие.').show();
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
        populateTrainerMultiselect($('#cell-trainer-profile-ids'), [], []);
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
                    renderPostpayTeamUi(ctx, $('#edit-team-id').val());
                    populateTrainerMultiselect($('#cell-trainer-profile-ids'), ctx.trainers || [], trainerIdsForVisited(ctx));
                    syncTrainerBlock();
                    syncPostpayBillingHints(true);
                    hideCellDeleteButton();
                    cellEditModal.show();
                },
                error: function () {
                    setCellEditTeamDisplay('');
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
                setCellEditTeamDisplay(selected.team_title || '');
                var metaParts = [];
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
                if (selected.team_id) {
                    $('#edit-team-id').val(selected.team_id);
                }
                populateTrainerMultiselect($('#cell-trainer-profile-ids'), ctx.trainers || [], []);
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
            var flexibleRemaining = parseInt($(this).attr('data-flexible-remaining') || '0', 10);
            if (isNaN(flexibleRemaining)) {
                flexibleRemaining = 0;
            }
            var hasEmptyLesson = $(this).attr('data-empty-lesson') === '1';

            if (isFlexible && flexibleRemaining > 0) {
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
                return;
            }
            if (isFlexible && flexibleRemaining < 1 && hasEmptyLesson) {
                openEmptyCellPlaceModal(userId, date, userName);
                return;
            }
            if (isFlexible) {
                openFlexiblePlaceModal(userId, date, userName, {
                    teamId: scheduleJournalContextTeamId(currentCell)
                });
                return;
            }
            if (hasEmptyLesson) {
                openEmptyCellPlaceModal(userId, date, userName);
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
                return item.name !== 'trainer_profile_ids[]' && item.name !== 'trainer_profile_id';
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

        var range = billingMonthRange(a);
        if (range) {
            $start.val('');
            $ends.val(range.max || '');
            $endsWrap.show();
            $hint.text(
                'Укажите дату начала в пределах месяца начисления (' + range.min + ' … ' + range.max + ').'
            ).show();
            syncAbonementStartDateQuickPicks();
            return;
        }

        $endsWrap.hide();
        $ends.val('');
        $start.val((abonementContextCache && abonementContextCache.default_start_date) || '');
        syncAbonementStartDateQuickPicks();
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

    function fillAbonementUlpOptions(teamId) {
        var $ulp = $('#abonement-ulp-id').empty();
        var tid = parseInt(teamId, 10) || 0;
        var list = (abonementContextCache && abonementContextCache.assignments) || [];
        var forTeam = list.filter(function (a) {
            return parseInt(a.team_id, 10) === tid;
        });
        forTeam.forEach(function (a) {
            var label = a.name + ' (' + a.lessons_remaining + '/' + a.lessons_total + ')';
            var $opt = $('<option>', {value: a.id, text: label});
            if (!a.placeable) {
                $opt.prop('disabled', true);
                $opt.text(label + ' — уже разложен');
            }
            $ulp.append($opt);
        });
        var placeable = forTeam.filter(function (a) { return a.placeable; });
        if (placeable.length === 1) {
            $ulp.val(String(placeable[0].id));
        } else if (placeable.length > 1) {
            $ulp.val(String(placeable[0].id));
        } else {
            $ulp.val('');
        }
        applySelectedUlpPeriodUi();
    }

    function renderAbonementTeamUi(ctx) {
        var teams = ctx.teams || [];
        var $wrap = $('#abonement-team-wrap');
        var $select = $('#abonement-team-id');
        var $readonly = $('#abonement-team-readonly');
        var $display = $('#abonement-team-display');
        $select.empty().removeClass('d-none is-invalid');
        $readonly.addClass('d-none').text('');
        $wrap.removeClass('d-none');
        $display.text('');

        function teamTitleById(id) {
            var title = '';
            var want = parseInt(id, 10);
            teams.forEach(function (team) {
                if (parseInt(team.id, 10) === want) {
                    title = team.title || '';
                }
            });
            return title || ('#' + id);
        }

        if (!teams.length) {
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $display.text('');
            fillAbonementUlpOptions('');
            applyTeamWeekdayTemplate();
            return;
        }

        teams.forEach(function (team) {
            $select.append($('<option>', {value: team.id, text: team.title || ('#' + team.id)}));
        });

        var resolved = ctx.team_id || (teams[0] && teams[0].id) || '';
        if (resolved) {
            $select.val(String(resolved));
        }

        if (ctx.team_locked || teams.length <= 1) {
            var title = '';
            if (teams.length === 1) {
                title = teams[0].title || ('#' + teams[0].id);
            } else if (resolved) {
                title = teamTitleById(resolved);
            }
            $select.addClass('d-none');
            $wrap.addClass('d-none');
            $readonly.removeClass('d-none').text(title || '—');
            $display.text(title || '');
        }

        fillAbonementUlpOptions($select.val() || resolved);
        applyTeamWeekdayTemplate();
    }

    function fillAbonementForm(ctx) {
        abonementContextCache = ctx;
        $('#abonement-user-name').text(ctx.user.name || '');
        renderAbonementTeamUi(ctx);
        $('#abonement-preview-wrap').hide();
        $('#abonement-preview-text').text('');
        clearAbonementErrors();
    }

    $(document).on('change', '#abonement-team-id', function () {
        fillAbonementUlpOptions($(this).val());
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
    $(document).on('click', '#abonement-start-date-quick button[data-date]', function () {
        applyAbonementStartDateQuickPick($(this).attr('data-date'));
    });
    $(document).on('change', '.abonement-day-chk', function () {
        $('#abonement-weekdays-error').text('').hide();
    });

    $(document).on('click', '.journal-abonement-btn:not(:disabled)', function () {
        abonementUserId = $(this).data('user-id');
        var filterTeamId = scheduleJournalFilterTeamId();
        $.ajax({
            url: '/schedule/user/' + abonementUserId + '/abonement-context',
            method: 'GET',
            data: filterTeamId ? {context_team_id: filterTeamId} : {},
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
                    $('#abonement-preview-text').text(formatAbonementPreviewText(r));
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
