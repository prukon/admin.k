{{-- Ячейка «Партнёр» в таблице комиссий Т-Банк (DataTables / серверный HTML) --}}
@if($r->partner_id)
    @php
        $pid = (int) $r->partner_id;
        $auto = !empty($autoPayoutByPartnerId) ? (bool) ($autoPayoutByPartnerId[$pid] ?? false) : false;
        $conn = !empty($tbankConnectedByPartnerId) ? (bool) ($tbankConnectedByPartnerId[$pid] ?? false) : false;
        $stats = ($autoPayoutStatsByPartnerId ?? collect())->get($pid);
    @endphp
    <div>{{ optional($r->partner)->title ?? ('#'.$pid) }}</div>
    <div class="small">
        <span class="text-muted">Автовыплата:</span>
        @if($auto)
            <span class="badge text-bg-success">on</span>
        @else
            <span class="badge text-bg-secondary">off</span>
        @endif
        @if(!$conn)
            <span class="badge text-bg-warning">ключи?</span>
        @endif
    </div>
    <div class="small text-muted mt-1">
        За 30 дн.: {{ $stats['count'] ?? 0 }} автовыплат
        @if(!empty($stats['last_at']))
            , последняя {{ $stats['last_at']->format('d.m.Y H:i') }}
        @endif
        <a href="{{ url('/admin/tinkoff/payouts?partner_id=' . $pid . '&source=auto') }}" class="ms-1" target="_blank" title="К выплатам (авто)">→</a>
    </div>
@else
    — (глобально)
@endif
