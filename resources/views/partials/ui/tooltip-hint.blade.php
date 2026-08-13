{{--
    Иконка-подсказка (Bootstrap Tooltip через KidsCrmTooltip).

    CSS: resources/css/kids-tooltip.css (layouts/admin2 через Vite).

    @include('partials.ui.tooltip-hint', [
        'title' => 'Текст подсказки',
        'placement' => 'top',
        'iconClass' => 'fa fa-info-circle',
        'wrapperClass' => 'ms-1',
    ])
--}}
@php
    $hintTitle = trim((string) ($title ?? ''));
    $hintPlacement = in_array(($placement ?? 'top'), ['top', 'bottom', 'left', 'right'], true)
        ? $placement
        : 'top';
    $hintIconClass = trim((string) ($iconClass ?? 'fa fa-info-circle'));
    $hintExtraClass = trim((string) ($wrapperClass ?? 'ms-1'));
    $hintClass = trim('kids-tooltip-hint d-inline-block '.$hintExtraClass);
@endphp

@if($hintTitle !== '')
    <span class="{{ $hintClass }}"
          tabindex="0"
          data-kids-tooltip-hint
          data-bs-toggle="tooltip"
          data-bs-placement="{{ $hintPlacement }}"
          data-bs-custom-class="ulp-assignment-paid-tooltip"
          title="{{ $hintTitle }}"
          aria-label="{{ $hintTitle }}">
        <i class="{{ $hintIconClass }}" aria-hidden="true"></i>
    </span>
@endif
