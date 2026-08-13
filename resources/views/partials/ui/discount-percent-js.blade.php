<script>
    (function (w) {
        if (w.KidsCrmUserDiscount) {
            return;
        }

        function escapeAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        w.KidsCrmUserDiscount = {
            percentOf: function (value) {
                const n = parseInt(value, 10);
                if (!Number.isFinite(n) || n < 1) {
                    return 0;
                }
                return n > 100 ? 100 : n;
            },
            payableAfterDiscountCents: function (priceCents, percent) {
                const cents = parseInt(priceCents, 10) || 0;
                const p = this.percentOf(percent);
                if (p < 1 || cents < 0) {
                    return cents;
                }
                const discount = Math.round(cents * p / 100);
                return cents - discount;
            },
            centsFromRub: function (rub) {
                const n = Number(rub);
                if (!Number.isFinite(n) || n < 0) {
                    return 0;
                }
                return Math.round(n * 100);
            },
            rubFromCents: function (cents) {
                const n = parseInt(cents, 10) || 0;
                return n / 100;
            },
            tooltip: function (percent, comment) {
                const p = this.percentOf(percent);
                if (p < 1) {
                    return '';
                }
                const reason = String(comment == null ? '' : comment).trim();
                return reason !== '' ? ('Скидка ' + p + '%. ' + reason) : ('Скидка ' + p + '%.');
            },
            badgeHtml: function (percent, comment) {
                const title = this.tooltip(percent, comment);
                if (!title) {
                    return '';
                }
                const t = escapeAttr(title);
                return '<span class="kids-user-discount-badge">'
                    + '<span class="kids-tooltip-hint d-inline-block" tabindex="0" data-kids-tooltip-hint'
                    + ' data-bs-toggle="tooltip" data-bs-placement="top"'
                    + ' data-bs-custom-class="ulp-assignment-paid-tooltip"'
                    + ' title="' + t + '" aria-label="' + t + '">'
                    + '<i class="fa fa-percent" aria-hidden="true"></i></span></span>';
            },
            wrapPriceHtml: function (inputHtml, percent, comment) {
                return '<div class="kids-user-discount-price-wrap">'
                    + inputHtml
                    + this.badgeHtml(percent, comment)
                    + '</div>';
            },
            showBadge: function (wrapEl, percent, comment) {
                if (!wrapEl) {
                    return;
                }
                const $wrap = window.jQuery ? window.jQuery(wrapEl) : null;
                const host = $wrap ? $wrap.get(0) : wrapEl;
                if (!host) {
                    return;
                }
                let badge = host.querySelector('.kids-user-discount-badge');
                const html = this.badgeHtml(percent, comment);
                if (!html) {
                    if (badge) {
                        badge.remove();
                    }
                    return;
                }
                if (badge) {
                    badge.outerHTML = html;
                } else {
                    host.insertAdjacentHTML('beforeend', html);
                }
            },
            hideBadge: function (wrapEl) {
                if (!wrapEl) {
                    return;
                }
                const badge = wrapEl.querySelector ? wrapEl.querySelector('.kids-user-discount-badge') : null;
                if (badge) {
                    badge.remove();
                }
            },
            initHint: function (el) {
                if (!el || !window.KidsCrmTooltip) {
                    return;
                }
                window.KidsCrmTooltip.dispose(el, { scopes: ['hint'] });
                window.KidsCrmTooltip.init(el, { scopes: ['hint'] });
            },
            matchesPayable: function (amountRub, catalogRub, percent) {
                const expected = this.rubFromCents(
                    this.payableAfterDiscountCents(this.centsFromRub(catalogRub), percent)
                );
                const current = Number(amountRub);

                return this.percentOf(percent) >= 1
                    && Number.isFinite(current)
                    && Math.abs(current - expected) < 0.001;
            }
        };
    })(window);
</script>
