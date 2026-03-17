@extends('layouts.admin2')

@php
    function roubles($cents){ return number_format($cents/100,2,',',' '); }
    function pretty_json($v){
        if ($v === null) return '';
        if (is_string($v)) return $v;
        return json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }
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
                                @php
                                    $pPartner = $payment->partner;
                                    $taxVal = $pPartner ? $pPartner->taxation_system : null;
                                    $taxLabel = $taxVal !== null && $taxVal !== '' ? (match((int)$taxVal) { 0=>'ОСН', 1=>'УСН доход', 2=>'УСН доход − расход', 3=>'ЕНВД', 4=>'ЕСХН', 5=>'Патент', default=>(string)$taxVal }) : '—';
                                @endphp
                                <div class="small text-muted">СНО (для чеков): {{ $taxLabel }}</div>
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
                            <div class="col text-end">− {{ roubles($breakdown['platformFee']) }} ₽</div>
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

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-2">История статусов</h6>
                            <div class="small text-muted">
                                {{ count($historyEvents ?? []) }} событий
                            </div>
                        </div>

                        @if(empty($historyEvents))
                            <div class="text-muted">История пока отсутствует.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width: 170px;">Время</th>
                                        <th style="width: 90px;">Тип</th>
                                        <th style="width: 110px;">Источник</th>
                                        <th>Статус</th>
                                        <th style="width: 220px;">Детали</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($historyEvents as $ev)
                                        <tr>
                                            <td class="text-nowrap">
                                                {{ $ev['at'] ? $ev['at']->format('d.m.Y H:i:s') : '—' }}
                                            </td>
                                            <td class="text-nowrap">{{ $ev['kind'] ?? '—' }}</td>
                                            <td class="text-nowrap">{{ $ev['source'] ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $to = $ev['to_status'] ?? ($ev['bank_status'] ?? '');
                                                    $from = $ev['from_status'] ?? null;
                                                @endphp
                                                <div><b>{{ $to !== '' ? $to : '—' }}</b></div>
                                                @if($from)
                                                    <div class="small text-muted">← {{ $from }}</div>
                                                @endif
                                                @if(!empty($ev['bank_status']) && ($ev['bank_status'] !== ($ev['to_status'] ?? null)))
                                                    <div class="small text-muted">BankStatus: {{ $ev['bank_status'] }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if(($ev['kind'] ?? '') === 'payment')
                                                    @if(!empty($ev['bank_payment_id']))
                                                        <div class="small">PaymentId: <span class="text-muted">{{ $ev['bank_payment_id'] }}</span></div>
                                                    @endif
                                                    @if(!empty($ev['order_id']))
                                                        <div class="small">OrderId: <span class="text-muted">{{ $ev['order_id'] }}</span></div>
                                                    @endif
                                                @elseif(($ev['kind'] ?? '') === 'payout')
                                                    @if(!empty($ev['payout_id']))
                                                        <div class="small">PayoutId: <span class="text-muted">{{ $ev['payout_id'] }}</span></div>
                                                    @endif
                                                @endif

                                                @if(!empty($ev['payload']))
                                                    <details class="mt-1">
                                                        <summary class="small">payload</summary>
                                                        <pre class="small mb-0 bg-light p-2 rounded" style="max-height:240px; overflow:auto;">{{ pretty_json($ev['payload']) }}</pre>
                                                    </details>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
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

                @if(!empty($payouts) && count($payouts) > 0)
                    <div class="card shadow-sm mt-3">
                        <div class="card-body">
                            <h6 class="mb-2">Выплаты по deal_id</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:70px;">ID</th>
                                        <th style="width:110px;">Статус</th>
                                        <th>Сумма</th>
                                        <th style="width:170px;">Когда</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($payouts as $po)
                                        <tr>
                                            <td class="text-nowrap">{{ $po->id }}</td>
                                            <td class="text-nowrap">{{ $po->status }}</td>
                                            <td class="text-nowrap">{{ roubles((int) $po->amount) }} ₽</td>
                                            <td class="text-nowrap">
                                                @if($po->when_to_run)
                                                    {{ $po->when_to_run->format('d.m.Y H:i') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

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
