import './setting-prices-manual-paid-modal.js';

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let usersPrice = [];
    let lastCanManageManualPaid = false;
    let lastUsersTeam = [];
    /** @type {string|null} */
    let editingMonthlyUserId = null;

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

    function effectivePaidFromUserPrice(row) {
        if (typeof row.effective_is_paid !== 'undefined') {
            return !!row.effective_is_paid;
        }
        return !!row.is_paid;
    }

    function postManualPaid(userId, selectedDate, mode, comment, errorEl) {
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
                selectedDate: selectedDate,
                mode: mode,
                comment: comment
            })
        }).done(function (res) {
            if (res && res.success && res.user_price) {
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
            const checkClass = eff ? '' : 'display-none';
            const inputDisabled = eff ? 'disabled' : '';

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
                infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" title="' + escapeAttr(noteForTitle) + '" aria-label="Комментарий к ручной отметке оплаты"></i>';
            }

            const namePlain = (i + 1) + '. ' + ((last || first) ? `${last} ${first}`.trim() : 'Имя не найдено');

            let pencilHtml = '';
            if (canManage && uid) {
                pencilHtml = '<button type="button" class="btn btn-link btn-sm p-0 user-price-manual-edit setting-prices-monthly-edit-btn" data-user-id="' + uid + '" title="Изменить статус оплаты">' +
                    '<i class="fa fa-edit" aria-hidden="true"></i></button>';
            }

            const statusBadgeClass = eff ? 'bg-success' : 'bg-secondary';
            const statusBadgeText = eff ? 'Оплачено' : 'Не оплачено';

            /** При праве на ручную отметку достаточно шильдиков — дублирующую галочку не показываем */
            const showPaidCheckmark = !canManage;

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
                    '<i class="fa fa-times" aria-hidden="true"></i>' +
                    '</button>' +
                    '</div>' +
                    '<div class="manual-paid-error small text-danger mt-1" style="display:none"></div>' +
                    '</div>';
            } else {
                statusCellHtml =
                    '<div class="user-price-status-view setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1">' +
                    '<div class="user-price-badge-wrap position-relative setting-prices-monthly-badge-wrap">' +
                    '<span class="badge ' + statusBadgeClass + '">' + statusBadgeText + '</span>' +
                    infoIcon +
                    '</div>' +
                    '<div class="setting-prices-monthly-edit-wrap">' + pencilHtml + '</div>' +
                    '</div>';
            }

            const checkColHtml = showPaidCheckmark
                ? '<div class="setting-prices-monthly-check flex-shrink-0 d-flex align-items-center justify-content-center">' +
                    '<span class="fa fa-check ' + checkClass + ' green-check" aria-hidden="true" title="' + (eff ? 'Месяц считается оплаченным' : 'Не оплачено') + '"></span>' +
                    '</div>'
                : '';

            const userBlock = `
                        <div class="setting-prices-user-card mb-2 pb-2 border-bottom" data-user-id="${uid}">
                            <div class="setting-prices-monthly-row d-flex align-items-center gap-1 gap-md-2 flex-nowrap w-100 min-w-0">
                                <div class="setting-prices-monthly-name-col d-flex align-items-center min-w-0 flex-grow-1 gap-1">
                                    <span id="${uid}" class="user-name setting-prices-monthly-name-text text-truncate" title="${escapeAttr(namePlain)}">${userNameFormatted}</span>
                                </div>
                                ${checkColHtml}
                                <div class="setting-prices-monthly-price flex-shrink-0">
                                    <input type="number" class="form-control form-control-sm setting-prices-monthly-price-input" value="${up.price}" ${inputDisabled} aria-label="Цена">
                                </div>
                                <div class="setting-prices-monthly-status flex-shrink-0 min-w-0">
                                    ${statusCellHtml}
                                </div>
                            </div>
                        </div>`;

            rightBar.append(userBlock);
        }

        document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');
    }

    $(document).on('click', '#right_bar .wrap-users .user-price-manual-edit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const uid = $(this).attr('data-user-id');
        if (!uid) {
            return;
        }

        if (String(editingMonthlyUserId) === String(uid)) {
            editingMonthlyUserId = null;
            renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
            return;
        }

        if (editingMonthlyUserId) {
            editingMonthlyUserId = uid;
            renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
            return;
        }

        editingMonthlyUserId = uid;
        renderUsersRightColumn(lastUsersTeam, usersPrice, lastCanManageManualPaid);
    });

    $(document).on('click', '#right_bar .wrap-users .user-price-edit-cancel', function (e) {
        e.preventDefault();
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
                postManualPaid(userId, selectedDate, mode, comment, errBox);
            }
        );
    });

    const detailButtons = document.querySelectorAll('#left_bar .detail');
    for (let i = 0; i < detailButtons.length; i++) {
        let button = detailButtons[i];
        button.addEventListener('click', function () {

            const parentDiv = this.closest('.wrap-team');

            detailButtons.forEach(btn => btn.classList.remove('action-button'));
            clearTeamRowHighlight();

            button.classList.add('action-button');
            if (parentDiv) {
                parentDiv.classList.add('wrap-team--active');
            }

            const selectedDate = getSelectedMonthLabel();
            document.querySelector('#right_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
            editingMonthlyUserId = null;

            const csrf = $('meta[name="csrf-token"]').attr('content');
            if (parentDiv) {

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
                        teamId: parentDiv.id,
                        selectedDate: selectedDate
                    }),
                    success: function (response) {
                        if (response.success) {
                            usersPrice = response.usersPrice;
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
        });
    }

    const okButtons = document.querySelectorAll('#left_bar .ok');
    for (let i = 0; i < okButtons.length; i++) {
        let button = okButtons[i];
        button.addEventListener('click', function () {
            const parentDiv = this.closest('.wrap-team');
            const teamPriceInput = parentDiv.querySelector('.team-price input');
            const teamPrice = teamPriceInput.value;
            const selectedDate = getSelectedMonthLabel();

            teamPriceInput.classList.remove('animated-input');

            if (parentDiv) {
                showConfirmDeleteModal(
                    'Подтвердите действие',
                    'Вы действительно хотите установить цену для этой команды?',
                    function () {
                        const csrf = $('meta[name="csrf-token"]').attr('content');
                        $.ajax({
                            url: '/admin/setting-prices/set-team-price',
                            method: 'POST',
                            contentType: 'application/json',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            data: JSON.stringify({
                                teamId: parentDiv.id,
                                teamPrice: teamPrice,
                                selectedDate: selectedDate,
                            }),
                            success: function (response) {
                                if (response.success) {
                                    teamPriceInput.classList.add('animated-input');
                                }
                            }
                        });
                    }
                );
            }
        });
    }

    $('.set-price-all-teams').on('click', function () {
        showConfirmDeleteModal(
            "Установка цена всем группам",
            "Вы уверены, что хотите применить изменения?", function () {
                const selectedDate = getSelectedMonthLabel();

                document.querySelector('#set-price-all-teams').setAttribute('disabled', 'disabled');

                let teamsData = [];
                document.querySelectorAll('#left_bar .wrap-team').forEach(function (teamElement) {
                    let teamName = teamElement.querySelector('.team-name').textContent.trim();
                    let teamId = teamElement.id;
                    let teamPrice = teamElement.querySelector('.team-price input').value;
                    teamsData.push({
                        name: teamName,
                        price: parseFloat(teamPrice),
                        teamId: teamId
                    });
                });

                if (teamsData.length === 0) {
                    console.error('Teams data is empty');
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
                        showSuccessModal("Установка цен всем группам", "Цены  всем группам успешно обновлены.", 1);
                    },
                    error: function () {
                        $('#errorModal').modal('show');
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
                            let price = priceInput ? priceInput.value : null;
                            if (price !== null && String(usersPriceLocal[i].user_id) === String(userId)) {
                                usersPriceLocal[i].price = price;
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
                        usersPrice: usersPrice,
                    }),
                    success: function (response) {
                        usersPrice = response.usersPrice;

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
                    }
                });
            }
        );
    });
});
