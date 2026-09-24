{{--
    Карточка ученика: платежи и журнал.
    @include('partials.ui.user-card-modal', ['userCardUrl' => url('/schedule/users')])
    Клик: .js-user-card или .js-payment-user-card с data-user-id.
--}}
@once
<div class="modal fade" id="paymentUserCardModal" tabindex="-1" aria-labelledby="paymentUserCardModalLabel" aria-hidden="true" data-user-card-url="{{ $userCardUrl }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentUserCardModalLabel">Ученик</h5>
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
    <style>
        #paymentUserCardModal .peer-card { text-align: left; }
        #paymentUserCardModal .peer-card-avatar {
            display: block; width: 96px; height: 96px; border-radius: 50%;
            object-fit: cover; margin: 0 auto 1rem;
        }
        #paymentUserCardModal .peer-card-name { text-align: center; font-weight: 600; font-size: 1.1rem; margin-bottom: 1rem; }
        #paymentUserCardModal .peer-card-name:has(+ .peer-card-partner),
        #paymentUserCardModal .peer-card-name:has(+ .peer-card-icons) { margin-bottom: 0; }
        #paymentUserCardModal .peer-card-icons {
            display: flex; justify-content: center; gap: .85rem;
            margin: .35rem 0 .85rem; color: #6c757d; font-size: 1.05rem;
        }
        #paymentUserCardModal .peer-card-icons:has(+ .peer-card-partner) { margin-bottom: .15rem; }
        #paymentUserCardModal .peer-card-partner { text-align: center; color: #6c757d; font-size: .9rem; margin: .2rem 0 1rem; }
        #paymentUserCardModal .peer-card-row { display: flex; gap: .75rem; padding: .35rem 0; border-top: 1px solid #f0f2f4; }
        #paymentUserCardModal .peer-card-label { flex: 0 0 42%; color: #6c757d; font-size: .85rem; }
        #paymentUserCardModal .peer-card-row > div:last-child { min-width: 0; word-break: break-word; }
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

            function paymentUserCardRow(label, valueHtml) {
                return '<div class="peer-card-row"><div class="peer-card-label">' + paymentUserCardEscape(label) + '</div><div>' + valueHtml + '</div></div>';
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
                    : '<div class="peer-card-partner">' + paymentUserCardEscape(partnerName) + '</div>';
                var icons = [];
                if (u.has_family_account) {
                    icons.push(paymentUserCardIconHtml('paymentUserCardFamilyIconTpl'));
                }
                if (u.has_multiple_teams) {
                    icons.push(paymentUserCardIconHtml('paymentUserCardTeamsIconTpl'));
                }
                var iconsHtml = icons.length ? '<div class="peer-card-icons">' + icons.join('') + '</div>' : '';
                var familyHtml = paymentUserCardEscape('-');
                if (Array.isArray(u.family_siblings)) {
                    var siblingNames = u.family_siblings.map(function (name) {
                        return String(name == null ? '' : name).trim();
                    }).filter(function (name) {
                        return name !== '';
                    });
                    familyHtml = paymentUserCardEscape(siblingNames.length ? siblingNames.join(', ') : 'Нет');
                }
                var percent = Number(u.discount_percent || 0);
                var discountHtml = '';
                if (percent >= 1) {
                    var discountText = String(percent) + '%';
                    var comment = String(u.discount_comment == null ? '' : u.discount_comment).trim();
                    if (comment !== '') {
                        discountText += '. ' + comment;
                    }
                    discountHtml = paymentUserCardRow('Скидка', paymentUserCardEscape(discountText));
                }

                if (window.KidsCrmTooltip) {
                    window.KidsCrmTooltip.dispose(body, { scopes: ['hint'] });
                }
                body.innerHTML =
                    '<div class="peer-card">' +
                    '<img class="peer-card-avatar" src="' + paymentUserCardEscape(u.avatar || '/img/default-avatar.png') + '" alt="">' +
                    '<div class="peer-card-name">' + paymentUserCardEscape(paymentUserCardDash(u.full_name)) + '</div>' +
                    iconsHtml +
                    partnerHtml +
                    paymentUserCardRow('Телефон', paymentUserCardPhone(u.phone)) +
                    paymentUserCardRow('Email', paymentUserCardMail(u.email)) +
                    paymentUserCardRow('Дата рождения', paymentUserCardEscape(paymentUserCardDash(u.birthday))) +
                    paymentUserCardRow('Родитель', paymentUserCardEscape(paymentUserCardDash(u.parent_full_name))) +
                    paymentUserCardRow('Телефон родителя', paymentUserCardPhone(u.parent_phone)) +
                    paymentUserCardRow('Email родителя', paymentUserCardMail(u.parent_email)) +
                    paymentUserCardRow('Семейный аккаунт', familyHtml) +
                    discountHtml +
                    paymentUserCardRow('Последний онлайн', paymentUserCardEscape(paymentUserCardDash(u.last_seen_label))) +
                    paymentUserCardRow('Группы', paymentUserCardEscape(paymentUserCardDash(u.team_title))) +
                    '</div>';
                paymentUserCardBindHints(body);
            }

            function openPaymentUserCard(userId) {
                var id = String(userId || '').replace(/[^0-9]/g, '');
                if (!id || paymentUserCardUrlBase === '') {
                    return;
                }
                paymentUserCardError('');
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
        });
    </script>
@endpush
@endonce
