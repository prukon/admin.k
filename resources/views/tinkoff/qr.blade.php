@extends('layouts.admin2')
@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Оплата через СБП</h4>
        <p>Отсканируйте QR-код в мобильном банке. После оплаты статус изменится автоматически.</p>

        <div id="qrBox" class="p-3 border rounded" style="display:inline-block;min-width:320px;text-align:center;">
            Загружаем QR…
        </div>

        <div class="mt-3 small text-muted" id="statusBox">Статус: ожидание…</div>
    </div>
@endsection

@push('scripts')
    <script>
        (function(){
            const qrUrl = @json($qrUrl ?? ("/tinkoff/qr/".$paymentId."/json"));
            const stateUrl = @json($stateUrl ?? ("/tinkoff/qr/".$paymentId."/state"));
            const successUrl = @json($successUrl ?? "/payment/success");
            const qrBox = document.getElementById('qrBox');
            const statusBox = document.getElementById('statusBox');

            /**
             * GetQr Data: при DataType=IMAGE — base64 SVG; иначе бывает PNG base64, data:…, сырой SVG XML.
             * URL qr.nspk.ru — страница (text/html), не изображение → только iframe.
             */
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

            async function fetchQr() {
                try {
                    const r = await fetch(qrUrl);
                    const j = await r.json();
                    if (j.Success && j.Data) {
                        appendQrToBox(qrBox, j.Data);
                    } else {
                        const msg = (j && (j.Message || j.Details)) ? `${j.Message || ''}<br>${j.Details || ''}` : 'Не удалось получить QR';
                        qrBox.innerHTML = `<div class="text-danger">${msg}</div>`;
                        console.log(j);
                    }
                } catch(e) {
                    qrBox.innerHTML = 'Ошибка загрузки QR';
                }
            }

            async function pollState() {
                try {
                    const r = await fetch(stateUrl);
                    const j = await r.json();
                    statusBox.textContent = 'Статус: ' + (j.Status || '—');
                    if (j.Status === 'CONFIRMED') {
                        statusBox.textContent = 'Статус: CONFIRMED — оплата принята';
                        setTimeout(()=>location.href = successUrl, 700);
                    } else if (j.Status === 'REJECTED' || j.Status === 'CANCELED') {
                        statusBox.textContent = 'Статус: ' + j.Status + ' — оплата не завершена';
                    } else {
                        setTimeout(pollState, 3000);
                    }
                } catch(e) {
                    setTimeout(pollState, 3000);
                }
            }

            fetchQr();
            pollState();
        })();
    </script>
@endpush
