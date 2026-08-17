{{-- Переключатель ширины кабинета. Сохраняется в users.layout_wide. --}}
@auth
    @php
        $layoutWide = (bool) auth()->user()->layout_wide;
        $layoutWideTitle = $layoutWide ? 'Обычная ширина' : 'На всю ширину экрана';
    @endphp
    <li class="nav-item d-none d-md-flex align-items-center me-2 position-relative"
        id="layout-wide-toggle-wrap">
        <button type="button"
            class="nav-link px-2 border-0 bg-transparent"
            id="layout-wide-toggle"
            data-url="{{ route('cabinet.layout-wide.update') }}"
            data-csrf="{{ csrf_token() }}"
            data-wide="{{ $layoutWide ? '1' : '0' }}"
            aria-pressed="{{ $layoutWide ? 'true' : 'false' }}"
            title="{{ $layoutWideTitle }}">
            <i class="fas {{ $layoutWide ? 'fa-compress-alt' : 'fa-expand-alt' }}" aria-hidden="true"></i>
            <span class="visually-hidden">{{ $layoutWideTitle }}</span>
        </button>
        <div class="invalid-feedback layout-wide-error" id="layout-wide-error" role="alert"></div>
    </li>
    <style>
        #layout-wide-toggle {
            color: inherit;
            line-height: 1;
        }

        #layout-wide-toggle:hover,
        #layout-wide-toggle:focus {
            color: #495057;
        }

        #layout-wide-error {
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

        #layout-wide-error.is-visible {
            display: block;
        }
    </style>
    <script>
        (function() {
            const button = document.getElementById('layout-wide-toggle');
            if (!button) {
                return;
            }

            const errorBox = document.getElementById('layout-wide-error');
            const icon = button.querySelector('i');
            const hiddenLabel = button.querySelector('.visually-hidden');
            let saving = false;

            function isWide() {
                return button.getAttribute('data-wide') === '1';
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

            function applyWide(wide) {
                document.body.classList.toggle('layout-wide', wide);
                button.setAttribute('data-wide', wide ? '1' : '0');
                button.setAttribute('aria-pressed', wide ? 'true' : 'false');
                const title = wide ? 'Обычная ширина' : 'На всю ширину экрана';
                button.setAttribute('title', title);
                if (hiddenLabel) {
                    hiddenLabel.textContent = title;
                }
                if (icon) {
                    icon.className = wide ? 'fas fa-compress-alt' : 'fas fa-expand-alt';
                }
                window.dispatchEvent(new Event('resize'));
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
                    try {
                        window.jQuery.fn.dataTable.tables({
                            visible: true,
                            api: true
                        }).columns.adjust();
                    } catch (e) {
                        /* no-op */
                    }
                }
            }

            function fieldError(payload) {
                if (payload && payload.errors && payload.errors.layout_wide) {
                    const messages = payload.errors.layout_wide;
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
                return 'Не удалось сохранить ширину кабинета.';
            }

            button.addEventListener('click', function() {
                if (saving) {
                    return;
                }
                const previousWide = isWide();
                const nextWide = !previousWide;
                applyWide(nextWide);
                showError('');
                saving = true;
                button.disabled = true;

                const body = new URLSearchParams();
                body.set('_token', button.getAttribute('data-csrf') || '');
                body.set('layout_wide', nextWide ? '1' : '0');

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
                        applyWide(previousWide);
                        showError(fieldError(result.payload));
                        return;
                    }
                    const savedWide = !!(result.payload && result.payload.layout_wide);
                    applyWide(savedWide);
                }).catch(function() {
                    applyWide(previousWide);
                    showError('Не удалось сохранить ширину кабинета.');
                }).then(function() {
                    saving = false;
                    button.disabled = false;
                });
            });
        })();
    </script>
@endauth
