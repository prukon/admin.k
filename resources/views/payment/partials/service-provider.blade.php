@if(!empty($showTbankLegalEntityBlock))
        <div class="payment-service-provider@if(!empty($serviceProviderTeamTitle)) payment-service-provider--with-team@endif"@if(!empty($serviceProviderRowId)) id="{{ $serviceProviderRowId }}"@endif @if(!empty($serviceProviderRowHidden)) style="display:none"@endif>
        @if(!empty($serviceProviderTeamTitle))
            <div class="payment-service-provider-team mb-2">
                <div class="summary-item-label">Группа</div>
                <div class="summary-item-value">{{ $serviceProviderTeamTitle }}</div>
            </div>
        @endif
        <div class="summary-item-label">Поставщик услуг</div>
        @if(!empty($serviceProviderLabel))
            <div @if(!empty($serviceProviderValueId)) id="{{ $serviceProviderValueId }}" @endif class="summary-item-value">{{ $serviceProviderLabel }}</div>
        @else
            <div @if(!empty($serviceProviderValueId)) id="{{ $serviceProviderValueId }}" @endif class="summary-item-value text-danger">{{ $serviceProviderFallbackText ?? 'Обратитесь в школу.' }}</div>
        @endif
    </div>
@endif
