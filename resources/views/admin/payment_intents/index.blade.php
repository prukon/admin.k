@extends('layouts.admin2')
@section('content')

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3">Payment intents</h4>

        <form method="GET" action="{{ route('admin.payment-intents.index') }}" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label">InvId</label>
                    <input class="form-control" name="inv_id" value="{{ $filters['inv_id'] ?? '' }}" placeholder="123">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Partner ID</label>
                    <input class="form-control" name="partner_id" value="{{ $filters['partner_id'] ?? '' }}" placeholder="1">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">User ID</label>
                    <input class="form-control" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="42">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Provider</label>
                    <select class="form-select" name="provider">
                        @php($provider = $filters['provider'] ?? '')
                        <option value="">—</option>
                        <option value="robokassa" {{ $provider === 'robokassa' ? 'selected' : '' }}>robokassa</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Status</label>
                    @php($status = $filters['status'] ?? '')
                    <select class="form-select" name="status">
                        <option value="">—</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>pending</option>
                        <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>paid</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>failed</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>cancelled</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Фильтр</button>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label">Created from</label>
                    <input class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Created to</label>
                    <input class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Paid from</label>
                    <input class="form-control" type="date" name="paid_from" value="{{ $filters['paid_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Paid to</label>
                    <input class="form-control" type="date" name="paid_to" value="{{ $filters['paid_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <a class="btn btn-outline-secondary w-100" href="{{ route('admin.payment-intents.index') }}">Сброс</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>InvId</th>
                    <th>Partner</th>
                    <th>User</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>OutSum</th>
                    <th>PaymentDate</th>
                    <th>Created</th>
                    <th>Paid</th>
                    <th>Meta</th>
                </tr>
                </thead>
                <tbody>
                @forelse($intents as $intent)
                    <tr>
                        <td>{{ $intent->id }}</td>
                        <td>
                            {{ $intent->partner_id }}
                            @if($intent->partner)
                                <div class="small text-muted">{{ $intent->partner->title ?? ($intent->partner->name ?? '') }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $intent->user_id }}
                            @if($intent->user)
                                <div class="small text-muted">{{ $intent->user->full_name ?? $intent->user->name }}</div>
                            @endif
                        </td>
                        <td>{{ $intent->provider }}</td>
                        <td>{{ $intent->status }}</td>
                        <td>{{ $intent->out_sum }}</td>
                        <td>{{ $intent->payment_date }}</td>
                        <td>{{ optional($intent->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td>{{ optional($intent->paid_at)->format('Y-m-d H:i:s') }}</td>
                        <td class="small" style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $intent->meta }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-muted">Нет данных</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $intents->links() }}
        </div>
    </div>

@endsection


