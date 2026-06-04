@php
    /** @var string $fieldId */
    /** @var string $value */
@endphp
<textarea name="email_body_html"
          id="{{ $fieldId }}"
          class="form-control js-contract-template-email-body @error('email_body_html') is-invalid @enderror"
          rows="8">{{ $value }}</textarea>
