@php
    $items = $items ?? [];
    $variant = $variant ?? 'default';
    $showGroups = $showGroups ?? false;
@endphp
@if(count($items) > 0)
    @if($showGroups)
        <div class="trainer-workload-chips trainer-workload-chips--{{ $variant }}">
            @foreach($items as $item)
                <div class="trainer-workload-chip" title="{{ $item['team_title'] }}">
                    <span class="trainer-workload-chip__team">{{ $item['team_title'] }}</span>
                    <span class="trainer-workload-chip__count">{{ (int) $item['dates_count'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        @php
            $sum = array_sum(array_map(fn ($item) => (int) $item['dates_count'], $items));
        @endphp
        <span class="trainer-workload-sum-only trainer-workload-sum-only--{{ $variant }}">{{ $sum }}</span>
    @endif
@else
    <span class="trainer-workload-cell-empty" aria-hidden="true">—</span>
@endif
