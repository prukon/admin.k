{{-- Ячейка «Партнёр»: название и при необходимости предупреждение о ключах (автовыплата и счётчик — отдельные колонки) --}}
@if($r->partner_id)
    @php
        $pid = (int) $r->partner_id;
        $conn = !empty($tbankConnectedByPartnerId) ? (bool) ($tbankConnectedByPartnerId[$pid] ?? false) : false;
    @endphp
    <div>{{ optional($r->partner)->title ?? ('#'.$pid) }}</div>
    @if(!$conn)
        <div class="small mt-1"><span class="badge text-bg-warning">ключи?</span></div>
    @endif
@else
    (глобально)
@endif
