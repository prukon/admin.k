{{--
    Карточка ученика: платежи и журнал.
    @include('partials.ui.user-card-modal', ['userCardUrl' => url('/schedule/users')])
    Клик: .js-user-card или .js-payment-user-card с data-user-id.
--}}
@once
<div class="modal fade" id="paymentUserCardModal" tabindex="-1" aria-labelledby="paymentUserCardModalLabel" aria-hidden="true" data-user-card-url="{{ $userCardUrl }}" data-user-comment-url="{{ url('/admin/user-cards') }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentUserCardModalLabel">Ученик</h5>
                <div class="payment-user-card-presence" id="paymentUserCardPresence" hidden></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="text-danger" id="paymentUserCardError"></div>
                <div id="paymentUserCardBody"></div>
            </div>
        </div>
    </div>
</div>
<template id="paymentUserCardFamilyIconTpl">
    @include('partials.ui.tooltip-hint', [
        'title' => 'Семейный аккаунт',
        'placement' => 'top',
        'iconClass' => 'fas fa-users',
        'wrapperClass' => 'payment-user-card-icon',
        'container' => 'body',
    ])
</template>
<template id="paymentUserCardTeamsIconTpl">
    @include('partials.ui.tooltip-hint', [
        'title' => 'Несколько групп',
        'placement' => 'top',
        'iconClass' => 'fas fa-layer-group',
        'wrapperClass' => 'payment-user-card-icon',
        'container' => 'body',
    ])
</template>

@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/flag-icon-css/css/flag-icon.css') }}">
    <style>
        #paymentUserCardModal .modal-header {
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.9rem;
            border-bottom-color: #eef1f4;
        }
        #paymentUserCardModal .modal-title {
            font-size: 0.95rem;
            font-weight: 600;
        }
        #paymentUserCardModal .modal-body {
            padding: 0.85rem 0.9rem 1rem;
        }
        #paymentUserCardModal .payment-user-card-presence {
            margin-left: auto;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            gap: 0.2rem;
            text-align: right;
            min-width: 0;
        }
        #paymentUserCardModal .payment-user-card-presence-seen {
            font-size: 0.75rem;
            line-height: 1.2;
            white-space: nowrap;
        }
        #paymentUserCardModal .payment-user-card-login-flag {
            width: 1.25rem;
            height: 0.95rem;
            border-radius: 2px;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
            background-size: cover;
        }
        #paymentUserCardModal .modal-header .btn-close {
            margin-top: 0;
        }
        #paymentUserCardModal .peer-card { text-align: left; }
        #paymentUserCardModal .peer-card-head {
            display: flex;
            align-items: center;
            gap: 1.35rem;
            margin-bottom: .65rem;
            padding: .7rem .8rem;
            background: #f8fafc;
            border: 1px solid #e8edf2;
            border-radius: .85rem;
        }
        #paymentUserCardModal .peer-card-head-aside {
            flex: 0 0 108px;
            width: 108px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #paymentUserCardModal .peer-card-avatar {
            display: block;
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0;
            background: #fff;
            box-shadow: 0 0 0 3px #fff, 0 0 0 4px #e2e8f0;
        }
        #paymentUserCardModal .peer-card-head-main { min-width: 0; flex: 1 1 auto; }
        #paymentUserCardModal .peer-card-name {
            text-align: left;
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.25;
            margin-bottom: .35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #paymentUserCardModal .peer-card-name:has(+ .peer-card-partner) { margin-bottom: 0; }
        #paymentUserCardModal .peer-card-icons {
            display: flex;
            justify-content: center;
            gap: .55rem;
            margin: .4rem 0 0;
            color: #64748b;
            font-size: .95rem;
        }
        /* .kids-tooltip-hint ставит cursor:help — это системный знак вопроса. Здесь остаётся тёмный tooltip. */
        #paymentUserCardModal .peer-card-icons .kids-tooltip-hint {
            cursor: default;
        }
        #paymentUserCardModal .peer-card-partner {
            text-align: left;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.2;
            margin: 0 0 .35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #paymentUserCardModal .peer-card-head-line,
        #paymentUserCardModal .peer-card-row {
            display: flex;
            align-items: center;
            gap: .6rem;
            min-width: 0;
            padding: .12rem 0;
            line-height: 1.3;
        }
        #paymentUserCardModal .peer-card-row {
            padding: .28rem 0;
            border-top: 1px solid #f1f5f9;
        }
        #paymentUserCardModal .peer-card-head-label,
        #paymentUserCardModal .peer-card-label {
            flex: 0 0 8.5rem;
            color: #94a3b8;
            font-size: .75rem;
            line-height: 1.2;
            white-space: nowrap;
        }
        #paymentUserCardModal .peer-card-value {
            min-width: 0;
            flex: 1 1 auto;
            font-size: .84rem;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #paymentUserCardModal .peer-card-value a {
            color: inherit;
            text-decoration: none;
        }
        #paymentUserCardModal .peer-card-value a:hover {
            text-decoration: underline;
        }
        #paymentUserCardModal .payment-user-card-comment {
            margin-top: .75rem;
            text-align: left;
        }
        #paymentUserCardModal .payment-user-card-comment .form-label {
            margin-bottom: .25rem;
            font-size: .75rem;
            color: #94a3b8;
        }
        #paymentUserCardModal .payment-user-card-comment textarea {
            min-height: 4.2rem;
            font-size: .84rem;
        }
        button.schedule-user-card-name {
            border: 0;
            background: transparent;
            padding: 0;
            color: var(--bs-link-color, #0d6efd);
            text-decoration: underline;
            font: inherit;
            text-align: left;
            white-space: normal;
        }
    </style>
@endpush

@push('scripts')
    <script>
        window.KidsCrmUserCard = window.KidsCrmUserCard || {};
        window.KidsCrmUserCard.renderName = function (name, userId) {
            var label = name ? String(name) : 'Без имени';
            var id = String(userId == null ? '' : userId).replace(/[^0-9]/g, '');
            if (!id || !window.KidsCrmTooltip) {
                return window.KidsCrmTooltip ? window.KidsCrmTooltip.renderText(label) : label;
            }
            return window.KidsCrmTooltip.renderLink(label, {
                linkClass: 'js-user-card js-payment-user-card',
                extraAttrs: 'data-user-id="' + id + '"',
                href: 'javascript:void(0);'
            });
        };
        window.KidsCrmUserCard.renderNameList = function (cards, fallbackText) {
            if (!Array.isArray(cards) || cards.length === 0) {
                return window.KidsCrmTooltip
                    ? window.KidsCrmTooltip.renderText(fallbackText || '')
                    : String(fallbackText || '');
            }
            return cards.map(function (card) {
                return window.KidsCrmUserCard.renderName(card && card.name, card && card.id);
            }).join(', ');
        };
        window.KidsCrmUserCard.renderLinkedList = function (cards, items, fallbackText, listOptions) {
            listOptions = listOptions || {};
            var names = Array.isArray(cards) ? cards : [];
            var plainItems = Array.isArray(items) ? items : [];
            if (!window.KidsCrmTooltip) {
                return window.KidsCrmUserCard.renderNameList(names, fallbackText);
            }
            if (names.length === 0) {
                return window.KidsCrmTooltip.renderList(fallbackText, plainItems, listOptions);
            }
            if (names.length < 2) {
                return window.KidsCrmUserCard.renderName(names[0].name, names[0].id);
            }
            var customClass = String(listOptions.customClass || '').trim();
            var tooltipClass = 'kids-hover-list-tooltip ulp-assignment-paid-tooltip'
                + (customClass ? ' ' + customClass : '');
            var label = fallbackText ? String(fallbackText) : plainItems.join(', ');
            return '<span class="js-kids-hover-list-dropdown kids-hover-list-dropdown__trigger" '
                + 'data-bs-toggle="tooltip" '
                + 'data-bs-html="true" '
                + 'data-bs-placement="top" '
                + 'data-bs-custom-class="' + window.KidsCrmTooltip.escapeHtml(tooltipClass) + '" '
                + 'data-kids-hover-list-items="' + window.KidsCrmTooltip.escapeHtml(JSON.stringify(plainItems)) + '" '
                + 'tabindex="0" '
                + 'aria-label="' + window.KidsCrmTooltip.escapeHtml(label) + '">'
                + window.KidsCrmUserCard.renderNameList(names, fallbackText)
                + '</span>';
        };

        $(function () {
            var paymentUserCardModalEl = document.getElementById('paymentUserCardModal');
            var paymentUserCardUrlBase = paymentUserCardModalEl
                ? String(paymentUserCardModalEl.getAttribute('data-user-card-url') || '')
                : '';
            var paymentUserCardModal = null;

            function paymentUserCardEscape(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function paymentUserCardDash(value) {
                var text = String(value == null ? '' : value).trim();
                return text === '' ? '-' : text;
            }

            function paymentUserCardTel(phone) {
                var raw = String(phone == null ? '' : phone).trim();
                if (!raw) {
                    return '';
                }
                var cleaned = raw.replace(/[^\d+]/g, '');
                if (!cleaned || cleaned === '+') {
                    return '';
                }
                return 'tel:' + cleaned;
            }

            function paymentUserCardPhone(phone) {
                var shown = paymentUserCardDash(phone);
                if (shown === '-') {
                    return paymentUserCardEscape('-');
                }
                var href = paymentUserCardTel(phone);
                if (!href) {
                    return paymentUserCardEscape(shown);
                }
                return '<a href="' + paymentUserCardEscape(href) + '">' + paymentUserCardEscape(shown) + '</a>';
            }

            function paymentUserCardMail(email) {
                var shown = paymentUserCardDash(email);
                if (shown === '-') {
                    return paymentUserCardEscape('-');
                }
                var raw = String(email == null ? '' : email).trim();
                if (!/^[^\s@]+@[^\s@]+$/.test(raw)) {
                    return paymentUserCardEscape(shown);
                }
                return '<a href="mailto:' + paymentUserCardEscape(raw) + '">' + paymentUserCardEscape(shown) + '</a>';
            }

            function paymentUserCardValue(valueHtml, plain) {
                var text = String(plain == null ? '' : plain).trim();
                var title = text !== '' && text !== '-'
                    ? ' title="' + paymentUserCardEscape(text) + '"'
                    : '';
                return '<div class="peer-card-value"' + title + '>' + valueHtml + '</div>';
            }

            function paymentUserCardRow(label, valueHtml, plain) {
                return '<div class="peer-card-row"><div class="peer-card-label">' + paymentUserCardEscape(label) + '</div>' + paymentUserCardValue(valueHtml, plain) + '</div>';
            }

            function paymentUserCardHeadLine(label, valueHtml, plain) {
                return '<div class="peer-card-head-line"><div class="peer-card-head-label">' + paymentUserCardEscape(label) + '</div>' + paymentUserCardValue(valueHtml, plain) + '</div>';
            }

            function paymentUserCardError(text) {
                var el = document.getElementById('paymentUserCardError');
                if (el) {
                    el.textContent = text || '';
                }
            }

            function paymentUserCardIconHtml(templateId) {
                var tpl = document.getElementById(templateId);
                if (!tpl || !tpl.content) {
                    return '';
                }
                var holder = document.createElement('div');
                holder.appendChild(tpl.content.cloneNode(true));
                return holder.innerHTML;
            }

            function paymentUserCardBindHints(root) {
                if (!root || !window.KidsCrmTooltip) {
                    return;
                }
                window.KidsCrmTooltip.init(root, { scopes: ['hint'] });
            }

            function renderPaymentUserCard(u) {
                var body = document.getElementById('paymentUserCardBody');
                if (!body) {
                    return;
                }
                u = u || {};
                var partnerName = String(u.partner_name == null ? '' : u.partner_name).trim();
                var partnerHtml = partnerName === ''
                    ? ''
                    : '<div class="peer-card-partner" title="' + paymentUserCardEscape(partnerName) + '">' + paymentUserCardEscape(partnerName) + '</div>';
                var studentName = paymentUserCardDash(u.full_name);
                var icons = [];
                if (u.has_family_account) {
                    icons.push(paymentUserCardIconHtml('paymentUserCardFamilyIconTpl'));
                }
                if (u.has_multiple_teams) {
                    icons.push(paymentUserCardIconHtml('paymentUserCardTeamsIconTpl'));
                }
                var iconsHtml = icons.length ? '<div class="peer-card-icons">' + icons.join('') + '</div>' : '';
                var familyPlain = '-';
                var familyHtml = paymentUserCardEscape('-');
                if (Array.isArray(u.family_siblings)) {
                    var siblingNames = u.family_siblings.map(function (name) {
                        return String(name == null ? '' : name).trim();
                    }).filter(function (name) {
                        return name !== '';
                    });
                    familyPlain = siblingNames.length ? siblingNames.join(', ') : 'Нет';
                    familyHtml = paymentUserCardEscape(familyPlain);
                }
                var percent = Number(u.discount_percent || 0);
                var discountHtml = '';
                if (percent >= 1) {
                    var discountText = String(percent) + '%';
                    var comment = String(u.discount_comment == null ? '' : u.discount_comment).trim();
                    if (comment !== '') {
                        discountText += '. ' + comment;
                    }
                    discountHtml = paymentUserCardRow('Скидка', paymentUserCardEscape(discountText), discountText);
                }

                if (window.KidsCrmTooltip) {
                    window.KidsCrmTooltip.dispose(body, { scopes: ['hint'] });
                }
                body.innerHTML =
                    '<div class="peer-card">' +
                    '<div class="peer-card-head">' +
                    '<div class="peer-card-head-aside">' +
                    '<img class="peer-card-avatar" src="' + paymentUserCardEscape(u.avatar || '/img/default-avatar.png') + '" alt="">' +
                    iconsHtml +
                    '</div>' +
                    '<div class="peer-card-head-main">' +
                    '<div class="peer-card-name" title="' + paymentUserCardEscape(studentName) + '">' + paymentUserCardEscape(studentName) + '</div>' +
                    partnerHtml +
                    paymentUserCardHeadLine('Группы', paymentUserCardEscape(paymentUserCardDash(u.team_title)), paymentUserCardDash(u.team_title)) +
                    paymentUserCardHeadLine('Телефон', paymentUserCardPhone(u.phone), paymentUserCardDash(u.phone)) +
                    paymentUserCardHeadLine('Email', paymentUserCardMail(u.email), paymentUserCardDash(u.email)) +
                    paymentUserCardHeadLine('Дата рождения', paymentUserCardEscape(paymentUserCardDash(u.birthday)), paymentUserCardDash(u.birthday)) +
                    '</div>' +
                    '</div>' +
                    paymentUserCardRow('Родитель', paymentUserCardEscape(paymentUserCardDash(u.parent_full_name)), paymentUserCardDash(u.parent_full_name)) +
                    paymentUserCardRow('Телефон родителя', paymentUserCardPhone(u.parent_phone), paymentUserCardDash(u.parent_phone)) +
                    paymentUserCardRow('Email родителя', paymentUserCardMail(u.parent_email), paymentUserCardDash(u.parent_email)) +
                    paymentUserCardRow('Семейный аккаунт', familyHtml, familyPlain) +
                    discountHtml +
                    (u.can_edit_comment && u.id
                        ? '<div class="payment-user-card-comment">' +
                            '<label class="form-label" for="paymentUserCardComment">Комментарий</label>' +
                            '<textarea class="form-control" id="paymentUserCardComment" maxlength="5000" rows="2"></textarea>' +
                            '<div class="small text-danger mt-1" id="paymentUserCardCommentError" style="display:none;"></div>' +
                            '<div class="small text-success mt-1" id="paymentUserCardCommentSaved" style="display:none;"></div>' +
                            '<button type="button" class="btn btn-primary btn-sm mt-2" id="paymentUserCardCommentSave">Сохранить</button>' +
                          '</div>'
                        : '') +
                    '</div>';
                var commentInput = document.getElementById('paymentUserCardComment');
                if (commentInput) {
                    commentInput.value = String(u.comment == null ? '' : u.comment);
                }
                paymentUserCardBindHints(body);
                renderPaymentUserCardPresence(u);
            }

            function renderPaymentUserCardPresence(u) {
                var el = document.getElementById('paymentUserCardPresence');
                if (!el) {
                    return;
                }
                u = u || {};
                if (!u.id && !u.presence_label) {
                    el.innerHTML = '';
                    el.hidden = true;
                    return;
                }
                var online = !!u.is_online;
                var label = String(u.presence_label || (online ? 'Онлайн' : 'Офлайн'));
                var seen = String(u.presence_seen || '').trim();
                var badge = '<span class="badge ' + (online ? 'bg-success' : 'bg-secondary') + '">'
                    + paymentUserCardEscape(label) + '</span>';
                var seenHtml = (!online && seen !== '')
                    ? '<span class="text-muted payment-user-card-presence-seen">' + paymentUserCardEscape(seen) + '</span>'
                    : '';
                var flags = Array.isArray(u.login_flags) ? u.login_flags : [];
                var flagHtml = flags.map(function (flag) {
                    var code = String(flag && flag.code || '').toLowerCase().replace(/[^a-z]/g, '');
                    if (!code) {
                        return '';
                    }
                    var title = String(flag && flag.label || '');
                    return '<span class="flag-icon flag-icon-' + code + ' payment-user-card-login-flag"'
                        + ' title="' + paymentUserCardEscape(title) + '"'
                        + ' aria-label="' + paymentUserCardEscape(title) + '"></span>';
                }).join('');
                var device = String(u.login_device || '');
                var deviceLabel = paymentUserCardEscape(String(u.login_device_label || ''));
                var deviceIcon = '';
                if (device === 'desktop') {
                    deviceIcon = '<i class="fa-solid fa-desktop text-muted" title="' + deviceLabel + '" aria-label="' + deviceLabel + '"></i>';
                } else if (device === 'mobile') {
                    deviceIcon = '<i class="fa-solid fa-mobile-screen text-muted" title="' + deviceLabel + '" aria-label="' + deviceLabel + '"></i>';
                } else if (device === 'tablet') {
                    deviceIcon = '<i class="fa-solid fa-tablet-screen-button text-muted" title="' + deviceLabel + '" aria-label="' + deviceLabel + '"></i>';
                }
                var hintsHtml = (flagHtml || deviceIcon)
                    ? '<div class="d-flex align-items-center justify-content-end gap-2">' + flagHtml + deviceIcon + '</div>'
                    : '';
                el.hidden = false;
                el.innerHTML =
                    '<div class="d-flex align-items-center justify-content-end gap-2">' + badge + seenHtml + '</div>' +
                    hintsHtml;
            }

            function openPaymentUserCard(userId) {
                var id = String(userId || '').replace(/[^0-9]/g, '');
                if (!id || paymentUserCardUrlBase === '') {
                    return;
                }
                paymentUserCardError('');
                if (paymentUserCardModalEl) {
                    paymentUserCardModalEl.setAttribute('data-open-user-id', id);
                }
                renderPaymentUserCard({});
                if (paymentUserCardModalEl && window.bootstrap) {
                    paymentUserCardModal = bootstrap.Modal.getOrCreateInstance(paymentUserCardModalEl);
                    paymentUserCardModal.show();
                }

                $.ajax({
                    url: paymentUserCardUrlBase + '/' + encodeURIComponent(id),
                    method: 'GET',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    success: function (resp) {
                        renderPaymentUserCard(resp || {});
                    },
                    error: function (xhr) {
                        var msg = 'Не удалось загрузить карточку.';
                        var errors = xhr && xhr.responseJSON && xhr.responseJSON.errors;
                        if (errors && errors.user && errors.user[0]) {
                            msg = errors.user[0];
                        } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        paymentUserCardError(msg);
                    }
                });
            }

            $(document).on('click', '.js-payment-user-card, .js-user-card', function (e) {
                e.preventDefault();
                openPaymentUserCard($(this).attr('data-user-id'));
            });

            $(document).on('input', '#paymentUserCardComment', function () {
                var errorEl = document.getElementById('paymentUserCardCommentError');
                var savedEl = document.getElementById('paymentUserCardCommentSaved');
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.style.display = 'none';
                }
                if (savedEl) {
                    savedEl.textContent = '';
                    savedEl.style.display = 'none';
                }
            });

            $(document).on('click', '#paymentUserCardCommentSave', function () {
                var btn = this;
                var textarea = document.getElementById('paymentUserCardComment');
                var errorEl = document.getElementById('paymentUserCardCommentError');
                var savedEl = document.getElementById('paymentUserCardCommentSaved');
                var id = paymentUserCardModalEl
                    ? String(paymentUserCardModalEl.getAttribute('data-open-user-id') || '').replace(/[^0-9]/g, '')
                    : '';
                var base = paymentUserCardModalEl
                    ? String(paymentUserCardModalEl.getAttribute('data-user-comment-url') || '').replace(/\/$/, '')
                    : '';
                if (!textarea || !id || base === '') {
                    return;
                }
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.style.display = 'none';
                }
                if (savedEl) {
                    savedEl.textContent = '';
                    savedEl.style.display = 'none';
                }
                btn.disabled = true;
                $.ajax({
                    url: base + '/' + encodeURIComponent(id) + '/comment',
                    method: 'PATCH',
                    data: {
                        comment: textarea.value,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    success: function (resp) {
                        if (resp && typeof resp.comment === 'string') {
                            textarea.value = resp.comment;
                        }
                        if (savedEl) {
                            savedEl.textContent = (resp && resp.message) ? resp.message : 'Комментарий сохранён.';
                            savedEl.style.display = 'block';
                        }
                    },
                    error: function (xhr) {
                        var msg = 'Не удалось сохранить комментарий.';
                        var errors = xhr && xhr.responseJSON && xhr.responseJSON.errors;
                        if (errors && errors.comment && errors.comment[0]) {
                            msg = errors.comment[0];
                        } else if (errors && errors.user && errors.user[0]) {
                            msg = errors.user[0];
                        } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        if (errorEl) {
                            errorEl.textContent = msg;
                            errorEl.style.display = 'block';
                        }
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>
@endpush
@endonce
