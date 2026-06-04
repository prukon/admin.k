@if(!empty($editTemplate))
    <div class="modal fade"
         id="editContractTemplateModal"
         tabindex="-1"
         aria-labelledby="editContractTemplateModalLabel"
         aria-hidden="true"
         data-bs-backdrop="static"
         data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered contract-template-edit-modal">
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

                    <div class="modal-body text-start contract-template-edit-modal-body">
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

        #editContractTemplateModal .contract-template-edit-modal-body {
            max-height: calc(100vh - 11rem);
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
    </style>

    @push('scripts')
        <script>
            document.getElementById('editContractTemplateModal')?.addEventListener('shown.bs.modal', function () {
                const accordion = document.getElementById('editContractTemplateVariablesAccordion');
                if (!accordion) {
                    return;
                }

                accordion.querySelectorAll('.accordion-collapse.show').forEach(function (panel) {
                    panel.classList.remove('show');
                });

                accordion.querySelectorAll('.accordion-button').forEach(function (button) {
                    button.classList.add('collapsed');
                    button.setAttribute('aria-expanded', 'false');
                });

                if (typeof window.initKidsCrmTooltipHints === 'function') {
                    window.initKidsCrmTooltipHints(document.getElementById('editContractTemplateModal'));
                }
            });
        </script>
    @endpush
@endif
