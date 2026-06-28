@php
    /** @var list<array{key: string, label: string, state: string, at: ?\Illuminate\Support\Carbon, hint: ?string}> $paymentTimeline */
@endphp

<style>
    .tbank-payment-timeline {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        gap: 0.35rem 0;
    }

    .tbank-payment-timeline__step {
        flex: 1 1 120px;
        min-width: 105px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 0.5rem;
        padding: 0.75rem 0.65rem;
        background: #f8f9fa;
        color: #6c757d;
        text-align: center;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .tbank-payment-timeline__step--done {
        background: #d1e7dd;
        border-color: #a3cfbb;
        color: #0f5132;
    }

    .tbank-payment-timeline__step--active {
        background: #cfe2ff;
        border-color: #9ec5fe;
        color: #084298;
    }

    .tbank-payment-timeline__step--failed {
        background: #f8d7da;
        border-color: #f1aeb5;
        color: #842029;
    }

    .tbank-payment-timeline__num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 50%;
        font-size: 0.8rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        background: rgba(255, 255, 255, 0.65);
    }

    .tbank-payment-timeline__label {
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1.25;
        margin-bottom: 0.25rem;
    }

    .tbank-payment-timeline__time {
        font-size: 0.75rem;
        font-weight: 500;
        opacity: 0.95;
    }

    .tbank-payment-timeline__hint {
        font-size: 0.7rem;
        margin-top: 0.25rem;
        opacity: 0.85;
        line-height: 1.25;
    }

    .tbank-payment-timeline__arrow {
        flex: 0 0 auto;
        align-self: center;
        color: #adb5bd;
        font-size: 1.25rem;
        line-height: 1;
        padding: 0 0.15rem;
        user-select: none;
    }

    .tbank-payment-timeline__hint a {
        color: inherit;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .tbank-payment-timeline__hint a:hover {
        opacity: 1;
    }

    @media (max-width: 767.98px) {
        .tbank-payment-timeline__arrow {
            display: none;
        }

        .tbank-payment-timeline__step {
            flex: 1 1 100%;
        }
    }
</style>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h6 class="mb-3">Ход платежа, чеков и выплаты</h6>
        <div class="tbank-payment-timeline" role="list" aria-label="Ход платежа, чеков и выплаты">
            @foreach ($paymentTimeline as $index => $step)
                <div class="tbank-payment-timeline__step tbank-payment-timeline__step--{{ $step['state'] }}" role="listitem">
                    <div class="tbank-payment-timeline__num">{{ $index + 1 }}</div>
                    <div class="tbank-payment-timeline__label">{{ $step['label'] }}</div>
                    @if (! empty($step['at']))
                        <div class="tbank-payment-timeline__time">{{ $step['at']->format('d.m.Y H:i') }}</div>
                    @else
                        <div class="tbank-payment-timeline__time text-muted">—</div>
                    @endif
                    @if (! empty($step['hint']))
                        <div class="tbank-payment-timeline__hint">
                            @if (! empty($step['url']))
                                <a href="{{ $step['url'] }}" target="_blank" rel="noopener noreferrer">{{ $step['hint'] }}</a>
                            @else
                                {{ $step['hint'] }}
                            @endif
                        </div>
                    @endif
                </div>
                @if (! $loop->last)
                    <div class="tbank-payment-timeline__arrow" aria-hidden="true">→</div>
                @endif
            @endforeach
        </div>
    </div>
</div>
