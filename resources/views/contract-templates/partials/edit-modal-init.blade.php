<script>
    (function () {
        'use strict';

        const MODAL_ID = 'editContractTemplateModal';
        const shouldOpenEditModal = @json($shouldOpenEditModal ?? false);

        function modalEl() {
            return document.getElementById(MODAL_ID);
        }

        function editShowUrl(templateId) {
            const el = modalEl();
            const template = el?.getAttribute('data-edit-show-url-template') || '';

            return template.replace('__ID__', String(templateId));
        }

        function resetEditAccordion(modal) {
            const accordion = modal?.querySelector('#editContractTemplateVariablesAccordion');
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
        }

        function resetDocxUploadUi(modal) {
            const upload = modal?.querySelector('#template-edit-docx-upload');
            const toggle = modal?.querySelector('#template-edit-docx-toggle');
            const input = modal?.querySelector('#template-edit-docx');

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

        function bindDocxUploadToggle(modal) {
            const toggle = modal?.querySelector('.js-template-edit-docx-toggle');
            const upload = modal?.querySelector('#template-edit-docx-upload');
            const input = modal?.querySelector('#template-edit-docx');

            if (!toggle || !upload || toggle.dataset.bound === '1') {
                return;
            }

            toggle.dataset.bound = '1';

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

        function initEditModalUi(modal) {
            if (!modal) {
                return;
            }

            bindDocxUploadToggle(modal);
            resetEditAccordion(modal);

            if (window.KidsCrmTooltip) {
                KidsCrmTooltip.init(modal, { scopes: ['hint'] });
            }
        }

        function mountEditForm(payload) {
            const modal = modalEl();
            const form = document.getElementById('contractTemplateEditForm');
            const fields = document.getElementById('edit-template-modal-fields');
            const titleEl = document.getElementById('editContractTemplateModalLabel');
            const subtitle = document.getElementById('edit-template-modal-subtitle');
            const submitBtn = document.getElementById('edit-template-modal-submit');

            if (!modal || !form || !fields) {
                return;
            }

            form.action = payload.update_url || '#';
            fields.innerHTML = payload.html || '';

            if (titleEl) {
                titleEl.textContent = 'Шаблон: ' + (payload.title || '');
            }

            if (subtitle) {
                subtitle.classList.add('d-none');
                subtitle.textContent = '';
            }

            if (submitBtn) {
                submitBtn.disabled = false;
            }

            initEditModalUi(modal);
        }

        function openEditModal(templateId) {
            const modal = modalEl();
            const form = document.getElementById('contractTemplateEditForm');
            const fields = document.getElementById('edit-template-modal-fields');
            const loading = document.getElementById('edit-template-modal-loading');
            const errorBox = document.getElementById('edit-template-modal-error');
            const submitBtn = document.getElementById('edit-template-modal-submit');

            if (!modal || !form || !fields || !templateId) {
                return;
            }

            errorBox?.classList.add('d-none');
            errorBox && (errorBox.textContent = '');
            loading?.classList.remove('d-none');
            fields.innerHTML = '';
            submitBtn && (submitBtn.disabled = true);

            bootstrap.Modal.getOrCreateInstance(modal).show();

            fetch(editShowUrl(templateId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    loading?.classList.add('d-none');

                    if (!result.ok) {
                        if (errorBox) {
                            errorBox.textContent = result.data.message || 'Не удалось загрузить шаблон.';
                            errorBox.classList.remove('d-none');
                        }
                        return;
                    }

                    mountEditForm(result.data);
                })
                .catch(function () {
                    loading?.classList.add('d-none');
                    if (errorBox) {
                        errorBox.textContent = 'Не удалось загрузить шаблон.';
                        errorBox.classList.remove('d-none');
                    }
                });
        }

        window.ContractTemplateEditModal = {
            open: openEditModal,
            initEditModalUi: initEditModalUi,
        };

        document.addEventListener('DOMContentLoaded', function () {
            const modal = modalEl();
            if (!modal) {
                return;
            }

            modal.addEventListener('hidden.bs.modal', function () {
                if (@json($errors->any() && request()->filled('edit'))) {
                    return;
                }

                if (window.location.search.includes('edit=')) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
                }

                resetDocxUploadUi(modal);
            });

            if (shouldOpenEditModal) {
                initEditModalUi(modal);
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }

            document.getElementById('contract-templates-table')?.addEventListener('click', function (event) {
                const link = event.target.closest('.js-contract-template-edit-link');
                if (!link) {
                    return;
                }

                event.preventDefault();

                const templateId = link.getAttribute('data-template-id');
                if (templateId) {
                    openEditModal(templateId);
                }
            });
        });
    })();
</script>
