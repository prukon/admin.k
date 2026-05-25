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

    <div class="col-12">
        <label class="form-label">Текущая версия DOCX</label>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($template->currentVersion)
                <span class="badge bg-info text-dark">v{{ $template->currentVersion->version }}</span>
                <a href="{{ route('contract-templates.download-docx', $template) }}"
                   class="btn btn-sm btn-outline-secondary"
                   target="_blank"
                   rel="noopener">Скачать DOCX</a>
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
    </div>

    <div class="col-12">
        <label class="form-label" for="template-edit-docx">Загрузить новую версию DOCX (необязательно)</label>
        <input type="file"
               name="docx"
               id="template-edit-docx"
               class="form-control @error('docx') is-invalid @enderror"
               accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        <div class="form-text">При загрузке нового файла будет создана новая версия; существующие договоры останутся на старой версии.</div>
        @error('docx')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="is_archived"
                   value="1"
                   id="template-edit-is-archived"
                   @checked(old('is_archived', $template->is_archived))>
            <label class="form-check-label" for="template-edit-is-archived">
                В архиве (нельзя выбрать при создании договора)
            </label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label" for="template-edit-email-subject">Тема письма клиенту</label>
        <input type="text"
               name="email_subject"
               id="template-edit-email-subject"
               class="form-control @error('email_subject') is-invalid @enderror"
               value="{{ old('email_subject', $template->currentVersion?->email_subject) }}"
               maxlength="255">
        @error('email_subject')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="template-edit-email-body">Текст письма (HTML)</label>
        <textarea name="email_body_html"
                  id="template-edit-email-body"
                  class="form-control @error('email_body_html') is-invalid @enderror"
                  rows="5">{{ old('email_body_html', $template->currentVersion?->email_body_html) }}</textarea>
        <div class="form-text">
            Подстановки:
            <code>&#123;&#123;documents_url&#125;&#125;</code>,
            <code>&#123;&#123;student_name&#125;&#125;</code>,
            <code>&#123;&#123;contract_id&#125;&#125;</code>
        </div>
        @error('email_body_html')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@include('contract-templates.partials.fields-editor', [
    'fields' => $fields,
    'prefillSources' => $prefillSources,
])
