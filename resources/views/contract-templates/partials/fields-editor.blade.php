@php
    /** @var array $fields */
    /** @var array<string, string> $prefillSources */
@endphp

<div class="card mt-3" id="fields-editor-card" style="{{ empty($fields) ? 'display:none' : '' }}">
    <div class="card-header">Поля шаблона (из DOCX)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="fields-table">
                <thead>
                <tr>
                    <th>Ключ</th>
                    <th>Подпись для формы</th>
                    <th class="text-center">Обязательное</th>
                    <th>Предзаполнение</th>
                </tr>
                </thead>
                <tbody>
                @foreach($fields as $i => $field)
                    <tr>
                        <td>
                            <code>{{ $field['key'] }}</code>
                            <input type="hidden" name="fields[{{ $i }}][key]" value="{{ $field['key'] }}">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm"
                                   name="fields[{{ $i }}][label]"
                                   value="{{ $field['label'] ?? $field['key'] }}">
                        </td>
                        <td class="text-center">
                            <input type="hidden" name="fields[{{ $i }}][required]" value="0">
                            <input type="checkbox" class="form-check-input"
                                   name="fields[{{ $i }}][required]" value="1"
                                   {{ !empty($field['required']) ? 'checked' : '' }}>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="fields[{{ $i }}][prefill_source]">
                                <option value="">— не предзаполнять —</option>
                                @foreach($prefillSources as $srcKey => $srcLabel)
                                    <option value="{{ $srcKey }}"
                                        @selected(($field['prefill_source'] ?? '') === $srcKey)>{{ $srcLabel }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
