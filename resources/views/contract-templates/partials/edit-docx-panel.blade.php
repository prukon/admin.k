@php
    /** @var \App\Models\ContractTemplate $template */
    $showDocxUpload = $errors->has('docx');
@endphp

<section class="contract-template-docx-update-panel border border-2 rounded-3 p-3 p-md-4 mt-3"
         aria-labelledby="template-edit-docx-panel-title">
    <h6 class="fw-semibold mb-3" id="template-edit-docx-panel-title">Изменение файла DOCX</h6>

    <div class="mb-0">
        <div class="small text-muted mb-1">Текущая версия</div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($template->currentVersion)
                <span class="badge bg-info text-dark">v{{ $template->currentVersion->version }}</span>
                <a href="{{ route('contract-templates.download-docx', $template) }}"
                   class="btn btn-sm btn-outline-secondary"
                   target="_blank"
                   rel="noopener">Скачать шаблон</a>
                <button type="button"
                        class="btn btn-sm btn-outline-primary js-template-edit-docx-toggle"
                        id="template-edit-docx-toggle"
                        aria-expanded="{{ $showDocxUpload ? 'true' : 'false' }}"
                        aria-controls="template-edit-docx-upload">
                    {{ $showDocxUpload ? 'Скрыть' : 'Изменить' }}
                </button>
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
    </div>

    <div id="template-edit-docx-upload"
         class="mt-3 {{ $showDocxUpload ? '' : 'd-none' }}">
        <label class="form-label" for="template-edit-docx">Загрузить новую версию DOCX</label>
        <input type="file"
               name="docx"
               id="template-edit-docx"
               class="form-control @error('docx') is-invalid @enderror"
               accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        <div class="form-text mb-0">
            При загрузке нового файла будет создана новая версия; существующие договоры останутся на старой версии.
        </div>
        @error('docx')
        <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="mt-3 mb-0">
            @include('contract-templates.partials.variables-reference', [
                'compact' => true,
                'collapseAll' => true,
                'accordionId' => 'editContractTemplateVariablesAccordion',
            ])
        </div>
    </div>
</section>

<style>
    .contract-template-docx-update-panel {
        border-color: var(--bs-border-color, #dee2e6) !important;
        background-color: var(--bs-light, #f8f9fa);
    }
</style>

@once
    @push('scripts')
        <script>
            (function () {
                const modalId = 'editContractTemplateModal';

                function resetDocxUploadUi(modalEl) {
                    const upload = modalEl?.querySelector('#template-edit-docx-upload');
                    const toggle = modalEl?.querySelector('#template-edit-docx-toggle');
                    const input = modalEl?.querySelector('#template-edit-docx');

                    if (!upload || !toggle) {
                        return;
                    }

                    upload.classList.add('d-none');
                    toggle.textContent = 'Изменить';
                    toggle.setAttribute('aria-expanded', 'false');

                    if (input) {
                        input.value = '';
                    }
                }

                function bindDocxUploadToggle(modalEl) {
                    const toggle = modalEl?.querySelector('.js-template-edit-docx-toggle');
                    const upload = modalEl?.querySelector('#template-edit-docx-upload');
                    const input = modalEl?.querySelector('#template-edit-docx');

                    if (!toggle || !upload) {
                        return;
                    }

                    toggle.addEventListener('click', function () {
                        const isHidden = upload.classList.contains('d-none');

                        if (isHidden) {
                            upload.classList.remove('d-none');
                            toggle.textContent = 'Скрыть';
                            toggle.setAttribute('aria-expanded', 'true');
                            input?.focus();
                            return;
                        }

                        upload.classList.add('d-none');
                        toggle.textContent = 'Изменить';
                        toggle.setAttribute('aria-expanded', 'false');

                        if (input) {
                            input.value = '';
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const modalEl = document.getElementById(modalId);
                    if (!modalEl) {
                        return;
                    }

                    bindDocxUploadToggle(modalEl);

                    modalEl.addEventListener('hidden.bs.modal', function () {
                        resetDocxUploadUi(modalEl);
                    });
                });
            })();
        </script>
    @endpush
@endonce
