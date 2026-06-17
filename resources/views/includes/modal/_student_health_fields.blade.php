@php
    $fieldPrefix = match ($prefix ?? 'edit') {
        'create' => 'create-',
        'lead'   => 'lead-',
        default  => 'edit-',
    };
    $variant = $variant ?? 'select';
    $isLeadCheckbox = $variant === 'checkbox' && ($prefix ?? 'edit') === 'lead';
@endphp

@can('users.other.update')
    <div class="col-12 js-user-health-wrap {{ ($prefix ?? 'edit') === 'lead' ? '' : 'd-none' }}" data-health-prefix="{{ $prefix ?? 'edit' }}">
        <div class="mb-2 mt-1">
            <span class="form-label d-block mb-2">Сведения об ученике</span>
        </div>
        @if ($isLeadCheckbox)
            <div class="d-flex flex-column gap-2">
                <div class="form-check mb-0">
                    <input type="checkbox"
                           class="form-check-input js-user-health-field js-lead-health-checkbox"
                           id="{{ $fieldPrefix }}is_individual_traits"
                           name="is_individual_traits"
                           value="1">
                    <label class="form-check-label" for="{{ $fieldPrefix }}is_individual_traits">
                        Индивидуальные особенности воспитанника (физические, психологические)
                    </label>
                </div>
                <div class="form-check mb-0">
                    <input type="checkbox"
                           class="form-check-input js-user-health-field js-lead-health-checkbox"
                           id="{{ $fieldPrefix }}is_on_medical_register"
                           name="is_on_medical_register"
                           value="1">
                    <label class="form-check-label" for="{{ $fieldPrefix }}is_on_medical_register">
                        Состоит на учёте у медицинских специалистов
                    </label>
                </div>
                <div class="form-check mb-0">
                    <input type="checkbox"
                           class="form-check-input js-user-health-field js-lead-health-checkbox"
                           id="{{ $fieldPrefix }}is_with_disability"
                           name="is_with_disability"
                           value="1">
                    <label class="form-check-label" for="{{ $fieldPrefix }}is_with_disability">
                        Наличие инвалидности
                    </label>
                </div>
            </div>
        @else
            <div class="row g-3">
                <div class="col-12">
                    <label for="{{ $fieldPrefix }}is_individual_traits" class="form-label">
                        Индивидуальные особенности воспитанника (физические, психологические)
                    </label>
                    <select id="{{ $fieldPrefix }}is_individual_traits" name="is_individual_traits" class="form-select js-user-health-field">
                        <option value="">Не указано</option>
                        <option value="1">Да</option>
                        <option value="0">Нет</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="{{ $fieldPrefix }}is_on_medical_register" class="form-label">
                        Состоит на учёте у медицинских специалистов
                    </label>
                    <select id="{{ $fieldPrefix }}is_on_medical_register" name="is_on_medical_register" class="form-select js-user-health-field">
                        <option value="">Не указано</option>
                        <option value="1">Да</option>
                        <option value="0">Нет</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="{{ $fieldPrefix }}is_with_disability" class="form-label">Наличие инвалидности</label>
                    <select id="{{ $fieldPrefix }}is_with_disability" name="is_with_disability" class="form-select js-user-health-field">
                        <option value="">Не указано</option>
                        <option value="1">Да</option>
                        <option value="0">Нет</option>
                    </select>
                </div>
            </div>
        @endif
    </div>
@endcan
