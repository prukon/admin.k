@php
    /** @var \App\Models\PartnerLegalEntity $entity */
    /** @var \App\Models\Partner $partner */
    $businessTypes = \App\Enums\PartnerLegalEntityBusinessType::cases();
    $bt = $entity->business_type instanceof \App\Enums\PartnerLegalEntityBusinessType
        ? $entity->business_type->value
        : (string) $entity->business_type;
    $orgName = old('organization_name', $entity->organization_name ?: $entity->title);
    $isRegistered = trim((string) ($entity->tinkoff_shop_code ?? '')) !== '';
@endphp

<div class="row g-3">
    <div class="col-lg-3 col-md-6">
        <label class="form-label">Форма организации*</label>
        <select name="business_type" class="form-select js-legal-entity-business-type" required>
            @foreach ($businessTypes as $type)
                <option value="{{ $type->value }}" @selected($bt === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback d-block" data-error-for="business_type"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">Наименование*</label>
        <input name="title" class="form-control" required value="{{ old('title', $entity->title) }}">
        <div class="invalid-feedback d-block" data-error-for="title"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">Наименование организации*</label>
        <input name="organization_name" class="form-control" required value="{{ $orgName }}">
        <div class="invalid-feedback d-block" data-error-for="organization_name"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">E-mail*</label>
        <input name="email" class="form-control" type="email" required value="{{ old('email', $partner->email) }}">
        <div class="invalid-feedback d-block" data-error-for="email"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ИНН*</label>
        <input name="tax_id" class="form-control" required value="{{ old('tax_id', $entity->tax_id) }}">
        <div class="invalid-feedback d-block" data-error-for="tax_id"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ОГРН/ОГРНИП*</label>
        <input name="registration_number" class="form-control" required value="{{ old('registration_number', $entity->registration_number) }}">
        <div class="invalid-feedback d-block" data-error-for="registration_number"></div>
    </div>

    <div class="col-md-4 js-kpp-field">
        <label class="form-label">КПП</label>
        <input name="kpp" class="form-control js-kpp-input" value="{{ old('kpp', $entity->kpp) }}">
        <div class="form-text">Для форм кроме ООО — <code>000000000</code>.</div>
        <div class="invalid-feedback d-block" data-error-for="kpp"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Телефон контакта</label>
        @include('includes.fields.phone-input', [
            'name' => 'phone',
            'id' => 'legal_entity_phone',
            'value' => old('phone', $partner->phone),
        ])
        <div class="invalid-feedback d-block" data-error-for="phone"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Сайт</label>
        <input name="website" class="form-control" type="url" value="{{ old('website', $partner->website ?? config('app.url')) }}">
        <div class="invalid-feedback d-block" data-error-for="website"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Название для SMS/выписок</label>
        <input class="form-control" value="{{ $entity->sms_name ?? '—' }}" readonly>
    </div>

    <div class="col-md-2">
        <label class="form-label">Город*</label>
        <input name="city" class="form-control" required value="{{ old('city', $entity->city) }}">
        <div class="invalid-feedback d-block" data-error-for="city"></div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Индекс*</label>
        <input name="zip" class="form-control" required pattern="\d{6}" maxlength="6" value="{{ old('zip', $entity->zip) }}">
        <div class="invalid-feedback d-block" data-error-for="zip"></div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Улица, дом, офис*</label>
        <input name="address" class="form-control" required value="{{ old('address', $entity->address) }}">
        <div class="invalid-feedback d-block" data-error-for="address"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Банк*</label>
        <input name="bank_name" class="form-control" required value="{{ old('bank_name', $entity->bank_name) }}">
        <div class="invalid-feedback d-block" data-error-for="bank_name"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">БИК*</label>
        <input name="bank_bik" class="form-control" required value="{{ old('bank_bik', $entity->bank_bik) }}">
        <div class="invalid-feedback d-block" data-error-for="bank_bik"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Расчётный счёт*</label>
        <input name="bank_account" class="form-control" required value="{{ old('bank_account', $entity->bank_account) }}">
        <div class="invalid-feedback d-block" data-error-for="bank_account"></div>
    </div>

    <div class="col-12">
        <label class="form-label">Назначение платежа (details)*</label>
        <textarea name="sm_details_template" class="form-control" rows="2" required>{{ old('sm_details_template', $entity->sm_details_template ?? 'Выплата по договору, НДС не облагается') }}</textarea>
        <div class="invalid-feedback d-block" data-error-for="sm_details_template"></div>
    </div>
</div>

<div class="alert alert-danger d-none mt-3" id="legalEntitySmError"></div>
