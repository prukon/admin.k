<div class="modal fade"
     id="createContractTemplateModal"
     tabindex="-1"
     aria-labelledby="createContractTemplateModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered contract-template-create-modal">
        <div class="modal-content">
            <form id="contractTemplateCreateForm"
                  method="post"
                  action="{{ route('contract-templates.store') }}"
                  enctype="multipart/form-data">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="createContractTemplateModalLabel">Новый шаблон договора</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body text-start contract-template-create-modal-body">
                    <p class="text-muted small">
                        Загрузите DOCX с плейсхолдерами вида <code>&#123;&#123;parent_full_name&#125;&#125;</code>.
                        Список рекомендуемых переменных — в блоке ниже.
                    </p>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="template-create-title">Название</label>
                            <input type="text"
                                   name="title"
                                   id="template-create-title"
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}"
                                   required
                                   maxlength="255">
                            @error('title')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="template-create-docx">Файл DOCX</label>
                            <input type="file"
                                   name="docx"
                                   id="template-create-docx"
                                   class="form-control @error('docx') is-invalid @enderror"
                                   accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   required>
                            @error('docx')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Текст письма клиенту настраивается после сохранения — кнопка
                                «Письмо» в таблице шаблонов.
                            </div>
                        </div>

                    </div>

                    @include('contract-templates.partials.variables-reference', [
                        'compact' => true,
                        'accordionId' => 'createContractTemplateVariablesAccordion',
                    ])
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .contract-template-create-modal {
        max-width: 720px;
    }

    #createContractTemplateModal .contract-template-create-modal-body {
        max-height: calc(100vh - 11rem);
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
</style>
