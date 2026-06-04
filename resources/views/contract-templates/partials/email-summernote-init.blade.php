@php
    use App\Services\Contracts\ContractTemplateEmailDefaults;
@endphp
<script src="{{ asset('plugins/summernote/lang/summernote-ru-RU.min.js') }}"></script>
<style>
    #editTemplateEmailModal .note-editor .dropdown-menu {
        z-index: 1065;
    }

    #editTemplateEmailModal .note-editor.note-frame.is-invalid {
        border-color: var(--bs-form-invalid-border-color, #dc3545);
    }
</style>
<script>
    (function ($) {
        'use strict';

        const MODAL_ID = 'editTemplateEmailModal';
        const BODY_SELECTOR = '#template-email-body';
        const EMAIL_DEFAULTS = @json([
            'subject' => ContractTemplateEmailDefaults::subject(),
            'body_html' => ContractTemplateEmailDefaults::bodyHtml(),
        ]);

        const summernoteOptions = {
            height: 280,
            lang: 'ru-RU',
            disableDragAndDrop: true,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['codeview', 'help']],
            ],
            popover: {
                image: [],
                air: [],
            },
        };

        function $emailBody() {
            return $(BODY_SELECTOR);
        }

        function initEditor() {
            const $body = $emailBody();
            if (!$body.length || $body.data('summernote')) {
                return;
            }

            $body.summernote(summernoteOptions);

            if ($body.hasClass('is-invalid')) {
                $body.next('.note-editor').addClass('is-invalid');
            }
        }

        function destroyEditor() {
            const $body = $emailBody();
            if (!$body.length || !$body.data('summernote')) {
                return;
            }

            const html = $body.summernote('code');
            $body.summernote('destroy');
            $body.val(html);
        }

        function syncEditorToTextarea() {
            const $body = $emailBody();
            if ($body.data('summernote')) {
                $body.val($body.summernote('code'));
            }
        }

        function setEmailBodyHtml(html) {
            const $body = $emailBody();
            if ($body.data('summernote')) {
                $body.summernote('code', html);
            } else {
                $body.val(html);
            }
        }

        function applyDefaultEmailText() {
            const subjectInput = document.getElementById('template-email-subject');
            if (subjectInput) {
                subjectInput.value = EMAIL_DEFAULTS.subject || '';
            }

            setEmailBodyHtml(EMAIL_DEFAULTS.body_html || '');
        }

        function clearFieldErrors(form) {
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('[data-error-for]').forEach(function (el) {
                el.textContent = '';
            });
            form.querySelector('#template-email-modal-error')?.classList.add('d-none');
        }

        function showFieldErrors(form, errors) {
            Object.keys(errors || {}).forEach(function (field) {
                const messages = errors[field];
                const input = form.querySelector('[name="' + field + '"]');
                const feedback = form.querySelector('[data-error-for="' + field + '"]');

                if (input) {
                    input.classList.add('is-invalid');
                }
                if (feedback && messages && messages.length) {
                    feedback.textContent = messages[0];
                }
            });

            const $body = $emailBody();
            if ($body.hasClass('is-invalid') && $body.data('summernote')) {
                $body.next('.note-editor').addClass('is-invalid');
            }
        }

        function emailShowUrl(modalEl, templateId) {
            const template = modalEl.getAttribute('data-email-show-url-template') || '';

            return template.replace('__ID__', String(templateId));
        }

        function openEmailModal(templateId) {
            const modalEl = document.getElementById(MODAL_ID);
            if (!modalEl) {
                return;
            }

            const form = document.getElementById('contractTemplateEmailForm');
            const loading = document.getElementById('template-email-modal-loading');
            const fields = document.getElementById('template-email-modal-fields');
            const errorBox = document.getElementById('template-email-modal-error');
            const submitBtn = document.getElementById('template-email-modal-submit');
            const resetBtn = document.getElementById('template-email-reset-defaults');
            const subtitle = document.getElementById('template-email-modal-subtitle');

            clearFieldErrors(form);
            loading.classList.remove('d-none');
            fields.classList.add('d-none');
            errorBox.classList.add('d-none');
            submitBtn.disabled = true;
            if (resetBtn) {
                resetBtn.disabled = true;
            }
            subtitle.textContent = '';

            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            fetch(emailShowUrl(modalEl, templateId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    loading.classList.add('d-none');

                    if (!result.ok) {
                        errorBox.textContent = result.data.message || 'Не удалось загрузить письмо.';
                        errorBox.classList.remove('d-none');
                        return;
                    }

                    destroyEditor();

                    form.action = result.data.update_url;
                    subtitle.textContent = 'Шаблон: «' + (result.data.title || '') + '»';
                    document.getElementById('template-email-subject').value = result.data.email_subject || '';
                    $emailBody().val(result.data.email_body_html || '');

                    fields.classList.remove('d-none');
                    submitBtn.disabled = false;
                    if (resetBtn) {
                        resetBtn.disabled = false;
                    }
                    initEditor();
                })
                .catch(function () {
                    loading.classList.add('d-none');
                    errorBox.textContent = 'Не удалось загрузить письмо.';
                    errorBox.classList.remove('d-none');
                });
        }

        $(function () {
            const modalEl = document.getElementById(MODAL_ID);
            if (!modalEl) {
                return;
            }

            modalEl.addEventListener('hidden.bs.modal', function () {
                destroyEditor();
            });

            document.getElementById('contractTemplateEmailForm')?.addEventListener('submit', function (event) {
                event.preventDefault();

                const form = event.currentTarget;
                clearFieldErrors(form);
                syncEditorToTextarea();

                const submitBtn = document.getElementById('template-email-modal-submit');
                submitBtn.disabled = true;

                const body = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body,
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    })
                    .then(function (result) {
                        submitBtn.disabled = false;

                        if (result.ok) {
                            bootstrap.Modal.getInstance(modalEl)?.hide();
                            window.location.reload();
                            return;
                        }

                        if (result.status === 422 && result.data.errors) {
                            showFieldErrors(form, result.data.errors);
                            return;
                        }

                        const errorBox = document.getElementById('template-email-modal-error');
                        errorBox.textContent = result.data.message || 'Не удалось сохранить письмо.';
                        errorBox.classList.remove('d-none');
                    })
                    .catch(function () {
                        submitBtn.disabled = false;
                        const errorBox = document.getElementById('template-email-modal-error');
                        errorBox.textContent = 'Не удалось сохранить письмо.';
                        errorBox.classList.remove('d-none');
                    });
            });

            document.getElementById('template-email-reset-defaults')?.addEventListener('click', function () {
                applyDefaultEmailText();
            });

            document.getElementById('contract-templates-table')?.addEventListener('click', function (event) {
                const btn = event.target.closest('.js-contract-template-edit-email');
                if (!btn) {
                    return;
                }

                event.preventDefault();
                const templateId = btn.getAttribute('data-template-id');
                if (!templateId) {
                    return;
                }

                openEmailModal(templateId);
            });

            @if(!empty($openEmailTemplateId))
            openEmailModal(@json($openEmailTemplateId));
            @endif
        });
    })(jQuery);
</script>
