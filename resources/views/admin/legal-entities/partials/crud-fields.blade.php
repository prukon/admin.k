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
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Форма организации*</label>
        <select name="business_type" class="form-select" required>
            @foreach ($businessTypes as $type)
                <option value="{{ $type->value }}" @selected((string) $field('business_type', $selectedBusinessType) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="business_type"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Наименование*</label>
        <input class="form-control" name="title" required value="{{ $field('title') }}" />
        <div class="invalid-feedback" data-error-for="title"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Наименование организации</label>
        <input class="form-control" name="organization_name" value="{{ $field('organization_name') }}" />
        <div class="invalid-feedback" data-error-for="organization_name"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ИНН</label>
        <input class="form-control" name="tax_id" maxlength="12" value="{{ $field('tax_id') }}" />
        <div class="invalid-feedback" data-error-for="tax_id"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">КПП</label>
        <input class="form-control" name="kpp" maxlength="9" value="{{ $field('kpp') }}" />
        <div class="invalid-feedback" data-error-for="kpp"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ОГРН/ОГРНИП</label>
        <input class="form-control" name="registration_number" maxlength="20" value="{{ $field('registration_number') }}" />
        <div class="invalid-feedback" data-error-for="registration_number"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Город</label>
        <input class="form-control" name="city" value="{{ $field('city') }}" />
        <div class="invalid-feedback" data-error-for="city"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Индекс</label>
        <input class="form-control" name="zip" maxlength="6" pattern="\d{6}" value="{{ $field('zip') }}" />
        <div class="invalid-feedback" data-error-for="zip"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Адрес</label>
        <input class="form-control" name="address" value="{{ $field('address') }}" />
        <div class="invalid-feedback" data-error-for="address"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label">СНО (для чеков)</label>
        <select name="taxation_system" class="form-select">
            <option value="">—</option>
            @foreach ([0 => 'ОСН', 1 => 'УСН доход', 2 => 'УСН доход − расход', 3 => 'ЕНВД', 4 => 'ЕСХН', 5 => 'Патент'] as $val => $label)
                <option value="{{ $val }}" @selected((string) $field('taxation_system') === (string) $val)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="taxation_system"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Ставка НДС (онлайн-чек)</label>
        <select name="vat" class="form-select">
            <option value="">Не облагается</option>
            @foreach ([0, 5, 7, 10, 20, 22, 105, 107, 110, 120, 122] as $val)
                <option value="{{ $val }}" @selected((string) $field('vat') === (string) $val)>{{ $val }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="vat"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Основное юр. лицо</label>
        <select class="form-select" name="is_default">
            <option value="0" @selected((int) $field('is_default', 0) === 0)>Нет</option>
            <option value="1" @selected((int) $field('is_default', 0) === 1)>Да</option>
        </select>
        <div class="invalid-feedback" data-error-for="is_default"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Активен</label>
        <select class="form-select" name="is_enabled">
            <option value="1" @selected((int) $field('is_enabled', 1) === 1)>Да</option>
            <option value="0" @selected((int) $field('is_enabled', 1) === 0)>Нет</option>
        </select>
        <div class="invalid-feedback" data-error-for="is_enabled"></div>
    </div>
</div>
