{{-- Переключатель системных мониторов. Сохраняется в users.system_monitors. --}}
@can('settings.systemMonitors.view')
    @php
        $systemMonitorsOn = (bool) auth()->user()?->system_monitors;
        $systemMonitorsTitle = $systemMonitorsOn ? 'Скрыть системные мониторы' : 'Показать системные мониторы';
    @endphp
    <li class="nav-item d-flex align-items-center me-2 position-relative"
        id="system-monitors-toggle-wrap">
        <button type="button"
            class="ios-switch"
            id="system-monitors-toggle"
            role="switch"
            data-url="{{ route('cabinet.system-monitors.update') }}"
            data-csrf="{{ csrf_token() }}"
            data-on="{{ $systemMonitorsOn ? '1' : '0' }}"
            aria-checked="{{ $systemMonitorsOn ? 'true' : 'false' }}"
            title="{{ $systemMonitorsTitle }}">
            <span class="ios-switch__knob" aria-hidden="true"></span>
            <span class="visually-hidden">{{ $systemMonitorsTitle }}</span>
        </button>
        <div class="invalid-feedback system-monitors-error" id="system-monitors-error" role="alert" data-error-for="system_monitors"></div>
    </li>
    <style>
        .ios-switch {
            position: relative;
            width: 36px;
            height: 22px;
            border: 0;
            padding: 0;
            border-radius: 999px;
            background: #e5e7eb;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.2s ease;
            line-height: 1;
        }

        .ios-switch[aria-checked="true"] {
            background: #34c759;
        }

        .ios-switch:focus-visible {
            outline: 2px solid #86efac;
            outline-offset: 2px;
        }

        .ios-switch:disabled {
            opacity: 0.65;
            cursor: default;
        }

        .ios-switch__knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
            transition: transform 0.2s ease;
        }

        .ios-switch[aria-checked="true"] .ios-switch__knob {
            transform: translateX(14px);
        }

        #system-monitors-error {
            position: absolute;
            top: calc(100% - 0.15rem);
            right: 0;
            min-width: 12rem;
            margin: 0;
            z-index: 1050;
            display: none;
            background: #fff;
            padding: 0.25rem 0.4rem;
            border-radius: 0.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.08);
        }

        #system-monitors-error.is-visible {
            display: block;
        }
    </style>
    <script>
        (function() {
            const button = document.getElementById('system-monitors-toggle');
            if (!button) {
                return;
            }

            const errorBox = document.getElementById('system-monitors-error');
            const hiddenLabel = button.querySelector('.visually-hidden');
            let saving = false;

            function isOn() {
                return button.getAttribute('data-on') === '1';
            }

            function showError(message) {
                if (!errorBox) {
                    return;
                }
                errorBox.textContent = message || '';
                if (message) {
                    errorBox.classList.add('is-visible');
                } else {
                    errorBox.classList.remove('is-visible');
                }
            }

            function applyOn(on) {
                button.setAttribute('data-on', on ? '1' : '0');
                button.setAttribute('aria-checked', on ? 'true' : 'false');
                const title = on ? 'Скрыть системные мониторы' : 'Показать системные мониторы';
                button.setAttribute('title', title);
                if (hiddenLabel) {
                    hiddenLabel.textContent = title;
                }
                document.body.classList.toggle('system-monitors-on', on);
                document.dispatchEvent(new CustomEvent('system-monitors:change', {
                    detail: { on: on }
                }));
            }

            function fieldError(payload) {
                if (payload && payload.errors && payload.errors.system_monitors) {
                    const messages = payload.errors.system_monitors;
                    if (Array.isArray(messages) && messages[0]) {
                        return messages[0];
                    }
                    if (typeof messages === 'string' && messages) {
                        return messages;
                    }
                }
                if (payload && payload.message) {
                    return payload.message;
                }
                return 'Не удалось сохранить системные мониторы.';
            }

            button.addEventListener('click', function() {
                if (saving) {
                    return;
                }
                const previousOn = isOn();
                const nextOn = !previousOn;
                applyOn(nextOn);
                showError('');
                saving = true;
                button.disabled = true;

                const body = new URLSearchParams();
                body.set('_token', button.getAttribute('data-csrf') || '');
                body.set('system_monitors', nextOn ? '1' : '0');

                fetch(button.getAttribute('data-url'), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString(),
                    credentials: 'same-origin'
                }).then(function(response) {
                    return response.json().then(function(payload) {
                        return {
                            ok: response.ok,
                            payload: payload
                        };
                    }).catch(function() {
                        return {
                            ok: response.ok,
                            payload: null
                        };
                    });
                }).then(function(result) {
                    if (!result.ok) {
                        applyOn(previousOn);
                        showError(fieldError(result.payload));
                        return;
                    }
                    const savedOn = !!(result.payload && result.payload.system_monitors);
                    applyOn(savedOn);
                }).catch(function() {
                    applyOn(previousOn);
                    showError('Не удалось сохранить системные мониторы.');
                }).then(function() {
                    saving = false;
                    button.disabled = false;
                });
            });
        })();
    </script>
@endcan
