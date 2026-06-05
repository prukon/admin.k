@php
    use App\Services\Contracts\ContractTemplateVariablePresets;

    $key = $field['key'];
    $prefillKey = $field['prefill_source'] ?? null;
    $label = $field['label'] ?? $key;
    if (is_string($prefillKey) && $prefillKey !== '' && isset($prefillSources[$prefillKey])) {
        $label = $prefillSources[$prefillKey];
    }
    $label = ContractTemplateVariablePresets::fillFormFieldLabel($label, $fieldGroup);
    $required = !empty($field['required']);
    $rawValue = old('fields.' . $key, $prefill[$key] ?? '');
    $isPhoneField = str_contains($key, 'phone')
        || str_contains($key, 'tel')
        || str_contains($key, 'mobile');
    $isDateField = ContractTemplateVariablePresets::isFillFormDateField($key);
    $value = $isDateField
        ? ContractTemplateVariablePresets::dateValueForFillInput($rawValue)
        : $rawValue;
@endphp
<div class="col-12 col-md-6 text-start">
    <label class="form-label mb-1 text-start w-100">
        {{ $label }}
        @if($required)<span class="text-danger">*</span>@endif
    </label>
    @if(!empty($showContractFieldKeys))
        <div class="form-text mb-1"><code>&#123;&#123;{{ $key }}&#125;&#125;</code></div>
    @endif
    @if($isDateField)
        <input type="date"
               name="fields[{{ $key }}]"
               class="form-control @error('fields.' . $key) is-invalid @enderror"
               value="{{ $value }}"
               max="{{ now()->format('Y-m-d') }}"
               {{ $required ? 'required' : '' }}>
    @elseif($isPhoneField)
        @include('includes.fields.phone-input', [
            'name' => 'fields[' . $key . ']',
            'value' => $rawValue,
            'unmask' => true,
            'contractFill' => true,
            'required' => $required,
            'class' => $errors->has('fields.' . $key) ? 'is-invalid' : '',
        ])
    @else
        <input type="text"
               name="fields[{{ $key }}]"
               class="form-control @error('fields.' . $key) is-invalid @enderror"
               value="{{ $value }}"
               {{ $required ? 'required' : '' }}
               maxlength="2000">
    @endif
    @error('fields.' . $key)
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
