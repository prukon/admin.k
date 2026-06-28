@php
    /** @var array{income: array, return: array} $fiscalReceipts */
    $incomeReceipt = $fiscalReceipts['income'] ?? [];
    $returnReceipt = $fiscalReceipts['return'] ?? [];
    $showReturnReceipt = ! empty($returnReceipt['has_url'])
        || (($returnReceipt['status'] ?? null) !== null);
@endphp

<div class="mt-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span>Фискальный чек:</span>
        @if(! empty($incomeReceipt['has_url']))
            <a href="{{ $incomeReceipt['url'] }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none" title="{{ $incomeReceipt['hint'] ?? 'Чек сформирован' }}">
                <i class="fas fa-receipt text-primary"></i>
                <span class="ms-1">Открыть чек</span>
            </a>
        @else
            <span class="text-muted" title="{{ $incomeReceipt['hint'] ?? 'Чек не сформирован' }}">
                <i class="fas fa-receipt text-secondary"></i>
                <span class="ms-1">{{ $incomeReceipt['hint'] ?? 'Чек не сформирован' }}</span>
            </span>
        @endif
        @if(! empty($incomeReceipt['receipt_datetime']))
            <span class="small text-muted">{{ $incomeReceipt['receipt_datetime']->format('d.m.Y H:i') }}</span>
        @endif
    </div>

    @if($showReturnReceipt)
        <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
            <span>Чек возврата:</span>
            @if(! empty($returnReceipt['has_url']))
                <a href="{{ $returnReceipt['url'] }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none return-receipt-link" title="{{ $returnReceipt['hint'] ?? 'Чек возврата' }}">
                    <i class="fas fa-receipt return-receipt-icon"></i>
                    <span class="ms-1">Открыть чек возврата</span>
                </a>
            @else
                <span class="text-muted" title="{{ $returnReceipt['hint'] ?? 'Чек возврата не создан' }}">
                    <i class="fas fa-receipt text-secondary"></i>
                    <span class="ms-1">{{ $returnReceipt['hint'] ?? 'Чек возврата не создан' }}</span>
                </span>
            @endif
            @if(! empty($returnReceipt['receipt_datetime']))
                <span class="small text-muted">{{ $returnReceipt['receipt_datetime']->format('d.m.Y H:i') }}</span>
            @endif
        </div>
    @endif
</div>
