<div class="modal fade"
     id="editContractTemplateModal"
     tabindex="-1"
     aria-labelledby="editContractTemplateModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     data-edit-show-url-template="{{ route('contract-templates.edit', ['template' => '__ID__']) }}">
    <div class="modal-dialog modal-dialog-centered contract-template-edit-modal">
        <div class="modal-content">
            <form id="contractTemplateEditForm"
                  method="post"
                  action="{{ !empty($editTemplate) ? route('contract-templates.update', $editTemplate) : '#' }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="editContractTemplateModalLabel">
                        @if(!empty($editTemplate))
                            Шаблон: {{ $editTemplate->title }}
                        @else
                            Редактирование шаблона
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body text-start contract-template-edit-modal-body">
                    <p class="text-muted small mb-3 {{ !empty($editTemplate) ? 'd-none' : '' }}"
                       id="edit-template-modal-subtitle"></p>

                    <div id="edit-template-modal-loading" class="text-center text-muted py-4 d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Загрузка…
                    </div>

                    <div id="edit-template-modal-error" class="alert alert-danger d-none" role="alert"></div>

                    <div id="edit-template-modal-fields">
                        @if(!empty($editTemplate))
                            @include('contract-templates.partials.edit-form', [
                                'template' => $editTemplate,
                                'fields' => $editFields ?? [],
                                'prefillSources' => $prefillSources,
                            ])
                        @endif
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="edit-template-modal-submit">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .contract-template-edit-modal {
        max-width: 720px;
    }

    #editContractTemplateModal .contract-template-edit-modal-body {
        max-height: calc(100vh - 11rem);
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
</style>
