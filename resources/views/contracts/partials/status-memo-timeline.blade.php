@php
    /** @var list<array{label: string, state: string, hint?: string}> $steps */
    $showArrows = $showArrows ?? true;
    $ariaLabel = $ariaLabel ?? 'Путь договора';
@endphp

<div class="contract-memo-timeline" role="list" aria-label="{{ $ariaLabel }}">
    @foreach ($steps as $index => $step)
        <div class="contract-memo-timeline__step contract-memo-timeline__step--{{ $step['state'] }}" role="listitem">
            <div class="contract-memo-timeline__num">{{ $index + 1 }}</div>
            <div class="contract-memo-timeline__label">{{ $step['label'] }}</div>
            @if (! empty($step['at']))
                <div class="contract-memo-timeline__time">{{ $step['at'] }}</div>
            @endif
            @if (! empty($step['hint']))
                <div class="contract-memo-timeline__hint">{{ $step['hint'] }}</div>
            @endif
        </div>
        @if ($showArrows && ! $loop->last)
            <div class="contract-memo-timeline__arrow" aria-hidden="true">→</div>
        @endif
    @endforeach
</div>
