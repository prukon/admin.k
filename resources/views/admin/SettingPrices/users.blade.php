<div class="container setting-price-wrap">
    <hr>

    <div class="row justify-content-center mt-3" id="wrap-bars">
        {{-- Левый блок: список учеников (аналогично левому бару групп) --}}
        <div id="left_bar" class="col-12 col-lg-5 mb-3">
            {{-- Фильтры над списком --}}
            <div class="row mb-3 mt-3">
                <div class="col-6">
                    <select class="form-select" id="filter-team">
                        <option value="">Все группы</option>
                        @foreach($allTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <input type="text"
                           class="form-control"
                           id="filter-user"
                           placeholder="Поиск по ученикам">
                </div>
            </div>

            {{-- Список учеников --}}
            @if(isset($users) && $users->count() > 0)
                @foreach($users as $idx => $user)
                    @php
                        $fullName = trim($user->lastname . ' ' . $user->name);
                        $teamsList = $user->teams->sortBy('title')->values();
                        $teamIds = $teamsList->pluck('id')->implode(',');
                        $teamCount = $teamsList->count();
                    @endphp

                    <div class="mb-2 wrap-team user-row setting-prices-user-row d-flex align-items-center flex-nowrap w-100 min-w-0"
                         data-user-id="{{ $user->id }}"
                         data-user-name="{{ $fullName }}"
                         data-team-ids="{{ $teamIds }}"
                         data-team-count="{{ $teamCount }}">
                        <div class="team-name setting-prices-user-name-col min-w-0 flex-grow-1">
                            <span class="setting-prices-user-name-text">{{ ($idx + 1) . '. ' . $fullName }}</span>
                            <div class="small text-muted user-row-teams">
                                @if($teamsList->isEmpty())
                                    Группа не указана
                                @else
                                    @foreach($teamsList as $team)
                                        <span class="user-row-team-label" data-team-id="{{ $team->id }}">{{ $team->title }}</span>@if(!$loop->last), @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="team-buttons setting-prices-user-detail-col flex-shrink-0">
                            <input class="detail btn btn-primary user-detail-btn" type="button" value="Подробно"
                                   title="">
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-muted">Ученики не найдены.</p>
            @endif
        </div>

        <div class="col-md-auto"></div>

        {{-- Правый блок: цены по месяцам за год (аналогично правому бару) --}}
        <div id="right_bar" class="col-12 col-lg-5">
            <button disabled id="save-user-year-prices"
                    class="btn btn-primary btn-setting-prices mb-3 mt-3">
                Применить
            </button>

            <div class="row mb-2 wrap-users text-start">
                <div class="col-12 mb-2">
                    <h5 id="user-detail-name" class="mb-0">Выберите ученика слева</h5>
                    <small id="user-detail-team" class="text-muted"></small>
                </div>

                <div class="col-12 mb-2">
                    @php $currentYear = now()->year; @endphp
                    <select class="form-select form-select-sm" id="user-year-select">
                        @for($year = $currentYear - 1; $year <= $currentYear + 1; $year++)
                            <option value="{{ $year }}" {{ $year === $currentYear ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-12" id="user-prices-table-wrapper">
                    <p class="text-muted mb-0">
                        После выбора ученика здесь появятся цены по месяцам за выбранный год.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.modal.manualUserPricePaidModal')

@push('styles')
    <style>
        /* Фильтры: отступы, т.к. у #left_bar горизонтальный padding сброшен */
        #left_bar > .row.mb-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            margin-left: 0;
            margin-right: 0;
            --bs-gutter-x: 0.75rem;
        }

        /* Заголовок «Абонемент» вровень с текстом в select (padding select) */
        #right_bar .wrap-users .user-prices-year-header-package {
            box-sizing: border-box;
            padding-left: 0.35rem;
        }

        /* Длинные названия абонемента: «...» в закрытом select */
        #right_bar .wrap-users .setting-prices-monthly-package-select {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Строка ученика: кнопка всегда у правого края, без прыжка при active */
        #left_bar .setting-prices-user-row {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            margin-left: 0;
            margin-right: 0;
            text-align: left;
            cursor: pointer;
            transition: background-color 0.15s ease;
            border-radius: 0;
        }

        #left_bar .setting-prices-user-row:hover:not(.wrap-team--active):not(.setting-prices-user-row--blocked) {
            background-color: rgba(0, 0, 0, 0.035);
        }

        #left_bar .setting-prices-user-row:hover:not(.wrap-team--active):not(.setting-prices-user-row--blocked) .setting-prices-user-name-text {
            text-decoration: underline;
            text-underline-offset: 0.15em;
        }

        #left_bar .setting-prices-user-row .user-detail-btn {
            cursor: pointer;
        }

        /* Несколько групп, «Подробно» недоступна — без кликабельного hover */
        #left_bar .setting-prices-user-row.setting-prices-user-row--blocked {
            cursor: default;
        }

        #left_bar .setting-prices-user-row.setting-prices-user-row--blocked:hover {
            background-color: transparent;
        }

        #left_bar .setting-prices-user-row.setting-prices-user-row--blocked .setting-prices-user-name-text {
            text-decoration: none;
        }

        #left_bar .setting-prices-user-row.setting-prices-user-row--blocked .user-detail-btn {
            cursor: not-allowed;
        }

        #left_bar .setting-prices-user-name-col {
            min-width: 0;
            text-align: left;
        }

        #left_bar .setting-prices-user-detail-col {
            margin-left: auto;
            flex: 0 0 auto;
            white-space: nowrap;
        }

        #left_bar .user-detail-btn.btn-primary.is-visually-disabled,
        #left_bar .user-detail-btn.btn-primary[aria-disabled="true"] {
            color: #6c757d !important;
            background-color: #dee2e6 !important;
            border-color: #adb5bd !important;
            opacity: 1 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
        }

        #left_bar .user-detail-btn.btn-primary.is-visually-disabled:hover,
        #left_bar .user-detail-btn.btn-primary.is-visually-disabled:focus,
        #left_bar .user-detail-btn.btn-primary[aria-disabled="true"]:hover,
        #left_bar .user-detail-btn.btn-primary[aria-disabled="true"]:focus {
            color: #6c757d !important;
            background-color: #dee2e6 !important;
            border-color: #adb5bd !important;
        }
    </style>
@endpush

{{-- Toast Bootstrap 5 для "Сохранено / Ошибка" --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="priceToast" class="toast align-items-center text-white bg-success border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="priceToastBody">
                Сохранено
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Закрыть"></button>
        </div>
    </div>
</div>

@push('scripts')
    @vite(['resources/js/setting-prices-manual-paid-modal.js'])
    <script>
        (function () {
            let currentUserId = null;
            let currentTeamId = null;
            let lastPricesPayload = null;
            let editingNewMonth = null;
            /** @type {Array<{id:number,name:string,price:number}>} */
            let lastLessonPackages = [];

            const DETAIL_DISABLED_TITLE = 'Выберите конкретную группу';

            function escapeAttr(s) {
                if (s == null || s === '') {
                    return '';
                }
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function escapeHtml(s) {
                return escapeAttr(s).replace(/'/g, '&#39;');
            }

            function formatPriceValue(val) {
                if (val == null || val === '') {
                    return '0';
                }
                const n = Number(val);
                if (!isFinite(n)) {
                    return String(val);
                }
                return (Math.round(n * 100) / 100).toString();
            }

            function buildPackageSelectOptions(selectedId) {
                let html = '<option value="">Без абонемента</option>';
                const selected = selectedId != null && selectedId !== '' ? String(selectedId) : '';
                for (let i = 0; i < lastLessonPackages.length; i++) {
                    const pkg = lastLessonPackages[i];
                    const id = String(pkg.id);
                    const sel = selected !== '' && selected === id ? ' selected' : '';
                    html += '<option value="' + escapeAttr(id) + '" data-price="' + escapeAttr(formatPriceValue(pkg.price)) + '"' + sel + '>'
                        + escapeHtml(pkg.name) + '</option>';
                }
                return html;
            }

            function getFilterTeamId() {
                const val = $('#filter-team').val();
                return val ? String(val) : '';
            }

            function getFilterTeamTitle() {
                const teamId = getFilterTeamId();
                if (!teamId) {
                    return '';
                }
                const opt = $('#filter-team option[value="' + teamId + '"]');
                return opt.length ? opt.text().trim() : '';
            }

            function resolveTeamContext(row) {
                const filterTeamId = getFilterTeamId();
                const teamIds = (row.attr('data-team-ids') || '').split(',').filter(Boolean);
                const teamCount = Number(row.attr('data-team-count') || teamIds.length || 0);

                if (filterTeamId) {
                    if (!teamIds.includes(filterTeamId)) {
                        return null;
                    }
                    return {
                        id: filterTeamId,
                        title: getFilterTeamTitle()
                    };
                }

                if (teamCount <= 1 && teamIds.length === 1) {
                    const onlyId = teamIds[0];
                    const label = row.find('.user-row-team-label[data-team-id="' + onlyId + '"]').text().trim();
                    return {
                        id: onlyId,
                        title: label
                    };
                }

                return null;
            }

            function updateTeamLabelsHighlight() {
                const filterTeamId = getFilterTeamId();
                $('#left_bar .user-row-team-label').removeClass('fw-bold');
                if (filterTeamId) {
                    $('#left_bar .user-row-team-label[data-team-id="' + filterTeamId + '"]').addClass('fw-bold');
                }
            }

            function isUserDetailBtnDisabled(btn) {
                return btn.attr('aria-disabled') === 'true';
            }

            function updateDetailButtonsState() {
                const filterTeamId = getFilterTeamId();
                const leftBar = document.getElementById('left_bar');

                if (typeof KidsCrmTooltip !== 'undefined' && leftBar) {
                    KidsCrmTooltip.dispose(leftBar, { scopes: ['hint'] });
                }

                $('#left_bar .user-row').each(function () {
                    const row = $(this);
                    const btn = row.find('.user-detail-btn');
                    const teamCount = Number(row.attr('data-team-count') || 0);
                    const teamIds = (row.attr('data-team-ids') || '').split(',').filter(Boolean);
                    const count = teamCount || teamIds.length;

                    const disabled = !filterTeamId && count > 1;

                    btn.prop('disabled', false);
                    btn.removeClass('disabled is-visually-disabled');
                    btn.removeAttr('aria-disabled');
                    btn.removeAttr('title');
                    btn.removeAttr('data-kids-tooltip-hint');
                    btn.removeAttr('data-bs-toggle');
                    btn.removeAttr('data-bs-placement');
                    btn.removeAttr('data-bs-custom-class');
                    row.removeClass('setting-prices-user-row--blocked');

                    if (disabled) {
                        btn.attr('aria-disabled', 'true');
                        btn.addClass('is-visually-disabled');
                        btn.attr('title', DETAIL_DISABLED_TITLE);
                        btn.attr('data-kids-tooltip-hint', '1');
                        btn.attr('data-bs-toggle', 'tooltip');
                        btn.attr('data-bs-placement', 'top');
                        btn.attr('data-bs-custom-class', 'ulp-assignment-paid-tooltip');
                        row.addClass('setting-prices-user-row--blocked');
                    }
                });

                if (typeof KidsCrmTooltip !== 'undefined' && leftBar) {
                    KidsCrmTooltip.init(leftBar, { scopes: ['hint'] });
                }
            }

            function resetRightPanel() {
                currentUserId = null;
                currentTeamId = null;
                editingNewMonth = null;
                lastPricesPayload = null;
                $('#left_bar .user-row').removeClass('wrap-team--active');
                $('#left_bar .detail').removeClass('action-button');
                $('#user-detail-name').text('Выберите ученика слева');
                $('#user-detail-team').text('');
                $('#user-prices-table-wrapper').html(
                    '<p class="text-muted mb-0">После выбора ученика здесь появятся цены по месяцам за выбранный год.</p>'
                );
                $('#save-user-year-prices').prop('disabled', true);
            }

            function showToast(message, isError) {
                const toastEl = document.getElementById('priceToast');
                const bodyEl = document.getElementById('priceToastBody');

                if (!toastEl || !bodyEl) return;

                bodyEl.textContent = message || (isError ? 'Ошибка' : 'Сохранено');

                toastEl.classList.remove('bg-success', 'bg-danger');
                toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');

                if (!window.bootstrap || !bootstrap.Toast) {
                    return;
                }

                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }

            function postManualPaidForUser(userId, teamId, selectedDate, mode, comment, onError) {
                const csrf = $('meta[name="csrf-token"]').attr('content');
                return $.ajax({
                    url: '/admin/setting-prices/manual-paid',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({
                        user_id: userId,
                        team_id: teamId,
                        selectedDate: selectedDate,
                        mode: mode,
                        comment: comment
                    })
                }).fail(function (xhr) {
                    let msg = 'Не удалось сохранить ручную отметку.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        const errs = xhr.responseJSON.errors;
                        if (errs && errs.record && errs.record[0]) {
                            msg = errs.record[0];
                        }
                        if (errs && errs.comment && errs.comment[0]) {
                            msg = errs.comment[0];
                        }
                    }
                    if (typeof onError === 'function') {
                        onError(msg);
                    } else {
                        showToast(msg, true);
                    }
                });
            }

            function renderUserPricesTable(response) {
                const wrapper = $('#user-prices-table-wrapper');
                const btnSave = $('#save-user-year-prices');

                if (!response || !response.months || !response.months.length) {
                    wrapper.html('<p class="text-muted mb-0">Нет данных по ценам за этот год.</p>');
                    btnSave.prop('disabled', true);
                    lastPricesPayload = response;
                    lastLessonPackages = [];
                    return;
                }

                lastPricesPayload = response;
                lastLessonPackages = Array.isArray(response.lessonPackages) ? response.lessonPackages : [];
                const canManual = !!response.can_manage_manual_paid;

                let html = '<div class="user-prices-year-table">';
                html += '<div class="user-prices-year-header d-flex align-items-center gap-1 gap-md-2 flex-nowrap w-100 min-w-0 mb-2 pb-2 border-bottom small text-muted">';
                html += '<div class="setting-prices-monthly-name-col d-flex align-items-center min-w-0 flex-grow-1">Месяц</div>';
                html += '<div class="setting-prices-monthly-package flex-shrink-0 user-prices-year-header-package">Абонемент</div>';
                html += '<div class="setting-prices-monthly-price flex-shrink-0 user-prices-year-header-price">Цена, ₽</div>';
                html += '<div class="setting-prices-monthly-status flex-shrink-0 min-w-0 user-prices-year-header-status" aria-hidden="true"></div>';
                html += '</div>';

                response.months.forEach(function (item) {
                    const effectivePaid = !!item.effective_is_paid;
                    const disabledAttr = effectivePaid ? 'disabled' : '';
                    const packageId = item.lesson_package_id != null ? item.lesson_package_id : '';

                    const hasRow = !!item.has_price_row;
                    const manualNote = item.manual_paid_note || '';
                    const hasManual = item.is_manual_paid !== null && item.is_manual_paid !== undefined;
                    const noteRaw = String(manualNote).trim();
                    const noteForTitle = hasManual
                        ? (noteRaw !== '' ? manualNote : 'Комментарий к ручному изменению не заполнен.')
                        : '';

                    let infoIcon = '';
                    if (hasManual) {
                        infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" tabindex="0" ' +
                            'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" ' +
                            'title="' + escapeAttr(noteForTitle) + '" ' +
                            'aria-label="Комментарий к ручной отметке оплаты"></i>';
                    }

                    let pencilHtml = '';
                    if (canManual && hasRow) {
                        pencilHtml = '<button type="button" class="btn btn-link btn-sm p-0 user-price-manual-edit setting-prices-monthly-edit-btn" ' +
                            'data-new-month="' + item.new_month + '" title="Изменить статус оплаты">' +
                            '<i class="fa fa-edit" aria-hidden="true"></i></button>';
                    }

                    const monthTitle = escapeAttr(item.month_label);
                    const paidLabel = effectivePaid ? 'Оплачено' : 'Не оплачено';
                    const paidIconHtml = effectivePaid
                        ? '<i class="fa fa-check green-check setting-prices-monthly-paid-icon" tabindex="0" ' +
                            'data-kids-tooltip-hint="1" data-bs-toggle="tooltip" data-bs-placement="top" ' +
                            'data-bs-custom-class="ulp-assignment-paid-tooltip" title="Оплачено" ' +
                            'aria-label="Оплачено"></i>'
                        : '<span class="setting-prices-monthly-paid-empty" aria-hidden="true"></span>';
                    const statusViewHtml =
                        '<div class="user-price-status-view setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1">' +
                        '<div class="user-price-badge-wrap position-relative setting-prices-monthly-badge-wrap" aria-label="' + paidLabel + '">' +
                        paidIconHtml +
                        infoIcon +
                        '</div>' +
                        '<div class="setting-prices-monthly-edit-wrap">' + pencilHtml + '</div>' +
                        '</div>';

                    html += '<div class="setting-prices-user-card mb-2 pb-2 border-bottom" data-new-month="' + item.new_month + '">';
                    html += '<div class="setting-prices-monthly-row d-flex align-items-center gap-1 gap-md-2 flex-nowrap w-100 min-w-0">';
                    html += '<div class="setting-prices-monthly-name-col d-flex align-items-center min-w-0 flex-grow-1 gap-1">';
                    html += '<span class="setting-prices-monthly-name-text text-truncate" title="' + monthTitle + '">' + escapeHtml(item.month_label) + '</span>';
                    html += '</div>';
                    html += '<div class="setting-prices-monthly-package flex-shrink-0">';
                    html += '<select class="form-select form-select-sm setting-prices-monthly-package-select" ' +
                        disabledAttr + ' aria-label="Абонемент">' +
                        buildPackageSelectOptions(packageId) +
                        '</select>';
                    html += '</div>';
                    html += '<div class="setting-prices-monthly-price flex-shrink-0">';
                    html += '<input type="number" step="0.01" min="0" class="form-control form-control-sm user-price-input setting-prices-monthly-price-input" ' +
                        'data-new-month="' + item.new_month + '" ' +
                        'data-effective-paid="' + (effectivePaid ? '1' : '0') + '" ' +
                        'value="' + escapeAttr(formatPriceValue(item.price)) + '" ' + disabledAttr + ' aria-label="Цена за месяц">';
                    html += '</div>';
                    html += '<div class="setting-prices-monthly-status flex-shrink-0 min-w-0 user-price-status-cell">';
                    html += statusViewHtml;
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });

                html += '</div>';

                wrapper.html(html);
                btnSave.prop('disabled', false);

                if (typeof window.initManualPaidBadgeTooltips === 'function') {
                    window.initManualPaidBadgeTooltips(wrapper.get(0));
                }
                if (window.KidsCrmTooltip) {
                    window.KidsCrmTooltip.dispose(wrapper.get(0), { scopes: ['hint'] });
                    window.KidsCrmTooltip.init(wrapper.get(0), { scopes: ['hint'] });
                }
            }

            function enterEditModeFor(newMonth) {
                if (!lastPricesPayload || !lastPricesPayload.months) {
                    return;
                }
                const item = lastPricesPayload.months.find(function (m) {
                    return m.new_month === newMonth;
                });
                if (!item || !item.has_price_row) {
                    return;
                }

                editingNewMonth = newMonth;
                const $row = $('#user-prices-table-wrapper .setting-prices-user-card[data-new-month="' + newMonth + '"]');
                const $cell = $row.find('.user-price-status-cell');
                const eff = !!item.effective_is_paid;
                const selVal = eff ? '1' : '0';

                const editHtml = '' +
                    '<div class="user-price-status-edit setting-prices-monthly-edit-panel">' +
                    '<div class="d-flex flex-nowrap align-items-center gap-1 justify-content-end">' +
                    '<select class="form-select form-select-sm user-manual-paid-select setting-prices-monthly-paid-select" ' +
                    'data-initial="' + selVal + '" aria-label="Статус оплаты">' +
                    '<option value="1"' + (eff ? ' selected' : '') + '>Оплачено</option>' +
                    '<option value="0"' + (!eff ? ' selected' : '') + '>Не оплачено</option>' +
                    '</select>' +
                    '<button type="button" class="btn btn-sm btn-danger user-price-edit-cancel d-inline-flex align-items-center justify-content-center px-2" title="Отмена" aria-label="Отмена">' +
                    '<i class="fa fa-times" aria-hidden="true"></i>' +
                    '</button>' +
                    '</div>' +
                    '<div class="manual-paid-error small text-danger mt-1" style="display:none"></div>' +
                    '</div>';

                $cell.html(editHtml);
            }

            function loadUserYearPrices(done) {
                const userId = currentUserId;
                const teamId = currentTeamId;
                if (!userId || !teamId) {
                    return;
                }

                const year = $('#user-year-select').val();
                const token = $('meta[name="csrf-token"]').attr('content');

                $.ajax({
                    url: '/admin/setting-prices/user-year-prices',
                    method: 'POST',
                    data: {
                        user_id: userId,
                        team_id: teamId,
                        year: year,
                        _token: token
                    },
                    success: function (response) {
                        if (response.success) {
                            editingNewMonth = null;
                            renderUserPricesTable(response);
                            if (typeof done === 'function') {
                                done();
                            }
                        } else {
                            $('#user-prices-table-wrapper').html(
                                '<p class="text-danger mb-0">' + (response.message || 'Не удалось загрузить данные.') + '</p>'
                            );
                            $('#save-user-year-prices').prop('disabled', true);
                            showToast(response.message || 'Не удалось загрузить данные.', true);
                        }
                    },
                    error: function () {
                        $('#user-prices-table-wrapper').html(
                            '<p class="text-danger mb-0">Ошибка при загрузке данных.</p>'
                        );
                        $('#save-user-year-prices').prop('disabled', true);
                        showToast('Ошибка при загрузке данных.', true);
                    }
                });
            }

            function filterUsers() {
                const teamId = $('#filter-team').val();
                const query = ($('#filter-user').val() || '').toLowerCase().trim();

                $('#left_bar .user-row').each(function () {
                    const item = $(this);
                    const itemTeamIds = (item.attr('data-team-ids') || '').split(',').filter(Boolean);
                    const userName = (item.attr('data-user-name') || '').toLowerCase();

                    const matchTeam = !teamId || itemTeamIds.includes(teamId.toString());
                    const matchName = !query || userName.indexOf(query) !== -1;

                    if (matchTeam && matchName) {
                        item.removeClass('d-none');
                    } else {
                        item.addClass('d-none');
                    }
                });
            }

            $(document).ready(function () {
                // JSON-запросы не передают _token в теле — нужен заголовок (как на вкладке «по месяцам»).
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $('#user-prices-table-wrapper').on('click', '.user-price-manual-edit', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const newMonth = $(this).data('new-month');
                    if (!newMonth) {
                        return;
                    }

                    if (editingNewMonth === newMonth) {
                        editingNewMonth = null;
                        loadUserYearPrices();
                        return;
                    }

                    if (editingNewMonth) {
                        loadUserYearPrices(function () {
                            enterEditModeFor(newMonth);
                        });
                    } else {
                        enterEditModeFor(newMonth);
                    }
                });

                $('#user-prices-table-wrapper').on('click', '.user-price-edit-cancel', function (e) {
                    e.preventDefault();
                    editingNewMonth = null;
                    loadUserYearPrices();
                });

                $('#user-prices-table-wrapper').on('change', '.setting-prices-monthly-package-select', function () {
                    const select = this;
                    if (select.disabled) {
                        return;
                    }
                    const selectedOpt = select.options[select.selectedIndex];
                    const pkgPrice = selectedOpt ? selectedOpt.getAttribute('data-price') : null;
                    const $input = $(select).closest('.setting-prices-user-card').find('.user-price-input');
                    if (!$input.length || $input.prop('disabled')) {
                        return;
                    }
                    if (select.value && pkgPrice != null && pkgPrice !== '') {
                        $input.val(formatPriceValue(pkgPrice));
                    }
                });

                $('#user-prices-table-wrapper').on('change', '.user-manual-paid-select', function () {
                    const $sel = $(this);
                    const $tr = $sel.closest('.setting-prices-user-card');
                    const newMonth = $tr.data('new-month');
                    const year = $('#user-year-select').val();
                    const val = $sel.val();
                    const initial = $sel.data('initial');

                    if (String(val) === String(initial)) {
                        return;
                    }

                    if (!lastPricesPayload || !currentUserId || !currentTeamId) {
                        return;
                    }

                    const item = lastPricesPayload.months.find(function (m) {
                        return m.new_month === newMonth;
                    });
                    if (!item) {
                        return;
                    }

                    const selectedDate = item.month_label.trim() + ' ' + year;
                    const mode = val === '1' ? 'paid' : 'unpaid';
                    const labelWant = val === '1' ? 'оплачено' : 'не оплачено';

                    $sel.val(initial);

                    if (typeof window.showManualPaidCommentModal !== 'function') {
                        showToast('Не загружена форма подтверждения. Обновите страницу.', true);
                        return;
                    }

                    window.showManualPaidCommentModal(
                        'Подтверждение',
                        'Будет установлен статус: «' + labelWant + '». Укажите комментарий.',
                        function (comment) {
                            postManualPaidForUser(currentUserId, currentTeamId, selectedDate, mode, comment, function (msg) {
                                showToast(msg, true);
                            }).done(function (res) {
                                if (res && res.success) {
                                    editingNewMonth = null;
                                    loadUserYearPrices();
                                    showToast('Статус оплаты обновлён.', false);
                                }
                            });
                        }
                    );
                });

                $('#left_bar').on('click', '.user-row', function (e) {
                    if ($(e.target).closest('.user-detail-btn[aria-disabled="true"]').length) {
                        return;
                    }

                    const row = $(this);
                    const btn = row.find('.user-detail-btn');
                    if (isUserDetailBtnDisabled(btn)) {
                        return;
                    }

                    const teamContext = resolveTeamContext(row);
                    if (!teamContext) {
                        showToast(DETAIL_DISABLED_TITLE, true);
                        return;
                    }

                    const userId = row.attr('data-user-id');
                    const userName = row.attr('data-user-name') || '';

                    $('#left_bar .user-row').removeClass('wrap-team--active');
                    $('#left_bar .detail').removeClass('action-button');
                    row.addClass('wrap-team--active');
                    row.find('.detail').addClass('action-button');

                    currentUserId = userId;
                    currentTeamId = teamContext.id;
                    editingNewMonth = null;

                    $('#user-detail-name').text(userName);
                    $('#user-detail-team').text(teamContext.title || '');

                    loadUserYearPrices();
                });

                $('#user-year-select').on('change', function () {
                    editingNewMonth = null;
                    loadUserYearPrices();
                });

                $('#filter-team').on('change', function () {
                    updateTeamLabelsHighlight();
                    updateDetailButtonsState();
                    filterUsers();
                    resetRightPanel();
                });

                $('#filter-user').on('input', function () {
                    filterUsers();
                });

                updateTeamLabelsHighlight();
                updateDetailButtonsState();

                $('#save-user-year-prices').on('click', function () {
                    const userId = currentUserId;
                    const teamId = currentTeamId;
                    if (!userId || !teamId) {
                        return;
                    }

                    const year = $('#user-year-select').val();
                    const token = $('meta[name="csrf-token"]').attr('content');

                    const payload = [];
                    $('#user-prices-table-wrapper .user-price-input').each(function () {
                        const input = $(this);
                        const effPaid = Number(input.data('effective-paid')) === 1;
                        const newMonth = input.data('new-month');
                        const price = Number(input.val()) || 0;
                        const $card = input.closest('.setting-prices-user-card');
                        const pkgVal = $card.find('.setting-prices-monthly-package-select').val();

                        if (newMonth && !effPaid) {
                            payload.push({
                                new_month: newMonth,
                                price: price,
                                lesson_package_id: pkgVal !== '' ? parseInt(pkgVal, 10) : null
                            });
                        }
                    });

                    if (!payload.length) {
                        showToast('Нет изменений для сохранения.', false);
                        return;
                    }

                    const $saveBtn = $('#save-user-year-prices');

                    showConfirmDeleteModal(
                        'Установка цен по ученику',
                        'Вы уверены, что хотите применить изменения?',
                        function () {
                            $saveBtn.prop('disabled', true);

                            $.ajax({
                                url: '/admin/setting-prices/user-year-prices/save',
                                method: 'POST',
                                data: {
                                    user_id: userId,
                                    team_id: teamId,
                                    year: year,
                                    prices: payload,
                                    _token: token
                                },
                                success: function (response) {
                                    if (response.success) {
                                        showToast('Изменения сохранены.', false);
                                        loadUserYearPrices();
                                    } else {
                                        showToast(response.message || 'Не удалось сохранить изменения.', true);
                                    }
                                },
                                error: function () {
                                    showToast('Ошибка при сохранении изменений.', true);
                                },
                                complete: function () {
                                    $saveBtn.prop('disabled', false);
                                }
                            });
                        }
                    );
                });
            });
        })();
    </script>
@endpush
