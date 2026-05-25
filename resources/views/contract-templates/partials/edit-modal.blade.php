@if(!empty($editTemplate))
    <div class="modal fade" id="editContractTemplateModal" tabindex="-1" aria-labelledby="editContractTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable contract-template-edit-modal">
            <div class="modal-content">
                <form id="contractTemplateEditForm"
                      method="post"
                      action="{{ route('contract-templates.update', $editTemplate) }}"
                      enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <h5 class="modal-title" id="editContractTemplateModalLabel">Шаблон: {{ $editTemplate->title }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>

                    <div class="modal-body text-start">
                        @include('contract-templates.partials.edit-form', [
                            'template' => $editTemplate,
                            'fields' => $editFields ?? [],
                            'prefillSources' => $prefillSources,
                        ])
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .contract-template-edit-modal {
            max-width: 720px;
        }
    </style>
@endif
