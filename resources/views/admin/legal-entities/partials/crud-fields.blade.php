@php
    /** @var string $prefix */
    /** @var \App\Models\PartnerLegalEntity|null $entity */
    $entity = $entity ?? null;
    $businessTypes = \App\Enums\PartnerLegalEntityBusinessType::cases();
    $field = function (string $name, mixed $default = '') use ($entity) {
        if (isset($entity)) {
            $value = $entity->{$name} ?? $default;
            if ($value instanceof \App\Enums\PartnerLegalEntityBusinessType) {
                $value = $value->value;
            }

            return old($name, $value);
        }

        return old($name, $default);
    };
    $selectedBusinessType = isset($entity)
        ? ($entity->business_type instanceof \App\Enums\PartnerLegalEntityBusinessType
            ? $entity->business_type->value
            : (string) $entity->business_type)
        : 'OOO';

    $ceoData = [];
    if (isset($entity) && is_array($entity->ceo ?? null)) {
        $ceoData = $entity->ceo;
    }
    $ceoField = function (string $key) use ($ceoData): string {
        $aliases = match ($key) {
            'lastName' => ['lastName', 'last_name'],
            'firstName' => ['firstName', 'first_name'],
            'middleName' => ['middleName', 'middle_name'],
            default => [$key],
        };

        foreach ($aliases as $alias) {
            $old = old('ceo.' . $alias);
            if ($old !== null) {
                return (string) $old;
            }
            if (array_key_exists($alias, $ceoData)) {
                return (string) $ceoData[$alias];
            }
        }

        return (string) old('ceo.' . $key, '');
    };
    $smDetailsDefault = 'Выплата по договору, НДС не облагается';
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Форма организации*</label>
        <select name="business_type" class="form-select js-legal-entity-business-type js-legal-entity-sm-locked" required>
            @foreach ($businessTypes as $type)
                <option value="{{ $type->value }}" @selected((string) $field('business_type', $selectedBusinessType) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback d-block" data-error-for="business_type"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Наименование организации*</label>
        <input class="form-control js-legal-entity-sm-locked" name="organization_name" placeholder="ИП Иванов Иван..." value="{{ $field('organization_name') }}" required />
        <div class="invalid-feedback d-block" data-error-for="organization_name"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">ИНН</label>
        <input class="form-control js-legal-entity-sm-locked" name="tax_id" maxlength="12" value="{{ $field('tax_id') }}" />
        <div class="invalid-feedback d-block" data-error-for="tax_id"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">ОГРН/ОГРНИП</label>
        <input class="form-control js-legal-entity-sm-locked" name="registration_number" maxlength="20" value="{{ $field('registration_number') }}" />
        <div class="invalid-feedback d-block" data-error-for="registration_number"></div>
    </div>

    <div class="col-md-3 js-kpp-field">
        <label class="form-label">КПП</label>
        <input class="form-control js-legal-entity-sm-locked" name="kpp" maxlength="9" value="{{ $field('kpp') }}" />
        <div class="invalid-feedback d-block" data-error-for="kpp"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Город</label>
        <input class="form-control js-legal-entity-sm-locked" name="city" value="{{ $field('city') }}" />
        <div class="invalid-feedback d-block" data-error-for="city"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Индекс</label>
        <input class="form-control js-legal-entity-sm-locked" name="zip" maxlength="6" pattern="\d{6}" value="{{ $field('zip') }}" />
        <div class="invalid-feedback d-block" data-error-for="zip"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Адрес</label>
        <input class="form-control js-legal-entity-sm-locked" name="address" value="{{ $field('address') }}" />
        <div class="invalid-feedback d-block" data-error-for="address"></div>
    </div>

    <div class="col-12">
        <h6 class="mb-0 mt-1">Реквизиты для банка</h6>
    </div>

    <div class="col-md-3">
        <label class="form-label">Наименование банка</label>
        <input class="form-control js-legal-entity-sm-locked" name="bank_name" maxlength="255" value="{{ $field('bank_name') }}" />
        <div class="invalid-feedback d-block" data-error-for="bank_name"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">БИК</label>
        <input class="form-control js-legal-entity-sm-locked" name="bank_bik" maxlength="20" value="{{ $field('bank_bik') }}" />
        <div class="invalid-feedback d-block" data-error-for="bank_bik"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Расчётный счёт</label>
        <input class="form-control js-legal-entity-sm-locked" name="bank_account" maxlength="32" value="{{ $field('bank_account') }}" />
        <div class="invalid-feedback d-block" data-error-for="bank_account"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ставка НДС (онлайн-чек)</label>
        <select name="vat" class="form-select">
            <option value="" @selected($field('vat') === '' || $field('vat') === null)>НДС не облагается</option>
            @foreach (\App\Enums\CloudKassirVatRate::selectOptions() as $vatOption)
                <option value="{{ $vatOption['value'] }}" @selected((string) $field('vat') === (string) $vatOption['value'])>{{ $vatOption['label'] }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback d-block" data-error-for="vat"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Фамилия</label>
        <input class="form-control js-legal-entity-sm-locked" name="ceo[lastName]" maxlength="100" value="{{ $ceoField('lastName') }}" />
        <div class="invalid-feedback d-block" data-error-for="ceo.lastName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Имя</label>
        <input class="form-control js-legal-entity-sm-locked" name="ceo[firstName]" maxlength="100" value="{{ $ceoField('firstName') }}" />
        <div class="invalid-feedback d-block" data-error-for="ceo.firstName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Отчество</label>
        <input class="form-control js-legal-entity-sm-locked" name="ceo[middleName]" maxlength="100" value="{{ $ceoField('middleName') }}" />
        <div class="invalid-feedback d-block" data-error-for="ceo.middleName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Телефон руководителя</label>
        @include('includes.fields.phone-input', [
            'name' => 'ceo[phone]',
            'id' => 'legal_entity_' . $prefix . '_ceo_phone',
            'value' => $ceoField('phone'),
            'class' => 'js-legal-entity-sm-locked',
        ])
        <div class="invalid-feedback d-block" data-error-for="ceo.phone"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Основное юр. лицо</label>
        <select class="form-select" name="is_default">
            <option value="0" @selected((int) $field('is_default', 0) === 0)>Нет</option>
            <option value="1" @selected((int) $field('is_default', 0) === 1)>Да</option>
        </select>
        <div class="invalid-feedback d-block" data-error-for="is_default"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Активен</label>
        <select class="form-select" name="is_enabled">
            <option value="1" @selected((int) $field('is_enabled', 1) === 1)>Да</option>
            <option value="0" @selected((int) $field('is_enabled', 1) === 0)>Нет</option>
        </select>
        <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
    </div>

    <input type="hidden"
           name="sm_details_template"
           class="js-legal-entity-sm-details-value"
           value="{{ trim((string) $field('sm_details_template', $smDetailsDefault)) !== '' ? $field('sm_details_template', $smDetailsDefault) : $smDetailsDefault }}">

    <div class="col-12">
        <div class="alert alert-info d-none py-2 mb-0 js-legal-entity-registered-hint" role="status">
            После регистрации в T‑Bank реквизиты и данные руководителя меняются на карточке через «Обновить в sm-register».
        </div>
    </div>
</div>
