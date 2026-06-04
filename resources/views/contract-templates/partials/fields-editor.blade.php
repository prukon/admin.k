@php
    use App\Services\Contracts\ContractTemplateVariablePresets;

    /** @var array $fields */
    /** @var array<string, string> $prefillSources */
@endphp

<div class="card mt-3" id="fields-editor-card" style="{{ empty($fields) ? 'display:none' : '' }}">
    <div class="card-header">Поля шаблона (из DOCX)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 contract-template-fields-table" id="contract-template-fields-table">
                <colgroup>
                    <col class="contract-template-fields-col-key">
                    <col class="contract-template-fields-col-label">
                    @can('contracts.templates.fillSortOrder.edit')
                        <col class="contract-template-fields-col-sort">
                    @endcan
                    <col class="contract-template-fields-col-required">
                    <col class="contract-template-fields-col-prefill">
                </colgroup>
                <thead>
                <tr>
                    <th>Ключ</th>
                    <th>Подпись для формы</th>
                    @can('contracts.templates.fillSortOrder.edit')
                        <th class="text-center">
                            Порядок
                            @include('partials.ui.tooltip-hint', [
                                'title' => 'Порядок полей в форме заполнения договора в кабинете родителя. Меньше — выше. Рекомендуется: 10–50 основные данные, 200 супруг(а), 300 кастомные поля из DOCX.',
                                'placement' => 'top',
                            ])
                        </th>
                    @endcan
                    <th class="text-center">Обяз.</th>
                    <th>
                        Предзаполнение
                        @include('partials.ui.tooltip-hint', [
                            'title' => 'CRM — данные из карточки ученика или родителя. «Заполняет родитель» — только в форме договора. «Автоматически» — подставляется системой при формировании PDF.',
                            'placement' => 'left',
                        ])
                    </th>
                </tr>
                </thead>
                <tbody>
                @foreach($fields as $i => $field)
                    @php
                        $fieldKey = (string) ($field['key'] ?? '');
                        $field = ContractTemplateVariablePresets::applyDefaultFillSortOrder($field);
                        $fillMode = ContractTemplateVariablePresets::fillModeForKey($fieldKey);
                        $isSystemField = $fillMode === ContractTemplateVariablePresets::FILL_MODE_SYSTEM;
                        $isParentField = $fillMode === ContractTemplateVariablePresets::FILL_MODE_PARENT;
                        $adminHint = ContractTemplateVariablePresets::adminHintForKey($fieldKey)
                            ?? ($isParentField ? ContractTemplateVariablePresets::parentFormHintForKey($fieldKey) : null);
                        $fillSortOrder = (int) ($field['fill_sort_order'] ?? ContractTemplateVariablePresets::FILL_SORT_DEFAULT_CUSTOM);
                    @endphp
                    <tr>
                        <td class="contract-template-fields-key">
                            <code>{{ $fieldKey }}</code>
                            <input type="hidden" name="fields[{{ $i }}][key]" value="{{ $fieldKey }}">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm"
                                   name="fields[{{ $i }}][label]"
                                   value="{{ $field['label'] ?? $fieldKey }}">
                        </td>
                        @can('contracts.templates.fillSortOrder.edit')
                            <td class="text-center contract-template-fields-sort">
                                @if($isSystemField)
                                    <span class="text-muted small" title="Не применимо">—</span>
                                @else
                                    <input type="number"
                                           class="form-control form-control-sm text-center"
                                           name="fields[{{ $i }}][fill_sort_order]"
                                           value="{{ old('fields.' . $i . '.fill_sort_order', $fillSortOrder) }}"
                                           min="0"
                                           max="9999"
                                           step="1"
                                           inputmode="numeric">
                                @endif
                            </td>
                        @endcan
                        <td class="text-center contract-template-fields-required">
                            @if($isSystemField)
                                <input type="hidden" name="fields[{{ $i }}][required]" value="0">
                                <span class="text-muted small" title="Не применимо">—</span>
                            @else
                                <input type="hidden" name="fields[{{ $i }}][required]" value="0">
                                <input type="checkbox" class="form-check-input"
                                       name="fields[{{ $i }}][required]" value="1"
                                       {{ !empty($field['required']) ? 'checked' : '' }}>
                            @endif
                        </td>
                        <td>
                            @if($fillMode === ContractTemplateVariablePresets::FILL_MODE_CRM)
                                <select class="form-select form-select-sm" name="fields[{{ $i }}][prefill_source]">
                                    <option value="">— не предзаполнять —</option>
                                    @foreach($prefillSources as $srcKey => $srcLabel)
                                        <option value="{{ $srcKey }}"
                                            @selected(($field['prefill_source'] ?? '') === $srcKey)>{{ $srcLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" name="fields[{{ $i }}][prefill_source]" value="">
                                <span class="text-muted small">
                                    @if($isSystemField)
                                        Автоматически
                                    @else
                                        Заполняет родитель
                                    @endif
                                    @if($adminHint)
                                        @include('partials.ui.tooltip-hint', ['title' => $adminHint, 'placement' => 'top'])
                                    @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    #fields-editor-card .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .contract-template-fields-table {
        width: 100%;
        min-width: 36rem;
        table-layout: fixed;
    }

    .contract-template-fields-table th,
    .contract-template-fields-table td {
        overflow: visible;
        white-space: normal;
        vertical-align: middle;
    }

    .contract-template-fields-table .contract-template-fields-col-key {
        width: 34%;
        min-width: 11rem;
    }

    .contract-template-fields-table .contract-template-fields-col-label {
        width: auto;
    }

    .contract-template-fields-table .contract-template-fields-col-sort {
        width: 4.5rem;
    }

    .contract-template-fields-table .contract-template-fields-col-required {
        width: 3.25rem;
    }

    .contract-template-fields-table .contract-template-fields-col-prefill {
        width: 26%;
        min-width: 9rem;
    }

    .contract-template-fields-table .contract-template-fields-key code {
        display: inline-block;
        white-space: normal;
        word-break: break-word;
        font-size: 0.8125rem;
        line-height: 1.35;
    }

    .contract-template-fields-table .contract-template-fields-required {
        vertical-align: middle;
        position: static;
    }

    .contract-template-fields-table .contract-template-fields-sort {
        vertical-align: middle;
    }

    .contract-template-fields-table .contract-template-fields-sort .form-control {
        min-width: 3.5rem;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }

    .contract-template-fields-table .contract-template-fields-required .form-check-input {
        margin: 0;
        float: none;
        position: static;
        display: inline-block;
    }

    .contract-template-fields-table .form-control,
    .contract-template-fields-table .form-select {
        max-width: 100%;
    }
</style>
