@extends('layouts.admin2')

@php
    $badge = [
      'INITIATED' => 'secondary',
      'NEW' => 'info',
      'AUTHORIZING' => 'info',
      'CHECKING' => 'info',
      'CREDIT_CHECKING' => 'info',
      'CHECKED' => 'primary',
      'COMPLETING' => 'info',
      'COMPLETED' => 'success',
      'REJECTED' => 'danger',
    ][$payout->status] ?? 'secondary';
@endphp

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Выплата #{{ $payout->id }}</h1>
            <div class="d-flex gap-2">
                <a href="/admin/tinkoff/payouts" class="btn btn-outline-secondary btn-sm">К списку</a>
                @if($payout->payment_id)
                    <a href="/admin/tinkoff/payments/{{ $payout->payment_id }}" class="btn btn-outline-primary btn-sm" target="_blank">Платёж</a>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-2">
                            <span class="badge text-bg-{{ $badge }}">{{ $payout->status }}</span>
                            @if($payout->source)
                                <span class="badge text-bg-light border">{{ $payout->source }}</span>
                            @endif
                            @if($payout->deal_id)
                                <span class="badge text-bg-dark">Deal {{ $payout->deal_id }}</span>
                            @endif
                            @if($payout->tinkoff_payout_payment_id)
                                <span class="badge text-bg-light border">PaymentId {{ $payout->tinkoff_payout_payment_id }}</span>
                            @endif
                        </div>

                        <div>Партнёр: <strong>{{ optional($payout->partner)->title ?? '#'.$payout->partner_id }}</strong></div>
                        <div>Плательщик: <strong>{{ $payout->payer?->full_name ?? ($payout->payer?->lastname.' '.$payout->payer?->name) ?? '—' }}</strong></div>
                        <div>Инициатор: <strong>
                                @if($payout->initiator)
                                    {{ $payout->initiator->full_name ?? trim(($payout->initiator->lastname ?? '').' '.($payout->initiator->name ?? '')) ?: '#'.$payout->initiated_by_user_id }}
                                @else
                                    {{ in_array((string)$payout->source, ['auto','scheduled'], true) ? 'Система' : '—' }}
                                @endif
                            </strong>
                        </div>

                        <div class="mt-2">
                            Запланирована: <strong>{{ $payout->when_to_run ? $payout->when_to_run->format('d.m.Y H:i') : '—' }}</strong>
                            @if($payout->completed_at)
                                <span class="ms-2">Завершена: <strong>{{ $payout->completed_at->format('d.m.Y H:i') }}</strong></span>
                            @endif
                        </div>

                        <hr>

                        <h6 class="mb-2">Snapshot расчёта @if(empty($breakdown['is_snapshot']))<span class="badge text-bg-warning">расчёт по текущим правилам</span>@endif</h6>
                        <div class="row text-nowrap">
                            <div class="col">Поступило (gross)</div>
                            <div class="col text-end">{{ roubles($breakdown['gross'] ?? null) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Комиссия оплаты</div>
                            <div class="col text-end">− {{ roubles($breakdown['bankAccept'] ?? null) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Комиссия выплаты</div>
                            <div class="col text-end">− {{ roubles($breakdown['bankPayout'] ?? null) }} ₽</div>
                        </div>
                        <div class="row text-nowrap">
                            <div class="col">Платформа</div>
                            <div class="col text-end">− {{ roubles($breakdown['platformFee'] ?? null) }} ₽</div>
                        </div>
                        <div class="row fw-bold text-nowrap">
                            <div class="col">К перечислению (net)</div>
                            <div class="col text-end">{{ roubles($breakdown['net'] ?? null) }} ₽</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">Payload</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="small text-muted mb-1">Init</div>
                                <pre class="small mb-0" style="max-height: 220px; overflow:auto;">{{ json_encode($payout->payload_init, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted mb-1">Payment</div>
                                <pre class="small mb-0" style="max-height: 220px; overflow:auto;">{{ json_encode($payout->payload_payment, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted mb-1">GetState / State</div>
                                <pre class="small mb-0" style="max-height: 220px; overflow:auto;">{{ json_encode($payout->payload_state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-2">Служебные поля</h6>
                        <div class="small text-muted">Создана: {{ $payout->created_at?->format('d.m.Y H:i') }}</div>
                        <div class="small text-muted">Обновлена: {{ $payout->updated_at?->format('d.m.Y H:i') }}</div>
                        <div class="small text-muted">Net (amount): {{ roubles($payout->amount) }} ₽</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

