@if(auth()->check() && auth()->user()->can('messages.view'))
    <script>
        (function () {
            function applyUnread(count) {
                var n = Number(count || 0);
                if (isNaN(n) || n < 0) {
                    n = 0;
                }
                document.querySelectorAll('.js-chat-unread-count').forEach(function (badge) {
                    badge.textContent = String(n);
                    badge.style.display = n > 0 ? '' : 'none';
                });
            }

            window.KidsCrmChatSetUnread = applyUnread;

            var me = {{ (int) auth()->id() }};
            var unreadUrl = @json(route('chat.api.unread'));
            var inboxBound = false;

            function refreshUnread() {
                if (!unreadUrl) {
                    return;
                }
                fetch(unreadUrl, {
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
                    if (data && typeof data.unread_total !== 'undefined') {
                        applyUnread(data.unread_total);
                    }
                }).catch(function () {});
            }

            function refreshInboxOrUnread() {
                if (typeof window.KidsCrmChatRefreshInbox === 'function') {
                    window.KidsCrmChatRefreshInbox();
                    return;
                }
                refreshUnread();
            }

            function socketState() {
                var pusher = window.Echo && window.Echo.connector && window.Echo.connector.pusher;
                return pusher && pusher.connection ? String(pusher.connection.state || '') : '';
            }

            function bindInboxSocket() {
                if (inboxBound || !window.Echo) {
                    return;
                }
                try {
                    var channel = window.Echo.private('inbox.' + me);
                    channel.listen('.inbox.bump', function (payload) {
                        if (typeof window.KidsCrmChatOnInboxBump === 'function') {
                            window.KidsCrmChatOnInboxBump(payload);
                            return;
                        }
                        if (payload && typeof payload.unread_total !== 'undefined') {
                            applyUnread(payload.unread_total);
                        }
                    });
                    channel.listen('.thread.read', function (payload) {
                        if (payload && Number(payload.user_id) === me && typeof payload.unread_total !== 'undefined') {
                            applyUnread(payload.unread_total);
                        }
                    });
                    inboxBound = true;
                    var pusher = window.Echo.connector && window.Echo.connector.pusher;
                    if (pusher && pusher.connection) {
                        pusher.connection.bind('connected', function () {
                            refreshInboxOrUnread();
                        });
                    }
                } catch (e) {}
            }

            bindInboxSocket();

            var lastFallbackPoll = 0;
            setInterval(function () {
                if (socketState() === 'connected') {
                    return;
                }
                var onChatPage = typeof window.KidsCrmChatRefreshInbox === 'function';
                var wait = onChatPage ? 1000 : 12000;
                var now = Date.now();
                if (now - lastFallbackPoll < wait) {
                    return;
                }
                lastFallbackPoll = now;
                refreshInboxOrUnread();
            }, 1000);
        })();
    </script>
@endif
