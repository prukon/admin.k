@php
    use App\Services\Contracts\ContractTemplateVariablePresets;

    $fieldGroups = $fieldGroups ?? ContractTemplateVariablePresets::groupFieldsForParentForm($fields ?? []);
    $parentFields = $fieldGroups[ContractTemplateVariablePresets::GROUP_PARENT] ?? [];
    $childFields = $fieldGroups[ContractTemplateVariablePresets::GROUP_CHILD] ?? [];
    $fillMode = $fillMode ?? 'default';
    $showFillForm = $contract->canClientFill()
        || ($fillMode === 'edit' && ($contract->canClientEditFilledData() || $contract->isGeneratingPdf()));
@endphp

@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!empty($generationError))
    <div class="alert alert-danger mb-3">{{ $generationError }}</div>
@endif

@if($contract->isGeneratingPdf())
    <div class="text-center py-4" data-contract-fill-poll="1">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <p class="mb-0">Формируем договор… Обычно это занимает до 30 секунд.</p>
        <p class="text-muted small mb-0 mt-2">Окно обновится автоматически.</p>
    </div>
@endif

@if($showFillForm && !$contract->isGeneratingPdf())
    <form method="post" action="{{ route('account.documents.generate', $contract) }}" class="contract-fill-form">
        @csrf

        @if($fillMode === 'edit')
            <div class="alert alert-warning mb-3">
                PDF будет пересоздан с новыми данными. Проверьте поля перед сохранением.
            </div>
        @endif

        @if($parentFields !== [])
            <section class="contract-fill-panel contract-fill-panel--parent mb-3">
                <div class="contract-fill-panel__head">
                    <span class="contract-fill-panel__icon" aria-hidden="true">
                        <i class="fas fa-user"></i>
                    </span>
                    <span class="contract-fill-panel__title">Родитель</span>
                    <span class="contract-fill-panel__hint text-muted">Заказчик по договору</span>
                </div>
                <div class="contract-fill-panel__body">
                    <div class="row g-3">
                        @foreach($parentFields as $field)
                            @include('account.partials.contract-fill-field', [
                                'field' => $field,
                                'fieldGroup' => ContractTemplateVariablePresets::GROUP_PARENT,
                                'prefill' => $prefill,
                                'prefillSources' => $prefillSources,
                                'showContractFieldKeys' => $showContractFieldKeys ?? false,
                            ])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($childFields !== [])
            <section class="contract-fill-panel contract-fill-panel--child mb-3">
                <div class="contract-fill-panel__head">
                    <span class="contract-fill-panel__icon" aria-hidden="true">
                        <i class="fas fa-child"></i>
                    </span>
                    <span class="contract-fill-panel__title">Ребёнок</span>
                    <span class="contract-fill-panel__hint text-muted">Ученик</span>
                </div>
                <div class="contract-fill-panel__body">
                    <div class="row g-3">
                        @foreach($childFields as $field)
                            @include('account.partials.contract-fill-field', [
                                'field' => $field,
                                'fieldGroup' => ContractTemplateVariablePresets::GROUP_CHILD,
                                'prefill' => $prefill,
                                'prefillSources' => $prefillSources,
                                'showContractFieldKeys' => $showContractFieldKeys ?? false,
                            ])
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div class="d-flex flex-wrap gap-2 pt-1">
            @if($fillMode === 'edit')
                <button type="submit" class="btn btn-primary">Сохранить и обновить PDF</button>
            @else
                <button type="submit" class="btn btn-primary">Сформировать договор</button>
            @endif
        </div>
    </form>
@endif

@if($contract->canClientSign() && $fillMode !== 'edit')
    <div class="contract-fill-sign-block {{ $contract->canClientFill() ? 'mt-4 pt-3 border-top' : '' }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h6 class="mb-0">Договор сформирован</h6>
            <a class="btn btn-outline-primary btn-sm"
               href="{{ route('account.documents.downloadOriginal', $contract) }}"
               target="_blank" rel="noopener">
                <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Скачать PDF
            </a>
        </div>

        <form method="post" action="{{ route('account.documents.sign', $contract) }}">
            @csrf
            <section class="contract-fill-panel contract-fill-panel--parent mb-3">
                <div class="contract-fill-panel__head">
                    <span class="contract-fill-panel__icon" aria-hidden="true">
                        <i class="fas fa-signature"></i>
                    </span>
                    <span class="contract-fill-panel__title">Родитель</span>
                    <span class="contract-fill-panel__hint text-muted">подписание по SMS</span>
                </div>
                <div class="contract-fill-panel__body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 text-start">
                            <label class="form-label mb-1 text-start w-100">Фамилия <span class="text-danger">*</span></label>
                            <input type="text" name="signer_lastname" class="form-control @error('signer_lastname') is-invalid @enderror"
                                   value="{{ old('signer_lastname', $signerDefaults['lastname']) }}" required maxlength="100">
                            @error('signer_lastname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6 text-start">
                            <label class="form-label mb-1 text-start w-100">Имя <span class="text-danger">*</span></label>
                            <input type="text" name="signer_firstname" class="form-control @error('signer_firstname') is-invalid @enderror"
                                   value="{{ old('signer_firstname', $signerDefaults['firstname']) }}" required maxlength="100">
                            @error('signer_firstname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6 text-start">
                            <label class="form-label mb-1 text-start w-100">Отчество</label>
                            <input type="text" name="signer_middlename" class="form-control @error('signer_middlename') is-invalid @enderror"
                                   value="{{ old('signer_middlename', $signerDefaults['middlename']) }}" maxlength="100">
                            @error('signer_middlename')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6 text-start">
                            <label class="form-label mb-1 text-start w-100">Телефон для SMS <span class="text-danger">*</span></label>
                            @include('includes.fields.phone-input', [
                                'name' => 'signer_phone',
                                'value' => old('signer_phone', $signerDefaults['phone']),
                                'unmask' => true,
                                'contractFill' => true,
                                'required' => true,
                                'class' => trim(($errors->has('signer_phone') ? 'is-invalid' : '')),
                            ])
                            @error('signer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </section>
            <button type="submit" class="btn btn-success">Подписать договор (отправить SMS)</button>
        </form>
    </div>
@endif

@if(in_array($contract->status, [\App\Models\Contract::STATUS_SENT, \App\Models\Contract::STATUS_OPENED], true))
    <div class="alert alert-info mb-0 mt-3">
        SMS отправлена. Откройте ссылку из сообщения и завершите подписание в сервисе Подпислон.
    </div>
@endif
