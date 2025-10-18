{{-- resources/views/tinkoff/payments/index.blade.php --}}
@extends('layouts.admin2')
@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h5 mb-0">Tinkoff — Платежи</h1>
            <a href="/admin/tinkoff/commissions" class="btn btn-outline-secondary btn-sm">Комиссии</a>
        </div>

        <form class="row g-2 mb-3">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    @php $statuses = ['','NEW','FORM','CONFIRMED','REJECTED','CANCELED']; @endphp
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" @selected(request('status')===$st)>{{ $st?:'Все статусы' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="partner_id" class="form-select form-select-sm">
                    <option value="">Все партнёры</option>
                    @foreach($partners as $p)
                        <option value="{{ $p->id }}" @selected(request('partner_id')==$p->id)>{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" placeholder="с">
            </div>
            <div class="col-auto">
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" placeholder="по">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Фильтр</button>
                <a href="/admin/tinkoff/payments" class="btn btn-link btn-sm">Сброс</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                <tr>
                    <th>ID</th><th>Создан</th><th>Партнёр</th><th>Order</th><th>Сумма</th><th>Статус</th><th>Deal</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($payments as $p)
                    @php
                        $badge = ['NEW'=>'secondary','FORM'=>'info','CONFIRMED'=>'success','REJECTED'=>'danger','CANCELED'=>'warning'][$p->status] ?? 'secondary';
                    @endphp
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td class="text-nowrap">{{ $p->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ optional($p->partner)->title ?? '#'.$p->partner_id }}</td>
                        <td class="small">{{ $p->order_id }}</td>
                        <td>{{ number_format($p->amount/100,2,',',' ') }} ₽</td>
                        <td><span class="badge text-bg-{{ $badge }}">{{ $p->status }}</span></td>
                        <td>{{ $p->deal_id ?? '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="/admin/tinkoff/payments/{{ $p->id }}">Открыть</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $payments->links() }}
    </div>
@endsection
