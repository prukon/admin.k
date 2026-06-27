@extends('layouts.admin2')

@section('content')
    <div class="container py-3">
        <div class="main-content text-start">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h1 class="h5 mb-0">T‑Bank: {{ $partner->title }} (ID {{ $partner->id }})</h1>
                @can('legal_entities.view')
                    <a href="{{ route('admin.legal-entities.index') }}" class="btn btn-outline-primary btn-sm">
                        Справочник «Юр. лица»
                    </a>
                @endcan
            </div>

            @if (session('ok'))
                <div class="alert alert-success">{{ session('ok') }}</div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif

            <div class="alert alert-info">
                Регистрация и обновление реквизитов T‑Bank (sm-register) выполняются в справочнике
                <a href="{{ route('admin.legal-entities.index') }}">«Юр. лица»</a>.
                Legacy-поля партнёра (<code>tinkoff_partner_id</code>) сохранены для обратной совместимости.
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="mb-2">Ожидают выплат: <strong>{{ $waiting }}</strong></div>
                            <div class="small text-muted">Legacy shopCode: {{ $partner->tinkoff_partner_id ?? '—' }}</div>
                            <div class="small text-muted">Legacy sm-register: {{ $partner->sm_register_status ?? '—' }}</div>
                            <hr class="my-2">
                            <div class="small">
                                <strong>Автовыплата:</strong>
                                @if($autoPayoutSummary->isEmpty())
                                    <span class="text-muted">нет правил комиссий</span>
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($autoPayoutSummary as $row)
                                            <li>
                                                {{ $row['method'] }} —
                                                @if($row['enabled'])
                                                    <span class="badge text-bg-success">вкл</span>,
                                                    {{ $row['delay_hours'] }} ч
                                                @else
                                                    <span class="badge text-bg-secondary">выкл</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="small text-muted">Обработка отложенных: каждые {{ $scheduledIntervalMinutes }} мин.</div>
                            <div class="small text-muted">
                                За 30 дн.: {{ $autoPayoutCount30 ?? 0 }} автовыплат
                                @if(!empty($autoPayoutLastAt))
                                    , последняя {{ $autoPayoutLastAt->format('d.m.Y H:i') }}
                                @endif
                                — <a href="{{ url('/admin/tinkoff/payouts?partner_id=' . $partner->id . '&source=auto') }}" target="_blank">к выплатам</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-2">Недавние платежи</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                    <tr><th>ID</th><th>Order</th><th>Сумма</th><th>Статус</th><th>Deal</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse($latestPayments as $p)
                                        @php
                                            $badge = ['NEW'=>'secondary','FORM'=>'info','CONFIRMED'=>'success','REJECTED'=>'danger','CANCELED'=>'warning'][$p->status] ?? 'secondary';
                                        @endphp
                                        <tr>
                                            <td>{{ $p->id }}</td>
                                            <td>{{ $p->order_id }}</td>
                                            <td>{{ number_format($p->amount/100,2,',',' ') }} ₽</td>
                                            <td><span class="badge text-bg-{{ $badge }}">{{ $p->status }}</span></td>
                                            <td>{{ $p->deal_id ?? '—' }}</td>
                                            <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="/admin/tinkoff/payments/{{ $p->id }}">Открыть</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-muted">Платежей пока нет</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
