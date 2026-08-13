@php
    $fieldPrefix = match ($prefix ?? 'edit') {
        'create' => 'create-',
        default  => 'edit-',
    };
    $canDiscount = (bool) ($canManageUserDiscount ?? (auth()->user()?->can('users.discount.manage') ?? false));
    $studentOnlyHidden = ($prefix ?? 'edit') === 'create' ? '' : 'd-none';
@endphp

@if ($canDiscount)
    <div class="col-12 js-user-discount-wrap {{ $studentOnlyHidden }}" data-discount-prefix="{{ $prefix ?? 'edit' }}">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="{{ $fieldPrefix }}discount_percent" class="form-label">Скидка, %</label>
                <input type="number"
                       id="{{ $fieldPrefix }}discount_percent"
                       name="discount_percent"
                       class="form-control js-user-discount-percent"
                       min="0"
                       max="100"
                       step="1"
                       inputmode="numeric"
                       value="">
                <div class="invalid-feedback" data-error-for="discount_percent"></div>
            </div>
            <div class="col-12 col-md-8">
                <label for="{{ $fieldPrefix }}discount_comment" class="form-label">
                    Основание скидки
                    <span class="text-danger js-user-discount-comment-required d-none">*</span>
                </label>
                <textarea id="{{ $fieldPrefix }}discount_comment"
                          name="discount_comment"
                          class="form-control js-user-discount-comment"
                          rows="2"
                          maxlength="500"></textarea>
                <div class="form-text">Обязательно, если указана скидка больше 0%.</div>
                <div class="invalid-feedback" data-error-for="discount_comment"></div>
            </div>
        </div>
    </div>
@endif
