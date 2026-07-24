import './kids-tooltip.js';
import './setting-prices-manual-paid-modal.js';

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Левая колонка: названия групп — ellipsis + KidsCrmTooltip только при обрезке.
    requestAnimationFrame(function () {
        const leftBar = document.getElementById('left_bar');
        if (leftBar && window.KidsCrmTooltip) {
            window.KidsCrmTooltip.init(leftBar, { scopes: ['text'] });
        }
        initTeamPackageRows();
    });

    let usersPrice = [];
    let lastCanManageManualPaid = false;
    let lastUsersTeam = [];
    let lastTeamId = null;
    /** @type {Array<{id:number,name:string,price:number}>} */
    let lastLessonPackages = [];
    /** @type {string|null} */
    let editingMonthlyUserId = null;

    function disposeTeamOkTooltip(okBtn) {
        if (!okBtn || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }
        const existing = bootstrap.Tooltip.getInstance(okBtn);
        if (existing) {
            existing.dispose();
        }
        // На случай, если tip ещё в DOM после dispose
        document.querySelectorAll('.tooltip.show').forEach(function (tipEl) {
            if (tipEl.id && okBtn.getAttribute('aria-describedby') === tipEl.id) {
                tipEl.remove();
            }
        });
        okBtn.removeAttribute('aria-describedby');
    }

    function syncTeamOkDisabledHint(okBtn, isDisabled) {
        if (!okBtn) {
            return;
        }

        // Сначала снимаем Bootstrap Tooltip, иначе после удаления attrs dispose его не найдёт.
        disposeTeamOkTooltip(okBtn);

        // Не используем native disabled — иначе нет hover/tooltip.
        okBtn.disabled = false;
        okBtn.classList.remove('disabled');

        if (isDisabled) {
            okBtn.setAttribute('aria-disabled', 'true');
            okBtn.classList.add('is-visually-disabled');
            okBtn.setAttribute('title', 'Выберите абонемент');
            okBtn.setAttribute('data-kids-tooltip-hint', '1');
            okBtn.setAttribute('data-bs-toggle', 'tooltip');
            okBtn.setAttribute('data-bs-placement', 'top');
            okBtn.setAttribute('data-bs-custom-class', 'ulp-assignment-paid-tooltip');
            return;
        }

        okBtn.removeAttribute('aria-disabled');
        okBtn.classList.remove('is-visually-disabled');
        okBtn.removeAttribute('title');
        okBtn.removeAttribute('data-kids-tooltip-hint');
        okBtn.removeAttribute('data-bs-toggle');
        okBtn.removeAttribute('data-bs-placement');
        okBtn.removeAttribute('data-bs-custom-class');
    }

    function refreshTeamOkTooltips() {
        const leftBar = document.getElementById('left_bar');
        if (!leftBar || !window.KidsCrmTooltip) {
            return;
        }
        // dispose только по селектору hint; инстансы без attrs уже сняты в syncTeamOkDisabledHint
        window.KidsCrmTooltip.dispose(leftBar, { scopes: ['hint'] });
        window.KidsCrmTooltip.init(leftBar, { scopes: ['hint'] });
    }

    function isTeamOkDisabled(okBtn) {
        if (!okBtn) {
            return true;
        }
        return okBtn.getAttribute('aria-disabled') === 'true' || !!okBtn.disabled;
    }

    function syncTeamRowPackageUi(rowEl) {
        if (!rowEl) {
            return;
        }
        const select = rowEl.querySelector('.setting-prices-team-package-select');
        const priceEl = rowEl.querySelector('.setting-prices-team-price-value');
        const okBtn = rowEl.querySelector('.ok');
        if (!select || !priceEl) {
            return;
        }

        const pkgVal = select.value;
        const selectedOpt = select.options[select.selectedIndex];
        const legacyPrice = rowEl.getAttribute('data-legacy-price');

        if (pkgVal && selectedOpt) {
            const pkgPrice = selectedOpt.getAttribute('data-price');
            priceEl.textContent = formatPriceValue(pkgPrice);
            priceEl.setAttribute('data-price', String(pkgPrice != null ? pkgPrice : ''));
            syncTeamOkDisabledHint(okBtn, false);
        } else {
            priceEl.textContent = formatPriceValue(legacyPrice);
            priceEl.setAttribute('data-price', String(legacyPrice != null ? legacyPrice : '0'));
            syncTeamOkDisabledHint(okBtn, true);
        }
    }

    function initTeamPackageRows() {
        document.querySelectorAll('#left_bar .wrap-team').forEach(function (rowEl) {
            syncTeamRowPackageUi(rowEl);
        });
        syncSetPriceAllTeamsButton();
        refreshTeamOkTooltips();
    }

    function syncSetPriceAllTeamsButton() {
        const btn = document.getElementById('set-price-all-teams');
        if (!btn) {
            return;
        }
        let hasAny = false;
        document.querySelectorAll('#left_bar .setting-prices-team-package-select').forEach(function (sel) {
            if (sel.value) {
                hasAny = true;
            }
        });
        btn.disabled = !hasAny;
    }

    function loadTeamUsersRightColumn(teamId) {
        if (!teamId) {
            return;
        }
        const selectedDate = getSelectedMonthLabel();
        const applyBtn = document.querySelector('#right_bar .btn-setting-prices');
        if (applyBtn) {
            applyBtn.setAttribute('disabled', 'disabled');
        }
        editingMonthlyUserId = null;

        const csrf = $('meta[name="csrf-token"]').attr('content');
        $.ajax({
            url: '/admin/setting-prices/get-team-price',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            data: JSON.stringify({
                teamId: teamId,
                selectedDate: selectedDate
            }),
            success: function (response) {
                if (response.success) {
                    usersPrice = response.usersPrice;
                    lastLessonPackages = Array.isArray(response.lessonPackages)
                        ? response.lessonPackages
                        : [];
                    lastTeamId = String(teamId);
                    const usersTeam = response.usersTeam;
                    const canManage = !!response.can_manage_manual_paid;
                    renderUsersRightColumn(usersTeam, usersPrice, canManage);
                }
            },
            error: function (xhr, status, error) {
                console.error('Ошибка: ' + error);
                console.error('Статус: ' + status);
                console.dir(xhr);
            }
        });
    }

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
        if (s == null || s === '') {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatPriceValue(price) {
        const n = Number(price);
        if (!Number.isFinite(n)) {
            return '0';
        }
        if (Math.abs(n - Math.round(n)) < 0.001) {
            return String(Math.round(n));
        }
        return n.toFixed(2);
    }

    function findLessonPackage(packageId) {
        if (packageId == null || packageId === '') {
            return null;
        }
        const id = String(packageId);
        return lastLessonPackages.find(function (p) {
            return String(p.id) === id;
        }) || null;
    }

    function buildPackageSelectOptions(selectedPackageId) {
        let html = '<option value="">Без абонемента</option>';
        for (let i = 0; i < lastLessonPackages.length; i++) {
            const pkg = lastLessonPackages[i];
            const selected = selectedPackageId != null && String(pkg.id) === String(selectedPackageId)
                ? ' selected'
                : '';
            html += '<option value="' + escapeAttr(pkg.id) + '"' + selected + '>'
                + escapeHtml(pkg.name) + '</option>';
        }
        return html;
    }

    function getSelectedMonthLabel() {
        const sel = document.getElementById('single-select-date');
        if (!sel || !sel.options[sel.selectedIndex]) {
            return '';
        }
        return sel.options[sel.selectedIndex].textContent;
    }

    function clearTeamRowHighlight() {
        document.querySelectorAll('#left_bar .wrap-team').forEach(function (el) {
            el.classList.remove('wrap-team--active');
        });
    }

    /**
     * Открыть группу справа (клик по строке слева).
     * @param {HTMLElement|null} rowEl
     */
    function openTeamDetail(rowEl) {
        if (!rowEl) {
            return;
        }

        clearTeamRowHighlight();
        rowEl.classList.add('wrap-team--active');
        lastTeamId = rowEl.id || null;
        loadTeamUsersRightColumn(rowEl.id);
    }

    function effectivePaidFromUserPrice(row) {
        if (typeof row.effective_is_paid !== 'undefined') {
            return !!row.effective_is_paid;
        }
        return !!row.is_paid;
    }

    /**
     * Переносит текущие значения select/инпута из DOM в usersPrice,
     * чтобы повторный render не откатывал несохранённый выбор абонемента/цены.
     */
    function syncUsersPriceFromDom() {
        const userRows = document.querySelectorAll('#right_bar .wrap-users .setting-prices-user-card');
        for (let j = 0; j < userRows.length; j++) {
            const userId = userRows[j].getAttribute('data-user-id');
            if (!userId) {
                continue;
            }
            const priceInput = userRows[j].querySelector('.setting-prices-monthly-price-input');
            const packageSelect = userRows[j].querySelector('.setting-prices-monthly-package-select');
            const idx = usersPrice.findIndex(function (u) {
                return String(u.user_id) === String(userId);
            });
            if (idx < 0) {
                continue;
            }
            if (priceInput) {
                usersPrice[idx].price = priceInput.value;
            }
            if (packageSelect) {
                const pkgVal = packageSelect.value;
                usersPrice[idx].lesson_package_id = pkgVal !== '' ? parseInt(pkgVal, 10) : null;
            }
        }
    }

    function postManualPaid(userId, teamId, selectedDate, mode, comment, errorEl) {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        return $.ajax({
            url: '/admin/setting-prices/manual-paid',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            data: JSON.stringify({
                user_id: userId,
                team_id: teamId,
                selectedDate: selectedDate,
                mode: mode,
                comment: comment
            })
        }).done(function (res) {
            if (res && res.success && res.user_price) {
                syncUsersPriceFromDom();
                const updated = res.user_price;
                const idx = usersPrice.findIndex(function (u) {
                    return String(u.user_id) === String(updated.user_id);
                });
                if (idx >= 0) {
                    usersPrice[idx] = updated;
                }
                editingMonthlyUserId = null;
                renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
                if (errorEl) {
                    errorEl.style.display = 'none';
                    errorEl.textContent = '';
                }
            }
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
            if (errorEl) {
                errorEl.style.display = 'block';
                errorEl.textContent = msg;
            } else {
                console.error(msg);
            }
        });
    }

    function renderUsersRightColumn(usersTeam, usersPriceList, canManage) {
        lastCanManageManualPaid = !!canManage;
        lastUsersTeam = usersTeam || [];
        const rightBar = $('.wrap-users');
        const rightBarEl = rightBar.get(0);

        if (rightBarEl && window.KidsCrmTooltip) {
            window.KidsCrmTooltip.dispose(rightBarEl, { scopes: ['text', 'manualPaid', 'hint'] });
        }

        rightBar.empty();

        try {
            rightBar.attr('data-users-team-json', JSON.stringify(usersTeam || []));
        } catch (e) {
            rightBar.removeAttr('data-users-team-json');
        }

        const selectedDate = getSelectedMonthLabel();
        rightBar.attr('data-selected-date', selectedDate);

        for (let i = 0; i < usersPriceList.length; i++) {
            const up = usersPriceList[i];
            const userTeam = usersTeam.find(team => team.id === up.user_id);

            const eff = effectivePaidFromUserPrice(up);

            const last = (userTeam && userTeam.lastname) ? String(userTeam.lastname).trim() : '';
            const first = (userTeam && userTeam.name) ? String(userTeam.name).trim() : '';
            const userNameFormatted = (i + 1) + '. ' + ((last || first) ? `${last} ${first}`.trim() : 'Имя не найдено');

            const uid = userTeam ? String(userTeam.id) : '';
            const hasManual = up.is_manual_paid !== null && up.is_manual_paid !== undefined;
            const noteRaw = (up.manual_paid_note != null && String(up.manual_paid_note).trim() !== '')
                ? String(up.manual_paid_note)
                : '';
            const noteForTitle = hasManual
                ? (noteRaw !== '' ? noteRaw : 'Комментарий к ручному изменению не заполнен.')
                : '';

            let infoIcon = '';
            if (hasManual) {
                infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" tabindex="0" '
                    + 'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" '
                    + 'title="' + escapeAttr(noteForTitle) + '" '
                    + 'aria-label="Комментарий к ручной отметке оплаты"></i>';
            }

            let pencilHtml = '';
            if (canManage && uid) {
                pencilHtml = '<button type="button" class="btn btn-link btn-sm p-0 user-price-manual-edit setting-prices-monthly-edit-btn" data-user-id="' + uid + '" title="Изменить статус и сумму">' +
                    '<i class="fa fa-edit" aria-hidden="true"></i></button>';
            }

            const isEditing = uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === uid;

            let statusCellHtml = '';
            if (isEditing) {
                const selVal = eff ? '1' : '0';
                statusCellHtml =
                    '<div class="user-price-status-edit setting-prices-monthly-edit-panel">' +
                    '<div class="d-flex flex-nowrap align-items-center gap-1 justify-content-end">' +
                    '<select class="form-select form-select-sm user-manual-paid-select setting-prices-monthly-paid-select" data-initial="' + selVal + '" aria-label="Статус оплаты">' +
                    '<option value="1"' + (eff ? ' selected' : '') + '>Оплачено</option>' +
                    '<option value="0"' + (!eff ? ' selected' : '') + '>Не оплачено</option>' +
                    '</select>' +
                    '<button type="button" class="btn btn-sm btn-danger user-price-edit-cancel d-inline-flex align-items-center justify-content-center px-2" title="Отмена" aria-label="Отмена">' +
                    '<i class="fa fa-times" aria-hidden="true"></i></button>' +
                    '</div>' +
                    '<div class="manual-paid-error small text-danger mt-1" style="display:none"></div>' +
                    '</div>';
            } else {
                const paidLabel = eff ? 'Оплачено' : 'Не оплачено';
                const paidIconHtml = eff
                    ? '<i class="fa fa-check green-check setting-prices-monthly-paid-icon" tabindex="0" '
                        + 'data-kids-tooltip-hint="1" data-bs-toggle="tooltip" data-bs-placement="top" '
                        + 'data-bs-custom-class="ulp-assignment-paid-tooltip" title="Оплачено" '
                        + 'aria-label="Оплачено"></i>'
                    : '<span class="setting-prices-monthly-paid-empty" aria-hidden="true"></span>';
                statusCellHtml =
                    '<div class="user-price-status-view setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1">' +
                    '<div class="user-price-badge-wrap position-relative setting-prices-monthly-badge-wrap" aria-label="' + paidLabel + '">' +
                    paidIconHtml +
                    infoIcon +
                    '</div>' +
                    '<div class="setting-prices-monthly-edit-wrap">' + pencilHtml + '</div>' +
                    '</div>';
            }

            const packageId = up.lesson_package_id != null ? up.lesson_package_id : '';
            const packageSelectDisabled = eff ? 'disabled' : '';
            let priceInputDisabled = 'disabled';
            if (isEditing) {
                priceInputDisabled = '';
            } else if (!canManage && !eff) {
                priceInputDisabled = '';
            }

            const nameHtml = (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.renderText === 'function')
                ? window.KidsCrmTooltip.renderText(userNameFormatted)
                : '<span class="setting-prices-monthly-name-text text-truncate" title="' + escapeAttr(userNameFormatted) + '">'
                    + escapeHtml(userNameFormatted) + '</span>';

            const userBlock = `
                        <div class="setting-prices-user-card mb-2 pb-2 border-bottom" data-user-id="${uid}">
                            <div class="setting-prices-monthly-row d-flex align-items-center gap-1 gap-md-2 flex-nowrap w-100 min-w-0">
                                <div class="setting-prices-monthly-name-col min-w-0">
                                    <span id="${uid}" class="user-name setting-prices-monthly-name-host d-block min-w-0 w-100">${nameHtml}</span>
                                </div>
                                <div class="setting-prices-monthly-package flex-shrink-0">
                                    <select class="form-select form-select-sm setting-prices-monthly-package-select"
                                        ${packageSelectDisabled}
                                        aria-label="Абонемент">
                                        ${buildPackageSelectOptions(packageId)}
                                    </select>
                                </div>
                                <div class="setting-prices-monthly-price flex-shrink-0">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control form-control-sm setting-prices-monthly-price-input"
                                        value="${escapeAttr(formatPriceValue(up.price))}"
                                        ${priceInputDisabled}
                                        aria-label="Цена">
                                </div>
                                <div class="setting-prices-monthly-status flex-shrink-0 min-w-0">
                                    ${statusCellHtml}
                                </div>
                            </div>
                        </div>`;

            rightBar.append(userBlock);
        }

        document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');

        // После layout — корректно измерить overflow для KidsCrmTooltip (ellipsis только при обрезке).
        requestAnimationFrame(function () {
            if (!rightBarEl || !window.KidsCrmTooltip) {
                return;
            }
            window.KidsCrmTooltip.init(rightBarEl, { scopes: ['text', 'manualPaid', 'hint'] });
        });
    }

    $(document).on('change', '#right_bar .wrap-users .setting-prices-monthly-package-select', function () {
        const $select = $(this);
        const $card = $select.closest('.setting-prices-user-card');
        const uid = $card.attr('data-user-id');
        const pkg = findLessonPackage($select.val());
        const $priceInput = $card.find('.setting-prices-monthly-price-input');

        if (pkg) {
            $priceInput.val(formatPriceValue(pkg.price));
        }

        // Сразу фиксируем выбор в состоянии — иначе карандаш перерисует строку из старых данных БД.
        if (uid) {
            const idx = usersPrice.findIndex(function (u) {
                return String(u.user_id) === String(uid);
            });
            if (idx >= 0) {
                const pkgVal = $select.val();
                usersPrice[idx].lesson_package_id = pkgVal !== '' ? parseInt(pkgVal, 10) : null;
                usersPrice[idx].price = $priceInput.val();
            }
        }

        // Вне режима редактирования сумму снова блокируем (выбор абонемента подставляет цену сам).
        // В режиме карандаша оставляем поле доступным для ручной правки.
        const inEditMode = uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === String(uid);
        if (!inEditMode && lastCanManageManualPaid) {
            $priceInput.prop('disabled', true);
        }
    });

    $(document).on('input change', '#right_bar .wrap-users .setting-prices-monthly-price-input', function () {
        const $input = $(this);
        const uid = $input.closest('.setting-prices-user-card').attr('data-user-id');
        if (!uid) {
            return;
        }
        const idx = usersPrice.findIndex(function (u) {
            return String(u.user_id) === String(uid);
        });
        if (idx >= 0) {
            usersPrice[idx].price = $input.val();
        }
    });

    $(document).on('click', '#right_bar .wrap-users .user-price-manual-edit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const uid = $(this).attr('data-user-id');
        if (!uid) {
            return;
        }

        syncUsersPriceFromDom();

        if (String(editingMonthlyUserId) === String(uid)) {
            editingMonthlyUserId = null;
            renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
            return;
        }

        editingMonthlyUserId = uid;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
    });

    $(document).on('click', '#right_bar .wrap-users .user-price-edit-cancel', function (e) {
        e.preventDefault();
        syncUsersPriceFromDom();
        editingMonthlyUserId = null;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
    });

    $(document).on('change', '#right_bar .wrap-users .user-manual-paid-select', function () {
        const $sel = $(this);
        const $card = $sel.closest('.setting-prices-user-card');
        const userId = $card.attr('data-user-id');
        const val = $sel.val();
        const initial = $sel.data('initial');

        if (String(val) === String(initial)) {
            return;
        }

        const selectedDate = getSelectedMonthLabel();
        const mode = val === '1' ? 'paid' : 'unpaid';
        const labelWant = val === '1' ? 'оплачено' : 'не оплачено';
        const errBox = $card.find('.manual-paid-error')[0];

        $sel.val(initial);

        if (typeof window.showManualPaidCommentModal !== 'function') {
            console.error('showManualPaidCommentModal not available');
            return;
        }

        window.showManualPaidCommentModal(
            'Подтверждение',
            'Будет установлен статус: «' + labelWant + '». Укажите комментарий.',
            function (comment) {
                if (!lastTeamId) {
                    if (errorEl) {
                        errorEl.style.display = 'block';
                        errorEl.textContent = 'Не выбрана группа.';
                    }
                    return;
                }
                postManualPaid(userId, lastTeamId, selectedDate, mode, comment, errBox);
            }
        );
    });

    $(document).on('change', '#left_bar .setting-prices-team-package-select', function () {
        const rowEl = this.closest('.wrap-team');
        syncTeamRowPackageUi(rowEl);
        syncSetPriceAllTeamsButton();
        refreshTeamOkTooltips();
    });

    document.querySelectorAll('#left_bar .wrap-team').forEach(function (rowEl) {
        rowEl.addEventListener('click', function (e) {
            if (e.target.closest('select, input, button, .ok, label, a')) {
                return;
            }
            openTeamDetail(rowEl);
        });
    });

    const okButtons = document.querySelectorAll('#left_bar .ok');
    for (let i = 0; i < okButtons.length; i++) {
        let button = okButtons[i];
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const parentDiv = this.closest('.wrap-team');
            if (!parentDiv || isTeamOkDisabled(button)) {
                return;
            }

            const packageSelect = parentDiv.querySelector('.setting-prices-team-package-select');
            const packageId = packageSelect ? packageSelect.value : '';
            if (!packageId) {
                return;
            }

            const selectedDate = getSelectedMonthLabel();
            const priceEl = parentDiv.querySelector('.setting-prices-team-price-value');

            if (priceEl) {
                priceEl.classList.remove('animated-input');
            }

            showConfirmDeleteModal(
                'Подтвердите действие',
                'Вы действительно хотите установить абонемент для этой группы?',
                function () {
                    const csrf = $('meta[name="csrf-token"]').attr('content');
                    $.ajax({
                        url: '/admin/setting-prices/set-team-price',
                        method: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        data: JSON.stringify({
                            teamId: parentDiv.id,
                            lesson_package_id: parseInt(packageId, 10),
                            selectedDate: selectedDate,
                        }),
                        success: function (response) {
                            if (response.success) {
                                if (typeof response.teamPrice !== 'undefined') {
                                    parentDiv.setAttribute('data-legacy-price', String(response.teamPrice));
                                    if (priceEl) {
                                        priceEl.textContent = formatPriceValue(response.teamPrice);
                                        priceEl.setAttribute('data-price', String(response.teamPrice));
                                        priceEl.classList.add('animated-input');
                                    }
                                }
                                if (String(lastTeamId) === String(parentDiv.id)) {
                                    loadTeamUsersRightColumn(parentDiv.id);
                                }
                            }
                        },
                        error: function (xhr) {
                            let msg = 'Не удалось установить абонемент для группы.';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.message) {
                                    msg = xhr.responseJSON.message;
                                }
                                const errs = xhr.responseJSON.errors;
                                if (errs) {
                                    const firstKey = Object.keys(errs)[0];
                                    if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                                        msg = errs[firstKey][0];
                                    }
                                }
                            }
                            if (typeof showErrorModal === 'function') {
                                showErrorModal('Ошибка', msg);
                            } else {
                                alert(msg);
                            }
                        }
                    });
                }
            );
        });
    }

    $('.set-price-all-teams').on('click', function () {
        if (this.disabled) {
            return;
        }

        showConfirmDeleteModal(
            "Установка тарифов всем группам",
            "Вы уверены, что хотите применить изменения?", function () {
                const selectedDate = getSelectedMonthLabel();
                const applyBtn = document.querySelector('#set-price-all-teams');
                if (applyBtn) {
                    applyBtn.setAttribute('disabled', 'disabled');
                }

                let teamsData = [];
                document.querySelectorAll('#left_bar .wrap-team').forEach(function (teamElement) {
                    let teamId = teamElement.id;
                    let packageSelect = teamElement.querySelector('.setting-prices-team-package-select');
                    let pkgVal = packageSelect ? packageSelect.value : '';
                    if (!pkgVal) {
                        return;
                    }
                    teamsData.push({
                        teamId: teamId,
                        lesson_package_id: parseInt(pkgVal, 10),
                    });
                });

                if (teamsData.length === 0) {
                    syncSetPriceAllTeamsButton();
                    return;
                }

                const csrf = $('meta[name="csrf-token"]').attr('content');
                $.ajax({
                    url: '/admin/setting-prices/set-price-all-teams',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    data: JSON.stringify({
                        selectedDate: selectedDate,
                        teamsData: teamsData
                    }),
                    success: function () {
                        showSuccessModal("Установка тарифов всем группам", "Тарифы группам успешно обновлены.", 1);
                    },
                    error: function (xhr) {
                        syncSetPriceAllTeamsButton();
                        let msg = 'Не удалось применить тарифы.';
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            const errs = xhr.responseJSON.errors;
                            if (errs) {
                                const firstKey = Object.keys(errs)[0];
                                if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                                    msg = errs[firstKey][0];
                                }
                            }
                        }
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Ошибка', msg);
                        } else {
                            $('#errorModal').modal('show');
                        }
                    }
                });

            }
        );
    });

    $('#set-price-all-users').on('click', function () {
        showConfirmDeleteModal(
            "Установка цен в одной группе",
            "Вы уверены, что хотите применить изменения?", function () {

                const selectedDate = getSelectedMonthLabel();

                let updateUsersPrice = function (usersPriceLocal) {
                    const userRows = document.querySelectorAll('.wrap-users .setting-prices-user-card');
                    for (let i = 0; i < usersPriceLocal.length; i++) {
                        for (let j = 0; j < userRows.length; j++) {
                            let userId = userRows[j].getAttribute('data-user-id');
                            let priceInput = userRows[j].querySelector('.setting-prices-monthly-price-input');
                            let packageSelect = userRows[j].querySelector('.setting-prices-monthly-package-select');
                            let price = priceInput ? priceInput.value : null;
                            if (price !== null && String(usersPriceLocal[i].user_id) === String(userId)) {
                                usersPriceLocal[i].price = price;
                                const pkgVal = packageSelect ? packageSelect.value : '';
                                usersPriceLocal[i].lesson_package_id = pkgVal !== '' ? parseInt(pkgVal, 10) : null;
                            }
                        }
                    }
                    return usersPriceLocal;
                };

                usersPrice = updateUsersPrice(usersPrice);

                const csrf = $('meta[name="csrf-token"]').attr('content');
                $.ajax({
                    url: '/admin/setting-prices/set-price-all-users',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    data: JSON.stringify({
                        selectedDate: selectedDate,
                        teamId: lastTeamId,
                        usersPrice: usersPrice,
                    }),
                    success: function (response) {
                        usersPrice = response.usersPrice;
                        if (Array.isArray(response.lessonPackages)) {
                            lastLessonPackages = response.lessonPackages;
                        }

                        document.querySelector('#set-price-all-users').removeAttribute('disabled');

                        showSuccessModal("Установка цен в одной группе", "Цены ученикам в выбранной группе успешно обновлены.");

                        editingMonthlyUserId = null;
                        const wrap = document.querySelector('#right_bar .wrap-users');
                        let usersTeam = [];
                        try {
                            const json = wrap && wrap.getAttribute('data-users-team-json');
                            usersTeam = json ? JSON.parse(json) : [];
                        } catch (e) {
                            usersTeam = [];
                        }
                        renderUsersRightColumn(usersTeam, usersPrice, lastCanManageManualPaid);
                    },
                    error: function (xhr, status, error) {
                        console.log('Error:', error);
                        let msg = 'Не удалось сохранить цены.';
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            const errs = xhr.responseJSON.errors;
                            if (errs) {
                                const firstKey = Object.keys(errs)[0];
                                if (firstKey && errs[firstKey] && errs[firstKey][0]) {
                                    msg = errs[firstKey][0];
                                }
                            }
                        }
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Ошибка', msg);
                        } else {
                            alert(msg);
                        }
                    }
                });
            }
        );
    });
});
