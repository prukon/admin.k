<div class="modal fade" id="createContractTemplateModal" tabindex="-1" aria-labelledby="createContractTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable contract-template-create-modal">
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

                <div class="modal-body text-start">
                    <p class="text-muted small">
                        Загрузите DOCX с плейсхолдерами вида <code>&#123;&#123;fio_parent&#125;&#125;</code>.
                        После сохранения проверьте подписи полей и текст email-уведомления.
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
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="template-create-email-subject">Тема письма клиенту (необязательно)</label>
                            <input type="text"
                                   name="email_subject"
                                   id="template-create-email-subject"
                                   class="form-control @error('email_subject') is-invalid @enderror"
                                   value="{{ old('email_subject') }}"
                                   maxlength="255"
                                   placeholder="Договор: требуется заполнение и подписание">
                            @error('email_subject')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="template-create-email-body">Текст письма (HTML, необязательно)</label>
                            <textarea name="email_body_html"
                                      id="template-create-email-body"
                                      class="form-control @error('email_body_html') is-invalid @enderror"
                                      rows="5"
                                      placeholder="&lt;p&gt;Здравствуйте! Перейдите в раздел «Мои документы»: &#123;&#123;documents_url&#125;&#125;&lt;/p&gt;">{{ old('email_body_html') }}</textarea>
                            <div class="form-text">
                                Для <strong>письма</strong> (не для DOCX): подстановки
                                <code>&#123;&#123;documents_url&#125;&#125;</code>,
                                <code>&#123;&#123;student_name&#125;&#125;</code>,
                                <code>&#123;&#123;contract_id&#125;&#125;</code>.
                                В файле DOCX используйте свои поля вида <code>&#123;&#123;fio_parent&#125;&#125;</code> — они появятся в форме клиента.
                            </div>
                            @error('email_body_html')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
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
        max-width: 520px;
    }
</style>
