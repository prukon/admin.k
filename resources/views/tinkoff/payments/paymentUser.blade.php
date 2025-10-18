{{-- Тинькофф — СБП (QR без витрины) --}}
<div class="wrap-pay" style="max-width: 300px;">
    <h5 class="pay-name mt-3 mb-3">Тинькофф — СБП (QR)</h5>
    <img class="img-fluid d-block mx-auto" src="{{ asset('/img/partners/tinkoff.png') }}" alt="Tinkoff">
    <form class="mb-3 mt-3" action="{{ route('payment.tinkoff.qrInit') }}" method="POST">
        @csrf
        <input type="hidden" name="partner_id" value="{{ $partnerId }}">
        <input type="hidden" name="outSum" value="{{ $outSum }}">
        <button type="submit" class="btn btn-bd-primary pay-btn">Оплатить через СБП</button>
    </form>
    <div class="pay-fee mb-3">Комиссия: как у СБП</div>
</div>
