@php
    /** @var \App\Models\ContractTemplate $template */
    /** @var array $fields */
    /** @var array<string, string> $prefillSources */
@endphp

<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="template-edit-title">Название</label>
        <input type="text"
               name="title"
               id="template-edit-title"
               class="form-control @error('title') is-invalid @enderror"
               value="{{ old('title', $template->title) }}"
               required
               maxlength="255">
        @error('title')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>

@include('contract-templates.partials.fields-editor', [
    'fields' => $fields,
    'prefillSources' => $prefillSources,
])

@include('contract-templates.partials.edit-docx-panel', ['template' => $template])

@php
    $templateIsArchived = filter_var(old('is_archived', $template->is_archived), FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="row g-3 mt-1">
    <div class="col-12 col-md-6">
        <div class="mb-0">
            <label for="template-edit-activity" class="form-label">Активность</label>
            <select id="template-edit-activity"
                    name="is_archived"
                    class="form-select @error('is_archived') is-invalid @enderror">
                <option value="0" @selected(!$templateIsArchived)>Активен</option>
                <option value="1" @selected($templateIsArchived)>В архиве</option>
            </select>
            {{-- <div class="form-text">Шаблон в архиве нельзя выбрать при создании договора.</div> --}}
            @error('is_archived')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
