@if(\App\Support\SystemMonitors::canView(auth()->user()))
    <div id="js-reverb-status" class="reverb-status system-monitor" data-status-url="{{ route('chat.api.reverb-status') }}" aria-live="polite">
        <div class="reverb-status__head">
            <div class="reverb-status__title">Reverb</div>
            <button type="button" class="reverb-status__copy" data-role="copy" title="Копировать состояние" aria-label="Копировать состояние">
                <i class="fas fa-copy" aria-hidden="true"></i>
            </button>
        </div>
        <div class="reverb-status__row">
            <span class="reverb-status__label">процесс</span>
            <span class="reverb-status__dot" data-role="process-dot"></span>
            <span data-role="process-text">…</span>
        </div>
        <div class="reverb-status__row">
            <span class="reverb-status__label">сокет</span>
            <span class="reverb-status__dot" data-role="socket-dot"></span>
            <span data-role="socket-text">…</span>
        </div>
    </div>
    <style>
        body:not(.system-monitors-on) .system-monitor {
            display: none !important;
        }
        .reverb-status {
            min-width: 220px;
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(17, 24, 39, 0.94);
            color: #f9fafb;
            font: 12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
            pointer-events: none;
        }
        .reverb-status.is-ok { outline: 1px solid #34d399; }
        .reverb-status.is-warn { outline: 1px solid #fbbf24; }
        .reverb-status.is-bad { outline: 1px solid #f87171; }
        .reverb-status__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
        .reverb-status__title { font-weight: 700; letter-spacing: .02em; }
        .reverb-status__copy {
            pointer-events: auto;
            cursor: pointer;
            border: 0;
            background: transparent;
            color: #e5e7eb;
            padding: 2px 4px;
            line-height: 1;
            border-radius: 4px;
        }
        .reverb-status__copy:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }
        .reverb-status__copy.is-copied { color: #34d399; }
        .reverb-status__row { display: flex; align-items: center; gap: 6px; margin-top: 3px; }
        .reverb-status__label { width: 58px; color: #d1d5db; }
        .reverb-status__dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6b7280;
            flex: 0 0 8px;
        }
        .reverb-status__dot.is-ok { background: #34d399; }
        .reverb-status__dot.is-warn { background: #fbbf24; }
        .reverb-status__dot.is-bad { background: #f87171; }
    </style>
    <script>
        (function () {
            var root = document.getElementById('js-reverb-status');
            if (!root) {
                return;
            }
            var statusUrl = root.getAttribute('data-status-url');
            var processDot = root.querySelector('[data-role="process-dot"]');
            var processText = root.querySelector('[data-role="process-text"]');
            var socketDot = root.querySelector('[data-role="socket-dot"]');
            var socketText = root.querySelector('[data-role="socket-text"]');
            var copyBtn = root.querySelector('[data-role="copy"]');
            var listening = false;
            var processMeta = '';
            var copyTimer = null;
            var processTimer = null;
            var paintTimer = null;

            function monitorsOn() {
                return document.body.classList.contains('system-monitors-on');
            }

            function toneClass(kind) {
                return kind === 'ok' ? 'is-ok' : (kind === 'warn' ? 'is-warn' : 'is-bad');
            }

            function setDot(el, kind) {
                el.classList.remove('is-ok', 'is-warn', 'is-bad');
                el.classList.add(toneClass(kind));
            }

            function socketState() {
                if (!window.Echo || typeof window.Echo.private !== 'function') {
                    return 'не создан';
                }
                var pusher = window.Echo.connector && window.Echo.connector.pusher;
                if (!pusher || !pusher.connection) {
                    return 'нет connector';
                }
                return String(pusher.connection.state || 'unknown');
            }

            function socketKind(state) {
                if (state === 'connected') {
                    return 'ok';
                }
                if (state === 'connecting' || state === 'initialized') {
                    return 'warn';
                }
                return 'bad';
            }

            function statusText() {
                var processLine = 'процесс: ' + (processText.textContent || '') + (processMeta ? ' (' + processMeta + ')' : '');
                var socketLine = 'сокет: ' + (socketText.textContent || '');
                return 'Reverb\n' + processLine + '\n' + socketLine;
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
                    copyBtn.setAttribute('title', 'Копировать состояние');
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

            function paint() {
                var sock = socketState();
                var sockKind = socketKind(sock);
                var procKind = listening ? 'ok' : 'bad';
                processText.textContent = listening ? 'up' : 'down';
                socketText.textContent = sock;
                setDot(processDot, procKind);
                setDot(socketDot, sockKind);
                root.classList.remove('is-ok', 'is-warn', 'is-bad');
                if (listening && sockKind === 'ok') {
                    root.classList.add('is-ok');
                } else if (listening || sockKind === 'warn') {
                    root.classList.add('is-warn');
                } else {
                    root.classList.add('is-bad');
                }
            }

            function refreshProcess() {
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
                    if (data && typeof data.listening !== 'undefined') {
                        listening = !!data.listening;
                        if (data.host && data.port) {
                            processMeta = data.host + ':' + data.port + (data.driver ? ', ' + data.driver : '');
                            processText.setAttribute('title', processMeta);
                        }
                    } else {
                        listening = false;
                    }
                    paint();
                }).catch(function () {
                    listening = false;
                    paint();
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
                if (processTimer || paintTimer) {
                    return;
                }
                paint();
                refreshProcess();
                processTimer = setInterval(refreshProcess, 3000);
                paintTimer = setInterval(paint, 1000);
            }

            function stopWatching() {
                if (processTimer) {
                    clearInterval(processTimer);
                    processTimer = null;
                }
                if (paintTimer) {
                    clearInterval(paintTimer);
                    paintTimer = null;
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

            var pusher = window.Echo && window.Echo.connector && window.Echo.connector.pusher;
            if (pusher && pusher.connection && typeof pusher.connection.bind === 'function') {
                pusher.connection.bind('state_change', function () {
                    if (monitorsOn()) {
                        paint();
                    }
                });
            }
        })();
    </script>
@endif
