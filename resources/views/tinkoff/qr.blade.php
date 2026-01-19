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
            const pid = @json($paymentId);
            const qrUrl = @json($qrUrl ?? ("/tinkoff/qr/".$paymentId."/json"));
            const stateUrl = @json($stateUrl ?? ("/tinkoff/qr/".$paymentId."/state"));
            const successUrl = @json($successUrl ?? "/payment/success");
            const qrBox = document.getElementById('qrBox');
            const statusBox = document.getElementById('statusBox');

            async function fetchQr() {
                try {
                    const r = await fetch(qrUrl);
                    const j = await r.json();
                    if (j.Success && j.Data) {
                        qrBox.innerHTML = `<img alt="QR" src="data:image/png;base64,${j.Data}" style="max-width:360px">`;
                    } else {
                        qrBox.innerHTML = 'Не удалось получить QR';
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
