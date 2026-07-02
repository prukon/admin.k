@extends('layouts.app')

@section('title', 'Оплата абонемента')

@section('content')
    <style>
        .ulp-pay-summary {
            border: 1px solid #e4edff;
            border-radius: 12px;
            padding: 14px 16px;
            background: #f8fbff;
        }
        .ulp-pay-summary .summary-item-label {
            font-size: 12px;
            color: #60708f;
            margin-bottom: 2px;
        }
        .ulp-pay-summary .summary-item-value {
            font-size: 15px;
            font-weight: 600;
            color: #1d2a44;
            word-break: break-word;
        }
        .ulp-pay-summary .payment-service-provider {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }
        .ulp-pay-summary .payment-service-provider--with-team {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #dce7ff;
        }
        .ulp-pay-summary .payment-service-provider-team + .summary-item-label {
            margin-top: 0;
        }
    </style>
    <div class="container text-start" style="max-width: 42rem;">
        <div class="d-flex flex-wrap align-items-center gap-3 pt-3 mb-2">
            <img class="img-fluid" src="{{ asset('/img/partners/tbank.png') }}" alt="T‑Bank" style="max-height: 40px; width: auto;">
            <h1 class="h4 mb-0">Оплата через СБП</h1>
        </div>

        <p class="text-muted mb-2 d-none" id="introDesktop">Отсканируйте QR-код в приложении банка. После успешной оплаты статус обновится автоматически.</p>
        <p class="text-muted mb-2 d-none" id="introMobile">Нажмите кнопку ниже — откроется страница СБП (НСПК), выберите банк и подтвердите оплату. Вернитесь сюда: статус обновится автоматически. QR-код можно показать, если удобнее оплатить со второго телефона.</p>

        @if(!empty($amountRubFormatted))
            <p class="mb-3"><span class="text-muted">К оплате:</span> <strong>{{ $amountRubFormatted }}&nbsp;₽</strong></p>
        @endif

        @if(!empty($showTbankLegalEntityBlock))
            <div class="ulp-pay-summary mb-3">
                @include('payment.partials.service-provider', [
                    'serviceProviderFallbackText' => 'Обратитесь в школу/клуб.',
                ])
            </div>
        @endif

        <div id="nspkMobileCta" class="d-none mb-3">
            <button type="button" class="btn btn-primary btn-lg px-4" id="nspkPayBtn" aria-label="Перейти к оплате через СБП на сайте НСПК">
                Перейти к оплате СБП
            </button>
            <div class="small text-danger mt-2 d-none" id="nspkError" role="alert"></div>
        </div>

        <details class="mb-3 d-none border rounded p-2 bg-light" id="qrDetailsFallback">
            <summary class="fw-semibold cursor-pointer user-select-none" style="cursor:pointer;">Показать QR-код</summary>
            <div class="pt-3 text-center">
                <div id="qrBox" class="p-3 border rounded bg-white mx-auto" style="display:inline-block;min-width:280px;max-width:100%;text-align:center;">
                    <span class="text-muted small">QR загрузится после раскрытия этого блока</span>
                </div>
            </div>
        </details>

        <div id="qrBoxDesktopWrap" class="mb-3">
            <div id="qrBoxDesktop" class="p-3 border rounded bg-light" style="display:inline-block;min-width:280px;max-width:100%;text-align:center;">
                Загружаем QR…
            </div>
        </div>

        <div class="mt-3 small text-muted" id="statusBox">Статус: ожидание…</div>

        <div class="mt-4">
            <a href="{{ url('/') }}" class="btn btn-outline-secondary">На главную</a>
        </div>
    </div>
@endsection

@section('scripts')
    @parent
    <script>
        (function(){
            const qrUrl = @json(route('ulp.public.pay.qr.json', ['token' => $token]));
            const nspkPayloadUrl = @json(route('ulp.public.pay.qr.payload', ['token' => $token]));
            const stateUrl = @json(route('ulp.public.pay.qr.state', ['token' => $token]));
            const successUrl = @json($successUrl ?? url('/'));
            const serverMobile = @json(!empty($isMobileClient));

            const introDesktop = document.getElementById('introDesktop');
            const introMobile = document.getElementById('introMobile');
            const nspkMobileCta = document.getElementById('nspkMobileCta');
            const nspkPayBtn = document.getElementById('nspkPayBtn');
            const nspkError = document.getElementById('nspkError');
            const qrDetailsFallback = document.getElementById('qrDetailsFallback');
            const qrBox = document.getElementById('qrBox');
            const qrBoxDesktop = document.getElementById('qrBoxDesktop');
            const qrBoxDesktopWrap = document.getElementById('qrBoxDesktopWrap');
            const statusBox = document.getElementById('statusBox');

            function isNspkPreferredViewport() {
                return serverMobile || window.matchMedia('(max-width: 767.98px)').matches;
            }

            function appendQrToBox(box, data) {
                const s = String(data).trim();
                if (/^https?:\/\/qr\.nspk\.ru\//i.test(s)) {
                    const iframe = document.createElement('iframe');
                    iframe.src = s;
                    iframe.title = 'QR СБП';
                    iframe.width = '360';
                    iframe.height = '440';
                    iframe.style.border = '0';
                    iframe.style.maxWidth = '100%';
                    iframe.setAttribute('sandbox', 'allow-scripts allow-forms allow-popups allow-same-origin');
                    box.replaceChildren(iframe);
                    return;
                }
                const img = document.createElement('img');
                img.alt = 'QR';
                img.style.maxWidth = '360px';
                if (/^https?:\/\//i.test(s)) {
                    img.src = s;
                    img.referrerPolicy = 'no-referrer';
                } else if (s.startsWith('data:')) {
                    img.src = s;
                } else if (s.startsWith('<svg') || s.startsWith('<' + '?xml')) {
                    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(s);
                } else if (s.startsWith('iVBOR')) {
                    img.src = 'data:image/png;base64,' + s;
                } else {
                    img.src = 'data:image/svg+xml;base64,' + s;
                }
                box.replaceChildren(img);
            }

            function isAllowedNspkHttpsUrl(s) {
                try {
                    const u = new URL(s);
                    if (u.protocol !== 'https:') return false;
                    const h = u.hostname.toLowerCase();
                    return h === 'qr.nspk.ru' || h.endsWith('.nspk.ru');
                } catch (e) {
                    return false;
                }
            }

            let desktopQrDone = false;
            let detailsQrDone = false;
            async function fetchQrImage(targetBox, kind) {
                try {
                    const r = await fetch(qrUrl);
                    const j = await r.json();
                    if (j.Success && j.Data) {
                        appendQrToBox(targetBox, j.Data);
                        if (kind === 'desktop') desktopQrDone = true;
                        if (kind === 'details') detailsQrDone = true;
                    } else {
                        const msg = (j && (j.Message || j.Details)) ? (String(j.Message || '') + ' ' + String(j.Details || '')).trim() : 'Не удалось получить QR';
                        targetBox.innerHTML = '';
                        const err = document.createElement('div');
                        err.className = 'text-danger small';
                        err.textContent = msg;
                        targetBox.appendChild(err);
                    }
                } catch (e) {
                    targetBox.textContent = 'Ошибка загрузки QR';
                }
            }

            let pollStopped = false;
            let pollTimer = null;
            function schedulePoll(ms) {
                if (pollStopped) return;
                if (pollTimer) clearTimeout(pollTimer);
                pollTimer = setTimeout(pollState, ms);
            }

            async function pollState() {
                if (pollStopped) return;
                try {
                    const r = await fetch(stateUrl);
                    const j = await r.json();
                    statusBox.textContent = 'Статус: ' + (j.Status || '—');
                    if (j.Status === 'CONFIRMED') {
                        pollStopped = true;
                        if (pollTimer) clearTimeout(pollTimer);
                        statusBox.textContent = 'Статус: CONFIRMED — оплата принята';
                        setTimeout(function() { location.href = successUrl; }, 700);
                    } else if (j.Status === 'REJECTED' || j.Status === 'CANCELED') {
                        pollStopped = true;
                        if (pollTimer) clearTimeout(pollTimer);
                        statusBox.textContent = 'Статус: ' + j.Status + ' — оплата не завершена';
                    } else {
                        schedulePoll(3000);
                    }
                } catch (e) {
                    schedulePoll(3000);
                }
            }

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible' && !pollStopped) {
                    pollState();
                    desktopQrDone = false;
                    detailsQrDone = false;
                    if (!isNspkPreferredViewport()) {
                        qrBoxDesktop.innerHTML = '<span class="text-muted small">Загружаем QR…</span>';
                        fetchQrImage(qrBoxDesktop, 'desktop');
                    }
                }
            });

            function setNspkError(text) {
                if (!text) {
                    nspkError.textContent = '';
                    nspkError.classList.add('d-none');
                    return;
                }
                nspkError.textContent = text;
                nspkError.classList.remove('d-none');
            }

            async function openNspkPayload() {
                setNspkError('');
                const prevLabel = nspkPayBtn.textContent;
                nspkPayBtn.disabled = true;
                nspkPayBtn.textContent = 'Открываем страницу оплаты…';
                try {
                    const r = await fetch(nspkPayloadUrl);
                    const j = await r.json();
                    if (j.Success && j.Data) {
                        const url = String(j.Data).trim();
                        if (!isAllowedNspkHttpsUrl(url)) {
                            setNspkError('Банк вернул неожиданную ссылку для оплаты. Попробуйте QR-код или оплату с компьютера.');
                            return;
                        }
                        window.location.assign(url);
                        return;
                    }
                    const msg = (j && (j.Message || j.Details)) ? String(j.Message || j.Details) : 'Не удалось получить ссылку на оплату';
                    setNspkError(msg);
                } catch (e) {
                    setNspkError('Ошибка сети. Повторите попытку.');
                } finally {
                    nspkPayBtn.disabled = false;
                    nspkPayBtn.textContent = prevLabel;
                }
            }

            function applyLayout() {
                const mobile = isNspkPreferredViewport();
                if (mobile) {
                    introDesktop.classList.add('d-none');
                    introMobile.classList.remove('d-none');
                    nspkMobileCta.classList.remove('d-none');
                    qrDetailsFallback.classList.remove('d-none');
                    qrBoxDesktopWrap.classList.add('d-none');
                } else {
                    introDesktop.classList.remove('d-none');
                    introMobile.classList.add('d-none');
                    nspkMobileCta.classList.add('d-none');
                    qrDetailsFallback.classList.add('d-none');
                    qrBoxDesktopWrap.classList.remove('d-none');
                    if (!desktopQrDone) {
                        fetchQrImage(qrBoxDesktop, 'desktop');
                    }
                }
            }

            nspkPayBtn.addEventListener('click', function() {
                openNspkPayload();
            });

            qrDetailsFallback.addEventListener('toggle', function() {
                if (qrDetailsFallback.open && !detailsQrDone) {
                    qrBox.innerHTML = '<span class="text-muted small">Загружаем QR…</span>';
                    fetchQrImage(qrBox, 'details');
                }
            });

            window.addEventListener('resize', function() {
                applyLayout();
            });

            applyLayout();
            pollState();
        })();
    </script>
@endsection
