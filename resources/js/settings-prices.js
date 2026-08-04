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
    /** @type {{userId:string,price:*,lesson_package_id:*,is_postpay:*}|null} */
    let editingMonthlySnapshot = null;

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
        editingMonthlySnapshot = null;

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
            const postpayAttr = pkg.is_postpay ? ' data-is-postpay="1"' : ' data-is-postpay="0"';
            const priceAttr = ' data-price="' + escapeAttr(pkg.price) + '"';
            html += '<option value="' + escapeAttr(pkg.id) + '"' + selected + postpayAttr + priceAttr + '>'
                + escapeHtml(pkg.name) + '</option>';
        }
        return html;
    }

    function packageIsPostpay(packageId) {
        const pkg = findLessonPackage(packageId);
        return !!(pkg && pkg.is_postpay);
    }

    function postpayVisitsTooltipTitle() {
        const month = getSelectedMonthLabel();
        if (month) {
            return 'Кол-во занятий за «' + month + '»';
        }
        return 'Кол-во занятий за выбранный месяц';
    }

    const POSTPAY_PRICE_TOOLTIP = 'Сумма рассчитывается как «Стоимость 1 занятия × кол-во посещений за месяц»';

    function applyKidsHintAttrs($el, title) {
        if (!$el || !$el.length) {
            return;
        }
        $el.attr({
            'data-kids-tooltip-hint': '1',
            'data-bs-toggle': 'tooltip',
            'data-bs-placement': 'top',
            'data-bs-custom-class': 'ulp-assignment-paid-tooltip',
            'title': title
        });
    }

    function buildPostpayVisitsHtml(visits) {
        return '<div class="setting-prices-monthly-postpay-visits flex-shrink-0">'
            + '<input type="text" readonly class="form-control form-control-sm setting-prices-monthly-postpay-visits-input"'
            + ' value="' + escapeAttr(String(visits)) + '"'
            + ' aria-label="Посещений за месяц"'
            + ' data-kids-tooltip-hint="1" data-bs-toggle="tooltip" data-bs-placement="top"'
            + ' data-bs-custom-class="ulp-assignment-paid-tooltip"'
            + ' title="' + escapeAttr(postpayVisitsTooltipTitle()) + '">'
            + '</div>';
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
    function isFormerMemberFlag(value) {
        return value === true || value === 1 || value === '1';
    }

    function isFormerMemberRow(up, userTeam) {
        if (up && isFormerMemberFlag(up.is_former_member)) {
            return true;
        }
        if (userTeam && isFormerMemberFlag(userTeam.is_former_member)) {
            return true;
        }
        return false;
    }

    function syncUsersPriceFromDom() {
        const userRows = document.querySelectorAll('#right_bar .wrap-users .setting-prices-user-card');
        for (let j = 0; j < userRows.length; j++) {
            const userId = userRows[j].getAttribute('data-user-id');
            if (!userId) {
                continue;
            }
            // Бывшие участники — только просмотр: не переносим DOM в состояние.
            if (userRows[j].getAttribute('data-is-former-member') === '1') {
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
            const isFormer = isFormerMemberRow(up, userTeam);

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

            // Бывшие участники — только просмотр (без карандаша и режима редактирования).
            // Карандаш — только после фактической установки абонемента.
            const packageId = up.lesson_package_id != null ? up.lesson_package_id : '';
            const hasAbon = packageId !== '';
            let pencilHtml = '';
            if (!isFormer && canManage && uid && hasAbon) {
                pencilHtml = '<button type="button" class="btn btn-link btn-sm p-0 user-price-manual-edit setting-prices-monthly-edit-btn" data-user-id="' + uid + '" title="Изменить статус и сумму">' +
                    '<i class="fa fa-edit" aria-hidden="true"></i></button>';
            }

            const isEditing = !isFormer && uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === uid;

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
                    '<button type="button" class="btn btn-sm btn-success user-price-edit-accept d-inline-flex align-items-center justify-content-center px-2" title="Применить" aria-label="Применить">' +
                    '<i class="fa fa-check" aria-hidden="true"></i></button>' +
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

            const isPostpay = !!(up.is_postpay || packageIsPostpay(packageId));
            const postpayVisits = (up.postpay_visits != null) ? up.postpay_visits : 0;
            // Бывшие: всегда disabled. Текущие: абонемент всегда (если не оплачено);
            // сумма открыта при первичной установке (нет абона), иначе — через карандаш.
            // Postpay: цена только расчётная — инпут всегда readonly.
            let packageSelectDisabled = 'disabled';
            let priceInputDisabled = 'disabled';
            if (!isFormer) {
                packageSelectDisabled = eff ? 'disabled' : '';
                if (isPostpay) {
                    priceInputDisabled = 'disabled';
                } else if (isEditing && !eff) {
                    // Карандаш: сумму можно править только для неоплаченных.
                    priceInputDisabled = '';
                } else if (!eff && (!canManage || !hasAbon)) {
                    // Первичная установка абона / без права карандаша — сумма доступна.
                    priceInputDisabled = '';
                }
            }

            const nameHtml = (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.renderText === 'function')
                ? window.KidsCrmTooltip.renderText(userNameFormatted)
                : '<span class="setting-prices-monthly-name-text text-truncate" title="' + escapeAttr(userNameFormatted) + '">'
                    + escapeHtml(userNameFormatted) + '</span>';

            const formerBadgeHtml = isFormer
                ? '<span class="setting-prices-former-badge text-muted" title="Ученик больше не состоит в этой группе">не в группе</span>'
                : '';

            const formerCardClass = isFormer ? ' setting-prices-user-card--former' : '';
            const formerDataAttr = isFormer ? ' data-is-former-member="1"' : '';
            const abonEstablishedAttr = ' data-abon-established="' + (hasAbon ? '1' : '0') + '"';
            const postpayVisitsHtml = isPostpay ? buildPostpayVisitsHtml(postpayVisits) : '';
            const postpayPriceHintAttrs = isPostpay
                ? (' data-kids-tooltip-hint="1" data-bs-toggle="tooltip" data-bs-placement="top"'
                    + ' data-bs-custom-class="ulp-assignment-paid-tooltip"'
                    + ' title="' + escapeAttr(POSTPAY_PRICE_TOOLTIP) + '"')
                : '';
            const postpayPriceClass = isPostpay ? ' is-postpay-calc' : '';

            const userBlock = `
                        <div class="setting-prices-user-card mb-2 pb-2 border-bottom${formerCardClass}" data-user-id="${uid}"${formerDataAttr}${abonEstablishedAttr} data-is-postpay="${isPostpay ? '1' : '0'}">
                            <div class="setting-prices-monthly-row d-flex align-items-center gap-1 flex-nowrap w-100 min-w-0">
                                <div class="setting-prices-monthly-name-col min-w-0">
                                    <span id="${uid}" class="user-name setting-prices-monthly-name-host d-flex flex-column min-w-0 w-100">${nameHtml}${formerBadgeHtml}</span>
                                </div>
                                <div class="setting-prices-monthly-package flex-shrink-0">
                                    <select class="form-select form-select-sm setting-prices-monthly-package-select"
                                        ${packageSelectDisabled}
                                        aria-label="Абонемент">
                                        ${buildPackageSelectOptions(packageId)}
                                    </select>
                                </div>
                                ${postpayVisitsHtml}
                                <div class="setting-prices-monthly-price flex-shrink-0">
                                    <input type="number" step="0.01" min="0"
                                        class="form-control form-control-sm setting-prices-monthly-price-input${postpayPriceClass}"
                                        value="${escapeAttr(formatPriceValue(up.price))}"
                                        ${priceInputDisabled}
                                        aria-label="Цена"
                                        ${isFormer || isPostpay ? 'readonly' : ''}
                                        ${postpayPriceHintAttrs}>
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
        if ($card.attr('data-is-former-member') === '1') {
            return;
        }
        const uid = $card.attr('data-user-id');
        const pkg = findLessonPackage($select.val());
        const $priceInput = $card.find('.setting-prices-monthly-price-input');
        const isPostpay = !!(pkg && pkg.is_postpay);

        $card.attr('data-is-postpay', isPostpay ? '1' : '0');

        let $visits = $card.find('.setting-prices-monthly-postpay-visits');
        if (isPostpay) {
            if ($visits.length === 0) {
                $visits = $(buildPostpayVisitsHtml(0));
                $card.find('.setting-prices-monthly-package').after($visits);
            }
            const known = uid
                ? usersPrice.find(function (u) {
                    return String(u.user_id) === String(uid);
                })
                : null;
            const knownVisits = (known && known.postpay_visits != null) ? Number(known.postpay_visits) : 0;
            $visits.find('input')
                .val(String(knownVisits))
                .attr('title', postpayVisitsTooltipTitle());
            applyKidsHintAttrs($visits.find('input'), postpayVisitsTooltipTitle());
            const amount = Math.round(knownVisits * Number(pkg.price) * 100) / 100;
            $priceInput.val(formatPriceValue(amount));
            $priceInput.prop('disabled', true).prop('readonly', true);
            $priceInput.addClass('is-postpay-calc');
            applyKidsHintAttrs($priceInput, POSTPAY_PRICE_TOOLTIP);
        } else {
            $visits.remove();
            if (pkg) {
                $priceInput.val(formatPriceValue(pkg.price));
            }
            $priceInput.prop('readonly', false);
            $priceInput.removeClass('is-postpay-calc');
            $priceInput.removeAttr('data-kids-tooltip-hint');
            $priceInput.removeAttr('data-bs-toggle');
            $priceInput.removeAttr('data-bs-placement');
            $priceInput.removeAttr('data-bs-custom-class');
            $priceInput.removeAttr('title');
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
                usersPrice[idx].is_postpay = isPostpay;
            }
        }

        // Вне режима редактирования сумму блокируем только если абон уже был сохранён.
        // При первичной установке оставляем поле открытым для правки перед «Применить».
        const inEditMode = uid && editingMonthlyUserId !== null && String(editingMonthlyUserId) === String(uid);
        const abonEstablished = $card.attr('data-abon-established') === '1';
        if (isPostpay) {
            $priceInput.prop('disabled', true).prop('readonly', true);
        } else if (inEditMode) {
            $priceInput.prop('disabled', false);
        } else if (lastCanManageManualPaid && abonEstablished) {
            $priceInput.prop('disabled', true);
        } else {
            $priceInput.prop('disabled', false);
        }

        const rightBarEl = document.getElementById('right_bar');
        if (rightBarEl && window.KidsCrmTooltip) {
            window.KidsCrmTooltip.dispose(rightBarEl, { scopes: ['hint'] });
            window.KidsCrmTooltip.init(rightBarEl, { scopes: ['hint'] });
        }
    });

    $(document).on('input change', '#right_bar .wrap-users .setting-prices-monthly-price-input', function () {
        const $input = $(this);
        const $card = $input.closest('.setting-prices-user-card');
        if ($card.attr('data-is-former-member') === '1') {
            return;
        }
        const uid = $card.attr('data-user-id');
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
        const $btn = $(this);
        if ($btn.closest('.setting-prices-user-card').attr('data-is-former-member') === '1') {
            return;
        }
        const uid = $btn.attr('data-user-id');
        if (!uid) {
            return;
        }

        syncUsersPriceFromDom();

        if (String(editingMonthlyUserId) === String(uid)) {
            editingMonthlyUserId = null;
            editingMonthlySnapshot = null;
            renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
            return;
        }

        const idx = usersPrice.findIndex(function (u) {
            return String(u.user_id) === String(uid);
        });
        editingMonthlySnapshot = idx >= 0
            ? {
                userId: String(uid),
                price: usersPrice[idx].price,
                lesson_package_id: usersPrice[idx].lesson_package_id,
                is_postpay: usersPrice[idx].is_postpay,
            }
            : null;

        editingMonthlyUserId = uid;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
    });

    function restoreEditingMonthlySnapshot() {
        if (!editingMonthlySnapshot) {
            return;
        }
        const idx = usersPrice.findIndex(function (u) {
            return String(u.user_id) === String(editingMonthlySnapshot.userId);
        });
        if (idx >= 0) {
            usersPrice[idx].price = editingMonthlySnapshot.price;
            usersPrice[idx].lesson_package_id = editingMonthlySnapshot.lesson_package_id;
            usersPrice[idx].is_postpay = editingMonthlySnapshot.is_postpay;
        }
        editingMonthlySnapshot = null;
    }

    $(document).on('click', '#right_bar .wrap-users .user-price-edit-cancel', function (e) {
        e.preventDefault();
        syncUsersPriceFromDom();
        restoreEditingMonthlySnapshot();
        editingMonthlyUserId = null;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
    });

    $(document).on('click', '#right_bar .wrap-users .user-price-edit-accept', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const $card = $btn.closest('.setting-prices-user-card');
        if ($card.attr('data-is-former-member') === '1') {
            return;
        }
        const userId = $card.attr('data-user-id');
        if (!userId || !lastTeamId) {
            return;
        }

        const priceInput = $card.find('.setting-prices-monthly-price-input');
        const packageSelect = $card.find('.setting-prices-monthly-package-select');
        const price = priceInput.length ? priceInput.val() : null;
        const pkgVal = packageSelect.length ? packageSelect.val() : '';

        const idx = usersPrice.findIndex(function (u) {
            return String(u.user_id) === String(userId);
        });
        if (idx < 0) {
            return;
        }

        const row = usersPrice[idx];
        const eff = effectivePaidFromUserPrice(row);

        // Оплаченный месяц: сумма не сохраняется — только выход из режима.
        if (eff) {
            editingMonthlyUserId = null;
            editingMonthlySnapshot = null;
            renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
            return;
        }

        const payloadRow = {
            user_id: parseInt(userId, 10),
            price: price !== null && price !== '' ? Number(price) : Number(row.price) || 0,
            lesson_package_id: pkgVal !== '' ? parseInt(pkgVal, 10) : null,
            user: row.user || { name: '' },
        };

        $btn.prop('disabled', true);
        const selectedDate = getSelectedMonthLabel();
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
                usersPrice: [payloadRow],
            }),
            success: function (response) {
                const responsePrices = Array.isArray(response.usersPrice) ? response.usersPrice : [];
                if (responsePrices.length) {
                    const updated = responsePrices.find(function (u) {
                        return String(u.user_id) === String(userId);
                    });
                    if (updated) {
                        usersPrice[idx] = Object.assign({}, usersPrice[idx], updated);
                    } else {
                        usersPrice[idx].price = payloadRow.price;
                        usersPrice[idx].lesson_package_id = payloadRow.lesson_package_id;
                    }
                } else {
                    usersPrice[idx].price = payloadRow.price;
                    usersPrice[idx].lesson_package_id = payloadRow.lesson_package_id;
                }
                if (Array.isArray(response.lessonPackages)) {
                    lastLessonPackages = response.lessonPackages;
                }
                editingMonthlyUserId = null;
                editingMonthlySnapshot = null;
                renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal('Установка цен', 'Изменения сохранены.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                let msg = 'Не удалось сохранить цену.';
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
                        if (isFormerMemberFlag(usersPriceLocal[i].is_former_member)) {
                            continue;
                        }
                        for (let j = 0; j < userRows.length; j++) {
                            if (userRows[j].getAttribute('data-is-former-member') === '1') {
                                continue;
                            }
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

                // В payload только текущие участники; бывшие остаются в локальном usersPrice
                // и после success снова вливаются в список для UI.
                const applyPayload = usersPrice.filter(function (row) {
                    return !isFormerMemberFlag(row.is_former_member);
                });
                const formerSnapshot = usersPrice.filter(function (row) {
                    return isFormerMemberFlag(row.is_former_member);
                });

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
                        usersPrice: applyPayload,
                    }),
                    success: function (response) {
                        const responsePrices = Array.isArray(response.usersPrice) ? response.usersPrice : [];
                        // Ответ сервера без бывших — восстанавливаем их из снимка до apply.
                        usersPrice = responsePrices.concat(formerSnapshot);
                        if (Array.isArray(response.lessonPackages)) {
                            lastLessonPackages = response.lessonPackages;
                        }

                        document.querySelector('#set-price-all-users').removeAttribute('disabled');

                        showSuccessModal("Установка цен в одной группе", "Цены ученикам в выбранной группе успешно обновлены.");

                        editingMonthlyUserId = null;
                        editingMonthlySnapshot = null;
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
