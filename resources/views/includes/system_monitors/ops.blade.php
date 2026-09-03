{{-- Пульт: восемь строк счётчиков. Ховер — KidsCrmTooltip scope hint (data-kids-tooltip-hint). --}}
<div id="js-ops-monitors"
    class="ops-monitors system-monitor"
    data-url="{{ route('cabinet.system-monitors.ops') }}"
    aria-live="polite">
    <div class="ops-monitors__title">Пульт</div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Сегодня</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Оборотка T‑Bank за текущий календарный день (все школы, summ_cents > 0)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="day-turnover">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Комиссия платформы T‑Bank за текущий календарный день (все школы, как в отчёте «Платежи»: правила, не снимок выплаты)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="day-commission">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Число успешных платежей T‑Bank за текущий календарный день (все школы, summ_cents > 0)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="day-count">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Вчера</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Оборотка T‑Bank за вчерашний календарный день (все школы, summ_cents > 0)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="yesterday-turnover">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Комиссия платформы T‑Bank за вчерашний календарный день (все школы, как в отчёте «Платежи»: правила, не снимок выплаты)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="yesterday-commission">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Число успешных платежей T‑Bank за вчерашний календарный день (все школы, summ_cents > 0)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="yesterday-count">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Очередь</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Воркер очереди: жив / давно нет heartbeat / вероятно умер / нет данных',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="queue-worker">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Планировщик cron (schedule:run): работает / давно не тикал / вероятно не запускается / нет данных',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="queue-scheduler">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Сколько задач сейчас в таблице jobs (ещё не выполнены)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="queue-jobs">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Сколько задач в failed_jobs (упали и лежат в ошибках)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="queue-failed">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Просроченные отложенные выплаты T‑Bank: срок наступил, банковский PaymentId ещё нет (все школы)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="queue-overdue">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Касса</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Просроченные выплаты T‑Bank по всем школам (срок наступил, выплата не ушла в банк)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="till-overdue">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Неуспешные Init оплаты (payment_intents.status = failed) за последние 24 часа',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="till-intents">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Чеки CloudKassir со статусом error за последние 24 часа',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="till-fiscal">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">500</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Сколько reportable-исключений (примерно HTTP 500) за 24 часа. Это не журнал my_logs',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="errors-count">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Класс последней 500. После опроса здесь будет текст ошибки (без email/телефона, до 80 символов)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="errors-last">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Самый частый класс 500 за 24 часа',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="errors-top">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Шлюзы</span>
        <span class="ops-monitors__vals">
            <span class="ops-monitors__gw">Т</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'T‑Bank: сколько назад был последний успешный HTTP',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-tinkoff-ok">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'T‑Bank: сколько назад был последний таймаут или HTTP-ошибка',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-tinkoff-fail">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            <span class="ops-monitors__gw">С</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'sms.ru: сколько назад была последняя успешная отправка',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-smsru-ok">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'sms.ru: сколько назад был последний отказ шлюза или таймаут',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-smsru-fail">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            <span class="ops-monitors__gw">К</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'CloudKassir: сколько назад был последний успешный HTTP',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-cloudkassir-ok">…</span>',
            ])
            <span class="ops-monitors__slash">/</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'CloudKassir: сколько назад был последний таймаут или HTTP-ошибка',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="gw-cloudkassir-fail">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Вход</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Неверный пароль или неизвестный email за 72 часа. Ховер: введённые email/пароль, IP, время. Пароль только здесь, не в my_logs',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="auth-logins">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Неверный код 2FA за 72 часа. Ховер: email, введённый код, IP, время',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="auth-2fa">…</span>',
            ])
        </span>
    </div>
    <div class="ops-monitors__row">
        <span class="ops-monitors__label">Welcome</span>
        <span class="ops-monitors__vals">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Лид → клиент за 24 часа без успешно отправленного welcome-письма (ClientWelcomeCredentialsMail)',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="welcome-count">…</span>',
            ])
            <span class="ops-monitors__sep">·</span>
            @include('partials.ui.tooltip-hint', [
                'title' => 'Последний ученик без welcome: только #id, не email',
                'placement' => 'left',
                'wrapperClass' => '',
                'innerHtml' => '<span data-role="welcome-user">…</span>',
            ])
        </span>
    </div>
</div>
<style>
    .ops-monitors {
        min-width: 280px;
        max-width: 360px;
        padding: 10px 12px;
        border-radius: 8px;
        background: rgba(17, 24, 39, 0.94);
        color: #f9fafb;
        font: 12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
        pointer-events: auto;
    }
    .ops-monitors__title {
        font-weight: 700;
        letter-spacing: .02em;
        margin-bottom: 6px;
    }
    .ops-monitors__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        padding: 1px 0;
    }
    .ops-monitors__label {
        color: #9ca3af;
        flex: 0 0 auto;
    }
    .ops-monitors__vals {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: flex-end;
        gap: 0 2px;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .ops-monitors__sep,
    .ops-monitors__slash {
        color: #6b7280;
        padding: 0 1px;
    }
    .ops-monitors__gw {
        color: #9ca3af;
        margin-right: 2px;
    }
    .ops-monitors .kids-tooltip-hint {
        color: #f9fafb;
        cursor: help;
    }
    .ops-monitors [data-role].is-ok { color: #34d399; }
    .ops-monitors [data-role].is-warn { color: #fbbf24; }
    .ops-monitors [data-role].is-bad { color: #f87171; }
    .ops-monitors [data-role].is-muted { color: #9ca3af; }
</style>
<script>
    (function () {
        var root = document.getElementById('js-ops-monitors');
        if (!root) {
            return;
        }
        var statusUrl = root.getAttribute('data-url');
        var pollTimer = null;

        function monitorsOn() {
            return document.body.classList.contains('system-monitors-on');
        }

        function el(role) {
            return root.querySelector('[data-role="' + role + '"]');
        }

        function setText(role, value, tone) {
            var node = el(role);
            if (!node) {
                return;
            }
            node.textContent = value;
            node.classList.remove('is-ok', 'is-warn', 'is-bad', 'is-muted');
            if (tone) {
                node.classList.add(tone);
            }
        }

        function setHint(role, extra) {
            var node = el(role);
            if (!node || typeof node.closest !== 'function') {
                return;
            }
            var wrap = node.closest('[data-kids-tooltip-hint]');
            if (!wrap) {
                return;
            }
            if (!wrap.getAttribute('data-ops-hint-default')) {
                var current = wrap.getAttribute('data-bs-original-title') || wrap.getAttribute('title') || '';
                wrap.setAttribute('data-ops-hint-default', current);
            }
            var title = extra ? String(extra) : (wrap.getAttribute('data-ops-hint-default') || '');
            var tip = (typeof bootstrap !== 'undefined' && bootstrap.Tooltip)
                ? bootstrap.Tooltip.getInstance(wrap)
                : null;
            if (tip && typeof tip.dispose === 'function') {
                tip.dispose();
            }
            wrap.setAttribute('title', title);
            wrap.setAttribute('aria-label', title);
            wrap.removeAttribute('data-bs-original-title');
            var host = wrap.parentElement || wrap;
            if (typeof window !== 'undefined' && window.KidsCrmTooltip && typeof window.KidsCrmTooltip.dispose === 'function') {
                window.KidsCrmTooltip.dispose(host, { scopes: ['hint'] });
            }
            if (typeof window !== 'undefined' && window.KidsCrmTooltip && typeof window.KidsCrmTooltip.init === 'function') {
                window.KidsCrmTooltip.init(host, { scopes: ['hint'] });
            }
        }

        function formatAuthAt(ts) {
            if (!ts || typeof ts !== 'number') {
                return '—';
            }
            var d = new Date(ts * 1000);
            if (isNaN(d.getTime())) {
                return '—';
            }
            function pad(n) {
                return n < 10 ? '0' + n : String(n);
            }
            return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + ' '
                + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function formatAuthAttempts(rows, kind) {
            if (!rows || !rows.length) {
                return '';
            }
            return rows.map(function (row) {
                var when = formatAuthAt(row && row.at);
                var ident = (row && row.email) ? String(row.email) : '—';
                var secret = kind === 'login'
                    ? ((row && row.password) ? String(row.password) : '∅')
                    : ((row && row.code) ? String(row.code) : '∅');
                var ip = (row && row.ip) ? String(row.ip) : '—';
                var extra = (kind === 'login' && row && row.user_found === false) ? ' · нет email' : '';
                return when + '  ' + ident + '  ·  ' + secret + '  ·  ' + ip + extra;
            }).join('\n');
        }

        function formatAge(seconds) {
            if (seconds == null || typeof seconds !== 'number') {
                return '—';
            }
            if (seconds < 60) {
                return String(seconds) + 'с';
            }
            if (seconds < 3600) {
                return String(Math.floor(seconds / 60)) + 'м';
            }
            if (seconds < 86400) {
                return String(Math.floor(seconds / 3600)) + 'ч';
            }
            return String(Math.floor(seconds / 86400)) + 'д';
        }

        function workerLabel(code) {
            if (code === 'alive') return { text: 'жив', tone: 'is-ok' };
            if (code === 'stale') return { text: 'давно', tone: 'is-warn' };
            if (code === 'dead') return { text: 'нет', tone: 'is-bad' };
            return { text: '—', tone: 'is-muted' };
        }

        function schedulerLabel(code) {
            if (code === 'alive') return { text: 'cron', tone: 'is-ok' };
            if (code === 'stale') return { text: 'cron?', tone: 'is-warn' };
            if (code === 'dead') return { text: 'cron!', tone: 'is-bad' };
            return { text: '—', tone: 'is-muted' };
        }

        function countTone(n) {
            if (typeof n !== 'number') return 'is-muted';
            if (n > 0) return 'is-bad';
            return 'is-ok';
        }

        function shortClass(name) {
            if (!name) return '—';
            var text = String(name);
            if (text.length > 18) {
                return text.slice(0, 17) + '…';
            }
            return text;
        }

        function clear() {
            setText('day-turnover', '—', 'is-muted');
            setText('day-commission', '—', 'is-muted');
            setText('day-count', '—', 'is-muted');
            setText('yesterday-turnover', '—', 'is-muted');
            setText('yesterday-commission', '—', 'is-muted');
            setText('yesterday-count', '—', 'is-muted');
            setText('queue-worker', '—', 'is-muted');
            setText('queue-scheduler', '—', 'is-muted');
            setText('queue-jobs', '—', 'is-muted');
            setText('queue-failed', '—', 'is-muted');
            setText('queue-overdue', '—', 'is-muted');
            setText('till-overdue', '—', 'is-muted');
            setText('till-intents', '—', 'is-muted');
            setText('till-fiscal', '—', 'is-muted');
            setText('errors-count', '—', 'is-muted');
            setText('errors-last', '—', 'is-muted');
            setHint('errors-last', '');
            setText('errors-top', '—', 'is-muted');
            setText('gw-tinkoff-ok', '—', 'is-muted');
            setText('gw-tinkoff-fail', '—', 'is-muted');
            setText('gw-smsru-ok', '—', 'is-muted');
            setText('gw-smsru-fail', '—', 'is-muted');
            setText('gw-cloudkassir-ok', '—', 'is-muted');
            setText('gw-cloudkassir-fail', '—', 'is-muted');
            setText('auth-logins', '—', 'is-muted');
            setHint('auth-logins', '');
            setText('auth-2fa', '—', 'is-muted');
            setHint('auth-2fa', '');
            setText('welcome-count', '—', 'is-muted');
            setText('welcome-user', '—', 'is-muted');
        }

        function render(data) {
            if (!data || typeof data !== 'object' || !data.ok) {
                clear();
                return;
            }
            var day = data.day || {};
            setText('day-turnover', String(day.turnover == null ? '—' : day.turnover), day.turnover == null ? 'is-muted' : '');
            setText('day-commission', String(day.commission == null ? '—' : day.commission), day.commission == null ? 'is-muted' : '');
            setText('day-count', String(day.payments_count == null ? '—' : day.payments_count), day.payments_count == null ? 'is-muted' : '');

            var yesterday = data.yesterday || {};
            setText('yesterday-turnover', String(yesterday.turnover == null ? '—' : yesterday.turnover), yesterday.turnover == null ? 'is-muted' : '');
            setText('yesterday-commission', String(yesterday.commission == null ? '—' : yesterday.commission), yesterday.commission == null ? 'is-muted' : '');
            setText('yesterday-count', String(yesterday.payments_count == null ? '—' : yesterday.payments_count), yesterday.payments_count == null ? 'is-muted' : '');

            var queue = data.queue || {};
            var worker = workerLabel(queue.worker && queue.worker.code);
            var scheduler = schedulerLabel(queue.scheduler && queue.scheduler.code);
            setText('queue-worker', worker.text, worker.tone);
            setText('queue-scheduler', scheduler.text, scheduler.tone);
            setText('queue-jobs', String(queue.jobs == null ? '—' : queue.jobs), typeof queue.jobs === 'number' && queue.jobs > 20 ? 'is-warn' : '');
            setText('queue-failed', String(queue.failed_jobs == null ? '—' : queue.failed_jobs), countTone(queue.failed_jobs));
            setText('queue-overdue', String(queue.overdue_payouts == null ? '—' : queue.overdue_payouts), countTone(queue.overdue_payouts));

            var till = data.till || {};
            setText('till-overdue', String(till.overdue_payouts == null ? '—' : till.overdue_payouts), countTone(till.overdue_payouts));
            setText('till-intents', String(till.failed_intents == null ? '—' : till.failed_intents), countTone(till.failed_intents));
            setText('till-fiscal', String(till.fiscal_errors == null ? '—' : till.fiscal_errors), countTone(till.fiscal_errors));

            var errors = data.errors || {};
            setText('errors-count', String(errors.count == null ? '—' : errors.count), countTone(errors.count));
            setText('errors-last', shortClass(errors.last_class), errors.last_class ? 'is-bad' : 'is-muted');
            setHint('errors-last', errors.last_message || '');
            setText('errors-top', shortClass(errors.top_class), errors.top_class ? 'is-warn' : 'is-muted');

            var gateways = data.gateways || {};
            ['tinkoff', 'smsru', 'cloudkassir'].forEach(function (name) {
                var gw = gateways[name] || {};
                setText('gw-' + name + '-ok', formatAge(gw.last_ok_age_seconds), gw.last_ok_age_seconds == null ? 'is-muted' : 'is-ok');
                setText('gw-' + name + '-fail', formatAge(gw.last_fail_age_seconds), gw.last_fail_age_seconds == null ? 'is-muted' : 'is-bad');
            });

            var auth = data.auth || {};
            setText('auth-logins', String(auth.failed_logins == null ? '—' : auth.failed_logins), countTone(auth.failed_logins));
            setHint('auth-logins', formatAuthAttempts(auth.recent_logins, 'login'));
            setText('auth-2fa', String(auth.failed_2fa == null ? '—' : auth.failed_2fa), countTone(auth.failed_2fa));
            setHint('auth-2fa', formatAuthAttempts(auth.recent_2fa, '2fa'));

            var welcome = data.welcome || {};
            setText('welcome-count', String(welcome.missing_count == null ? '—' : welcome.missing_count), countTone(welcome.missing_count));
            setText('welcome-user', welcome.last_user_id ? ('#' + welcome.last_user_id) : '—', welcome.last_user_id ? 'is-warn' : 'is-muted');
        }

        function refresh() {
            if (!monitorsOn() || !statusUrl) {
                return;
            }
            fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            }).then(function (data) {
                if (data && data.ok) {
                    render(data);
                    return;
                }
                clear();
            }).catch(function () {
                clear();
            });
        }

        function startWatching() {
            if (pollTimer) {
                return;
            }
            refresh();
            pollTimer = setInterval(refresh, 5000);
        }

        function stopWatching() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        if (monitorsOn()) {
            startWatching();
        }

        document.addEventListener('system-monitors:change', function (event) {
            if (event && event.detail && event.detail.on) {
                startWatching();
                return;
            }
            stopWatching();
        });
    })();
</script>
