@php
    $contractTemplates = $contractTemplates ?? collect();
    $contractCreateFee = (float) (config('billing.contract_create_fee') ?? 70);
    $contractCreateFeeLabel = rtrim(rtrim(number_format($contractCreateFee, 2, ',', ' '), '0'), ',') . ' ₽';
    $singleContractTemplate = $contractTemplates->count() === 1 ? $contractTemplates->first() : null;
@endphp

<div class="modal fade"
     id="createLeadClientChoiceModal"
     tabindex="-1"
     aria-labelledby="createLeadClientChoiceModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createLeadClientChoiceModalLabel">Создание клиента</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body text-start">
                <div class="field-error-msg text-danger small mb-2 d-none" data-field-error="send_contract"></div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="radio"
                               name="lead_create_client_mode"
                               id="leadCreateClientModeWithContract"
                               value="with_contract"
                               checked>
                        <label class="form-check-label" for="leadCreateClientModeWithContract">
                            Создать клиента и отправить договор
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="radio"
                               name="lead_create_client_mode"
                               id="leadCreateClientModeWithoutContract"
                               value="without_contract">
                        <label class="form-check-label" for="leadCreateClientModeWithoutContract">
                            Создать клиента без договора
                        </label>
                    </div>
                </div>

                <div id="leadCreateClientContractFields">
                    <div class="mb-3">
                        <label class="form-label" for="leadCreateClientTemplateId">Шаблон договора</label>
                        @if ($contractTemplates->isEmpty())
                            <div class="alert alert-warning mb-0" role="alert">
                                Шаблонов нет.
                                <a href="{{ route('contract-templates.index', ['create' => 1]) }}" class="alert-link">Создать шаблон</a>
                            </div>
                        @else
                            <select id="leadCreateClientTemplateId" class="form-select">
                                @unless($singleContractTemplate)
                                    <option value="">— выберите шаблон —</option>
                                @endunless
                                @foreach ($contractTemplates as $tpl)
                                    <option value="{{ $tpl->id }}"
                                        @selected($singleContractTemplate && (int) $singleContractTemplate->id === (int) $tpl->id)>
                                        {{ $tpl->title }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <div class="field-error-msg text-danger small mt-1"
                             data-field-error="contract_template_id"></div>
                    </div>

                    <div class="alert alert-warning mb-0" role="alert">
                        Это действие платное: с баланса будет списано {{ $contractCreateFeeLabel }}.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="createLeadClientChoiceOkBtn">Ок</button>
            </div>
        </div>
    </div>
</div>
