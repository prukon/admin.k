<div id="js-online-users"
    class="online-users system-monitor"
    data-url="{{ route('cabinet.system-monitors.online-users') }}"
    aria-live="polite">
    <div class="online-users__head">
        <div class="online-users__title">Онлайн (<span data-role="total">…</span>)</div>
        <button type="button" class="online-users__copy" data-role="copy" title="Копировать список" aria-label="Копировать список">
            <i class="fas fa-copy" aria-hidden="true"></i>
        </button>
    </div>
    <div class="online-users__list" data-role="list"></div>
</div>
<style>
    .online-users {
        min-width: 220px;
        max-width: 280px;
        max-height: min(52vh, calc(100vh - 140px));
        padding: 10px 12px;
        border-radius: 8px;
        background: rgba(17, 24, 39, 0.94);
        color: #f9fafb;
        font: 12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
        pointer-events: auto;
        overflow: auto;
    }
    .online-users__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 6px;
    }
    .online-users__title {
        font-weight: 700;
        letter-spacing: .02em;
    }
    .online-users__copy {
        pointer-events: auto;
        cursor: pointer;
        border: 0;
        background: transparent;
        color: #e5e7eb;
        padding: 2px 4px;
        line-height: 1;
        border-radius: 4px;
    }
    .online-users__copy:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }
    .online-users__copy.is-copied { color: #34d399; }
    .online-users__partner + .online-users__partner {
        margin-top: 8px;
    }
    .online-users__partner-title {
        font-weight: 700;
        color: #e5e7eb;
        margin-bottom: 2px;
        text-align: center;
    }
    .online-users__user {
        color: #f9fafb;
        padding-left: 0;
        text-align: left;
    }
    .online-users__empty {
        color: #9ca3af;
    }
</style>
<script>
    (function () {
        var root = document.getElementById('js-online-users');
        if (!root) {
            return;
        }
        var statusUrl = root.getAttribute('data-url');
        var totalEl = root.querySelector('[data-role="total"]');
        var listEl = root.querySelector('[data-role="list"]');
        var copyBtn = root.querySelector('[data-role="copy"]');
        var copyTimer = null;
        var pollTimer = null;
        var lastSnapshot = { total: 0, partners: [] };

        function monitorsOn() {
            return document.body.classList.contains('system-monitors-on');
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function render(data) {
            lastSnapshot = data && typeof data === 'object' ? data : { total: 0, partners: [] };
            var total = typeof lastSnapshot.total === 'number' ? lastSnapshot.total : 0;
            if (totalEl) {
                totalEl.textContent = String(total);
            }
            if (!listEl) {
                return;
            }
            var partners = Array.isArray(lastSnapshot.partners) ? lastSnapshot.partners : [];
            if (!partners.length) {
                listEl.innerHTML = '<div class="online-users__empty">Никого нет онлайн</div>';
                return;
            }
            var html = '';
            partners.forEach(function (partner) {
                var title = partner && partner.title ? partner.title : 'Без партнёра';
                var count = partner && typeof partner.count === 'number' ? partner.count : 0;
                html += '<div class="online-users__partner">';
                html += '<div class="online-users__partner-title">' + escapeHtml(title) + ' (' + count + ')</div>';
                var users = partner && Array.isArray(partner.users) ? partner.users : [];
                users.forEach(function (user) {
                    var name = user && user.name ? user.name : '';
                    html += '<div class="online-users__user">' + escapeHtml(name) + '</div>';
                });
                html += '</div>';
            });
            listEl.innerHTML = html;
        }

        function statusText() {
            var lines = ['Онлайн (' + (lastSnapshot.total || 0) + ')'];
            var partners = Array.isArray(lastSnapshot.partners) ? lastSnapshot.partners : [];
            partners.forEach(function (partner) {
                lines.push('');
                lines.push((partner.title || 'Без партнёра') + ' (' + (partner.count || 0) + ')');
                var users = Array.isArray(partner.users) ? partner.users : [];
                users.forEach(function (user) {
                    lines.push(user.name || '');
                });
            });
            return lines.join('\n');
        }

        function fallbackCopy(text) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }
            document.body.removeChild(area);
            return ok;
        }

        function markCopied() {
            if (!copyBtn) {
                return;
            }
            copyBtn.classList.add('is-copied');
            copyBtn.setAttribute('title', 'Скопировано');
            clearTimeout(copyTimer);
            copyTimer = setTimeout(function () {
                copyBtn.classList.remove('is-copied');
                copyBtn.setAttribute('title', 'Копировать список');
            }, 1500);
        }

        function copyStatus() {
            var text = statusText();
            var done = function () { markCopied(); };
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    if (fallbackCopy(text)) {
                        done();
                    }
                });
                return;
            }
            if (fallbackCopy(text)) {
                done();
            }
        }

        function refreshList() {
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
                render({ total: 0, partners: [] });
            }).catch(function () {
                render({ total: 0, partners: [] });
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                copyStatus();
            });
        }

        function startWatching() {
            if (pollTimer) {
                return;
            }
            refreshList();
            pollTimer = setInterval(refreshList, 3000);
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
