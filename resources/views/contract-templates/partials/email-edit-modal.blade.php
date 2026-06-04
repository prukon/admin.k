<div class="modal fade"
     id="editTemplateEmailModal"
     tabindex="-1"
     aria-labelledby="editTemplateEmailModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     data-email-show-url-template="{{ route('contract-templates.email.show', ['template' => '__ID__']) }}">
    <div class="modal-dialog modal-dialog-centered contract-template-edit-modal">
        <div class="modal-content">
            <form id="contractTemplateEmailForm" method="post" action="#" novalidate>
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="editTemplateEmailModalLabel">Письмо клиенту</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body text-start contract-template-edit-modal-body">
                    <p class="text-muted small mb-3" id="template-email-modal-subtitle"></p>

                    <div id="template-email-modal-loading" class="text-center text-muted py-4 d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Загрузка…
                    </div>

                    <div id="template-email-modal-error" class="alert alert-danger d-none" role="alert"></div>

                    <div id="template-email-modal-fields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label" for="template-email-subject">Тема письма</label>
                            <input type="text"
                                   name="email_subject"
                                   id="template-email-subject"
                                   class="form-control"
                                   maxlength="255"
                                   value="{{ old('email_subject') }}">
                            <div class="invalid-feedback d-block" data-error-for="email_subject"></div>
                        </div>

                        <div>
                            <label class="form-label" for="template-email-body">Текст письма</label>
                            @include('contract-templates.partials.email-body-field', [
                                'fieldId' => 'template-email-body',
                                'value' => old('email_body_html'),
                            ])
                            <div class="invalid-feedback d-block" data-error-for="email_body_html"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            id="template-email-reset-defaults"
                            disabled>
                        По умолчанию
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="template-email-modal-submit" disabled>
                            Сохранить письмо
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #editTemplateEmailModal .contract-template-edit-modal {
        max-width: 720px;
    }

    #editTemplateEmailModal .contract-template-edit-modal-body {
        max-height: calc(100vh - 11rem);
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
</style>
