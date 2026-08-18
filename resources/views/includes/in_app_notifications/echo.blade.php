@if(auth()->check())
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        (function () {
            var EchoLib = typeof Echo === 'function' ? Echo : window.Echo;
            if (window.Echo && typeof window.Echo.private === 'function') {
                return;
            }
            window.Pusher = window.Pusher || Pusher;
            var reverbKey = @json(config('broadcasting.connections.reverb.key') ?: env('REVERB_APP_KEY'));
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            window.Echo = new EchoLib({
                broadcaster: 'reverb',
                key: reverbKey,
                wsHost: window.location.hostname,
                wsPort: 443,
                wssPort: 443,
                forceTLS: true,
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
                    }
                }
            });
        })();
    </script>
    <script>
        (function () {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrf = csrfMeta ? csrfMeta.content : '';
            var presenceUrl = @json(route('presence.ping'));
            function ping() {
                if (!presenceUrl) {
                    return;
                }
                fetch(presenceUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    },
                    credentials: 'same-origin'
                }).catch(function () {});
            }
            ping();
            setInterval(ping, 60000);
        })();
    </script>
@endif

@if(!empty($inAppNotificationBell) && is_array($inAppNotificationBell) && auth()->check())
    <script>
        (function () {
            var root = document.getElementById('inAppNotificationBell');
            if (!root || !window.Echo) {
                return;
            }

            var me = {{ (int) auth()->id() }};
            var bellUrl = root.getAttribute('data-bell-url');
            var isSuperadmin = root.getAttribute('data-is-superadmin') === '1';
            var currentPartnerId = Number(root.getAttribute('data-current-partner-id') || 0);

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderItem(item) {
                var unreadClass = item.is_read ? '' : ' unread';
                var title = escapeHtml(item.title || '');
                var preview = escapeHtml(item.body_preview || '');
                var href = escapeHtml(item.page_url || '');
                var category = String(item.category || 'normal');
                var badge = '';
                if (category === 'update' || category === 'important') {
                    badge = '<span class="ian-badge ian-badge--' + escapeHtml(category) + '">'
                        + escapeHtml(item.category_label || '') + '</span>';
                }
                var inner = '<div class="bell-title">' + title + '</div>'
                    + '<div class="small text-muted bell-meta">' + badge
                    + '<time>' + escapeHtml(item.created_at_human || '') + '</time></div>'
                    + '<div class="small">' + preview + '</div>';
                return '<a class="dropdown-item in-app-bell-item' + unreadClass + '" href="' + href + '" data-notification-id="' + Number(item.id) + '">' + inner + '</a>';
            }

            function applyBellPayload(data) {
                var count = Number(data.unread_count || 0);
                var badge = root.querySelector('.js-in-app-bell-count');
                if (badge) {
                    badge.textContent = String(count);
                    badge.style.display = count > 0 ? '' : 'none';
                }
                var list = root.querySelector('.js-in-app-bell-list');
                if (!list) {
                    return;
                }
                var items = Array.isArray(data.items) ? data.items : [];
                if (items.length === 0) {
                    list.innerHTML = '<div class="px-3 py-3 text-muted small js-in-app-bell-empty">Нет уведомлений</div>';
                    return;
                }
                list.innerHTML = items.map(renderItem).join('');
            }

            function refreshBell() {
                fetch(bellUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).then(function (data) {
                    if (data) {
                        applyBellPayload(data);
                    }
                }).catch(function () {});
            }

            try {
                window.Echo.private('user.' + me).listen('.in-app-notification.bell', function (payload) {
                    if (isSuperadmin) {
                        var isGlobal = !!(payload && payload.is_global);
                        var partnerIds = (payload && Array.isArray(payload.partner_ids)) ? payload.partner_ids.map(Number) : [];
                        if (!isGlobal && partnerIds.indexOf(currentPartnerId) === -1) {
                            return;
                        }
                    }
                    refreshBell();
                });
            } catch (e) {}
        })();
    </script>
@endif
