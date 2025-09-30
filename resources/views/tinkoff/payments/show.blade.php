@extends('layouts.admin2')

@php
    function roubles($cents){ return number_format($cents/100,2,',',' '); }
    $badge = [
      'NEW' => 'secondary','FORM'=>'info','CONFIRMED'=>'success',
      'REJECTED'=>'danger','CANCELED'=>'warning'
    ][$payment->status] ?? 'secondary';
@endphp

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Платёж #{{ $payment->id }} (Order {{ $payment->order_id }})</h1>
            <a href="/admin/tinkoff/partners/{{ $payment->partner_id }}" class="btn btn-outline-secondary btn-sm">К партнёру</a>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="mb-1">
                                    <span class="badge text-bg-{{ $badge }}">{{ $payment->status }}</span>
                                    @if($payment->deal_id)
                                        <span class="badge text-bg-dark">Deal {{ $payment->deal_id }}</span>
                                    @endif
                                    @if($payment->tinkoff_payment_id)
                                        <span class="badge text-bg-light border">PaymentId {{ $payment->tinkoff_payment_id }}</span>
                                    @endif
                                </div>
                                <div>Сумма: <strong>{{ roubles($payment->amount) }} ₽</strong></div>
                                <div>Метод: {{ $payment->method ?? '—' }}</div>
                                <div>Партнёр: {{ optional($payment->partner)->title ?? '#'.$payment->partner_id }}</div>
                                @if($refundUntil)
                                    <div class="mt-2">
                                        <span class="badge text-bg-warning">Окно возврата до: {{ $refundUntil->format('d.m.Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="text-end">
                                @if($payment->payment_url)
                                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ $payment->payment_url }}">Ссылка на оплату</a>
                                @endif
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-2">Калькуляция</h6>
                        <div class="row text-nowrap">
                            <div class="col">Поступило</div>
                            <div class="col text-end">{{ roubles($breakdown['gross']) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Банк (приём 2.49% мин 3.49 ₽)</div>
                            <div class="col text-end">− {{ roubles($breakdown['bankAccept']) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Банк (выплата ЮЛ 0.1%)</div>
                            <div class="col text-end">− {{ roubles($breakdown['bankPayout']) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Моя комиссия</div>
                            <div class="col text-end">− {{ roubles($breakdown['myFee']) }} ₽</div>
                        </div>
                        <div class="row fw-bold text-nowrap">
                            <div class="col">К перечислению партнёру</div>
                            <div class="col text-end">{{ roubles($breakdown['net']) }} ₽</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">Действия</h6>

                        @if($payment->deal_id)
                            <form class="d-inline" method="post" action="/tinkoff/payouts/{{ $payment->deal_id }}/pay-now">
                                @csrf
                                <button class="btn btn-success btn-sm">Выплатить сейчас</button>
                            </form>

                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#delayModal">Отложить до…</button>

                            <form class="d-inline" method="post" action="/tinkoff/deals/{{ $payment->deal_id }}/close" onsubmit="return confirm('Закрыть сделку?')">
                                @csrf
                                <button class="btn btn-outline-danger btn-sm">Закрыть сделку</button>
                            </form>
                        @else
                            <div class="text-muted">DealId ещё не присвоен (ждём вебхук с SpAccumulationId).</div>
                        @endif
                    </div>
                </div>

                <!-- Modal Отложить -->
                <div class="modal fade" id="delayModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="post" action="/tinkoff/payouts/{{ $payment->deal_id }}/delay">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title">Отложить выплату</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">Выплатить в (Europe/Riga)</label>
                                <input class="form-control" type="datetime-local" name="run_at" required>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Отмена</button>
                                <button class="btn btn-primary">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-2">Служебные поля</h6>
                        <div class="small text-muted">Создан: {{ $payment->created_at?->format('d.m.Y H:i') }}</div>
                        <div class="small text-muted">Подтверждён: {{ $payment->confirmed_at?->format('d.m.Y H:i') ?? '—' }}</div>
                        <div class="small text-muted">Отменён: {{ $payment->canceled_at?->format('d.m.Y H:i') ?? '—' }}</div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">Raw payload</h6>
                        <pre class="small mb-0" style="max-height: 320px; overflow:auto;">{{ json_encode($payment->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
