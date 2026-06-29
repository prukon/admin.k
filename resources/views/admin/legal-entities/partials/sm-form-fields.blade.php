@php
    /** @var \App\Models\PartnerLegalEntity $entity */
    /** @var \App\Models\Partner $partner */
    $businessTypes = \App\Enums\PartnerLegalEntityBusinessType::cases();
    $bt = $entity->business_type instanceof \App\Enums\PartnerLegalEntityBusinessType
        ? $entity->business_type->value
        : (string) $entity->business_type;
    $orgName = old('organization_name', $entity->organization_name ?: $entity->title);
    $isRegistered = trim((string) ($entity->tinkoff_shop_code ?? '')) !== '';
    $smDetailsDefault = 'Выплата по договору, НДС не облагается';

    $ceoData = is_array($entity->ceo ?? null) ? $entity->ceo : [];
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
    $ceoReadonlyAttr = $isRegistered ? 'readonly' : '';
@endphp

<div class="row g-3">
    <div class="col-lg-3 col-md-6">
        <label class="form-label">Форма организации*</label>
        <select name="business_type" class="form-select js-legal-entity-business-type" required>
            @foreach ($businessTypes as $type)
                <option value="{{ $type->value }}" @selected($bt === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'type'])
        <div class="invalid-feedback d-block" data-error-for="business_type"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">Наименование*</label>
        <input name="title" class="form-control" required value="{{ old('title', $entity->title) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'name'])
        <div class="invalid-feedback d-block" data-error-for="title"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">Наименование организации*</label>
        <input name="organization_name" class="form-control" required value="{{ $orgName }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'fullName'])
        <div class="invalid-feedback d-block" data-error-for="organization_name"></div>
    </div>

    <div class="col-lg-3 col-md-6">
        <label class="form-label">E-mail*</label>
        <input name="email" class="form-control" type="email" required value="{{ old('email', $partner->email) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'email'])
        <div class="invalid-feedback d-block" data-error-for="email"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ИНН*</label>
        <input name="tax_id" class="form-control" required value="{{ old('tax_id', $entity->tax_id) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'inn'])
        <div class="invalid-feedback d-block" data-error-for="tax_id"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">ОГРН/ОГРНИП*</label>
        <input name="registration_number" class="form-control" required value="{{ old('registration_number', $entity->registration_number) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ogrn'])
        <div class="invalid-feedback d-block" data-error-for="registration_number"></div>
    </div>

    <div class="col-md-4 js-kpp-field">
        <label class="form-label">КПП</label>
        <input name="kpp" class="form-control js-kpp-input" value="{{ old('kpp', $entity->kpp) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'kpp'])
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
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'phones[].phone'])
        <div class="invalid-feedback d-block" data-error-for="phone"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Сайт</label>
        <input name="website" class="form-control" type="url" value="{{ old('website', $partner->website ?? config('app.url')) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'siteUrl'])
        <div class="invalid-feedback d-block" data-error-for="website"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Название для SMS/выписок</label>
        <input class="form-control bg-light" value="{{ $partner->sms_name ?: '—' }}" readonly>
        <div class="form-text">Редактируется в <a href="{{ route('admin.cur.company.edit') }}">учётной записи → Организация</a>.</div>
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'billingDescriptor'])
    </div>

    <div class="col-md-2">
        <label class="form-label">Город*</label>
        <input name="city" class="form-control" required value="{{ old('city', $entity->city) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'addresses[].city'])
        <div class="invalid-feedback d-block" data-error-for="city"></div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Индекс*</label>
        <input name="zip" class="form-control" required pattern="\d{6}" maxlength="6" value="{{ old('zip', $entity->zip) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'addresses[].zip'])
        <div class="invalid-feedback d-block" data-error-for="zip"></div>
    </div>

    <div class="col-md-8">
        <label class="form-label">Улица, дом, офис*</label>
        <input name="address" class="form-control" required value="{{ old('address', $entity->address) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'addresses[].street'])
        <div class="invalid-feedback d-block" data-error-for="address"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Страна (адрес)</label>
        <input class="form-control bg-light" value="RUS" readonly aria-readonly="true">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'addresses[].country'])
    </div>

    <div class="col-md-4">
        <label class="form-label">Банк*</label>
        <input name="bank_name" class="form-control" required value="{{ old('bank_name', $entity->bank_name) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'bankAccount.bankName'])
        <div class="invalid-feedback d-block" data-error-for="bank_name"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">БИК*</label>
        <input name="bank_bik" class="form-control" required value="{{ old('bank_bik', $entity->bank_bik) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'bankAccount.bik'])
        <div class="invalid-feedback d-block" data-error-for="bank_bik"></div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Расчётный счёт*</label>
        <input name="bank_account" class="form-control" required value="{{ old('bank_account', $entity->bank_account) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'bankAccount.account'])
        <div class="invalid-feedback d-block" data-error-for="bank_account"></div>
    </div>

    <div class="col-12">
        <label class="form-label">Назначение платежа</label>
        <input type="text"
               class="form-control bg-light"
               value="{{ $smDetailsDefault }}"
               disabled
               aria-disabled="true">
        <input type="hidden"
               name="sm_details_template"
               value="{{ old('sm_details_template', $entity->sm_details_template ?: $smDetailsDefault) }}">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'bankAccount.details'])
        <div class="invalid-feedback d-block" data-error-for="sm_details_template"></div>
    </div>

    <div class="col-12">
        <h6 class="mb-0 mt-1">Руководитель / ИП (ceo)</h6>
        @if ($isRegistered)
            <div class="form-text">При обновлении в sm-register блок ceo в API не передаётся — данные только для просмотра.</div>
        @endif
    </div>

    <div class="col-md-3">
        <label class="form-label">Фамилия{{ $isRegistered ? '' : '*' }}</label>
        <input name="ceo[lastName]" class="form-control" maxlength="100" value="{{ $ceoField('lastName') }}" {{ $ceoReadonlyAttr }} @disabled($isRegistered) @required(!$isRegistered)>
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ceo.lastName'])
        <div class="invalid-feedback d-block" data-error-for="ceo.lastName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Имя{{ $isRegistered ? '' : '*' }}</label>
        <input name="ceo[firstName]" class="form-control" maxlength="100" value="{{ $ceoField('firstName') }}" {{ $ceoReadonlyAttr }} @disabled($isRegistered) @required(!$isRegistered)>
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ceo.firstName'])
        <div class="invalid-feedback d-block" data-error-for="ceo.firstName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Отчество</label>
        <input name="ceo[middleName]" class="form-control" maxlength="100" value="{{ $ceoField('middleName') }}" {{ $ceoReadonlyAttr }} @disabled($isRegistered)>
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ceo.middleName'])
        <div class="invalid-feedback d-block" data-error-for="ceo.middleName"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Телефон руководителя</label>
        @include('includes.fields.phone-input', [
            'name' => 'ceo[phone]',
            'id' => 'legal_entity_sm_ceo_phone',
            'value' => $ceoField('phone'),
            'class' => $isRegistered ? 'bg-light' : '',
            'readonly' => $isRegistered,
            'disabled' => $isRegistered,
        ])
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ceo.phone'])
        <div class="invalid-feedback d-block" data-error-for="ceo.phone"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Страна</label>
        <input class="form-control bg-light" value="RUS" readonly aria-readonly="true">
        @include('admin.legal-entities.partials.sm-api-field-hint', ['code' => 'ceo.country'])
    </div>
</div>

<div class="alert alert-danger d-none mt-3" id="legalEntitySmError"></div>
