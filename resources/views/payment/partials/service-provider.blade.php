@if(!empty($showTbankLegalEntityBlock))
    <div @if(!empty($serviceProviderRowId)) id="{{ $serviceProviderRowId }}" @endif @if(!empty($serviceProviderRowHidden)) style="display:none" @endif>
        <div class="summary-item-label">Поставщик услуг</div>
        @if(!empty($serviceProviderLabel))
            <div @if(!empty($serviceProviderValueId)) id="{{ $serviceProviderValueId }}" @endif class="summary-item-value">{{ $serviceProviderLabel }}</div>
        @else
            <div @if(!empty($serviceProviderValueId)) id="{{ $serviceProviderValueId }}" @endif class="summary-item-value text-danger">Обратитесь в школу.</div>
        @endif
    </div>
@endif
