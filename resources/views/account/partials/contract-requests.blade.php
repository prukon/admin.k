{{--<div class="table-responsive">--}}
{{--    <table class="table table-sm align-middle mb-0">--}}
{{--        <thead>--}}
{{--        <tr>--}}
{{--            <th>#</th>--}}
{{--            <th>Подписант</th>--}}
{{--            <th>Телефон</th>--}}
{{--            <th>Статус</th>--}}
{{--            <th>Создано</th>--}}
{{--        </tr>--}}
{{--        </thead>--}}
{{--        <tbody>--}}
{{--        @forelse($requests as $r)--}}
{{--            <tr>--}}
{{--                <td>{{ $r->id }}</td>--}}
{{--                <td>{{ $r->signer_name ?? '—' }}</td>--}}
{{--                <td>{{ $r->signer_phone }}</td>--}}
{{--                <td>--}}
{{--                    <span class="badge {{ $r->status_badge_class }}">{{ $r->status_ru }}</span>--}}
{{--                </td>--}}
{{--                <td>{{ $r->created_at?->format('d.m.Y H:i') }}</td>--}}
{{--            </tr>--}}
{{--        @empty--}}
{{--            <tr>--}}
{{--                <td colspan="5" class="text-center text-muted py-4">Пока нет отправок</td>--}}
{{--            </tr>--}}
{{--        @endforelse--}}
{{--        </tbody>--}}
{{--    </table>--}}
{{--</div>--}}
