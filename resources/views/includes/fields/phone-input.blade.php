@php
    use App\Support\RuPhone;

    $name = $name ?? 'phone';
    $id = $id ?? $name;
    $rawValue = $value ?? '';
    $displayValue = RuPhone::formatForInput($rawValue !== '' && $rawValue !== null ? $rawValue : null);
    if ($displayValue === '' && $rawValue !== '' && $rawValue !== null) {
        $displayValue = (string) $rawValue;
    }

    $classes = ['form-control', 'js-phone-mask'];
    if (!empty($unmask)) {
        $classes[] = 'js-phone-mask-unmask';
    }
    if (!empty($contractFill)) {
        $classes[] = 'js-contract-fill-phone';
    }
    if (!empty($parentPhone)) {
        $classes[] = 'js-parent-phone';
    }
    if (!empty($class)) {
        $classes[] = $class;
    }

    $placeholder = $placeholder ?? '+7 (___) ___-__-__';
    $autocomplete = $autocomplete ?? 'tel';
    $extraAttributes = $attributes ?? [];
@endphp
<input
    type="tel"
    name="{{ $name }}"
    id="{{ $id }}"
    class="{{ implode(' ', $classes) }}"
    value="{{ $displayValue }}"
    placeholder="{{ $placeholder }}"
    autocomplete="{{ $autocomplete }}"
    @if(!empty($required)) required @endif
    @if(!empty($disabled)) disabled aria-disabled="true" @endif
    @if(!empty($readonly)) readonly @endif
    @foreach($extraAttributes as $attrKey => $attrValue)
        {{ $attrKey }}="{{ $attrValue }}"
    @endforeach
>
