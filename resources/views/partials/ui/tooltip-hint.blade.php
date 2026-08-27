{{--
    Иконка-подсказка (Bootstrap Tooltip через KidsCrmTooltip).

    CSS: resources/css/kids-tooltip.css (layouts/admin2 через Vite).

    @include('partials.ui.tooltip-hint', [
        'title' => 'Текст подсказки',
        'placement' => 'top',
        'iconClass' => 'fa fa-info-circle',
        'wrapperClass' => 'ms-1',
    ])

    Обёртка disabled-кнопки (ховер на span: у .btn:disabled pointer-events: none):
    @include('partials.ui.tooltip-hint', [
        'title' => 'Текст подсказки',
        'wrapperClass' => 'kids-tooltip-hint--control',
        'innerHtml' => '',
    ])
    Пустой innerHtml — клон для JS; иначе trusted-разметка вместо иконки.
--}}
@php
    $hintTitle = trim((string) ($title ?? ''));
    $hintPlacementRaw = $placement ?? 'top';
    $hintPlacement = in_array($hintPlacementRaw, ['top', 'bottom', 'left', 'right'], true)
        ? $hintPlacementRaw
        : 'top';
    $hintIconClass = trim((string) ($iconClass ?? 'fa fa-info-circle'));
    $hintExtraClass = trim((string) ($wrapperClass ?? 'ms-1'));
    $hintClass = trim('kids-tooltip-hint d-inline-block '.$hintExtraClass);
    $hintHasInnerHtml = array_key_exists('innerHtml', get_defined_vars());
    $hintInnerHtml = $hintHasInnerHtml ? (string) $innerHtml : null;
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
        @if($hintHasInnerHtml)
            {!! $hintInnerHtml !!}
        @else
            <i class="{{ $hintIconClass }}" aria-hidden="true"></i>
        @endif
    </span>
@endif
