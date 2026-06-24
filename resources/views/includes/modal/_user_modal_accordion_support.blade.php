@once
    @push('styles')
        <style>
            #createUserModal .user-modal-accordion,
            #editUserModal .user-modal-accordion {
                --bs-accordion-btn-padding-x: 0.75rem;
                --bs-accordion-btn-padding-y: 0.65rem;
                --bs-accordion-body-padding-x: 0.75rem;
                --bs-accordion-body-padding-y: 0.85rem;
            }

            #createUserModal .user-modal-accordion .accordion-item,
            #editUserModal .user-modal-accordion .accordion-item {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                overflow: hidden;
            }

            #createUserModal .user-modal-accordion .accordion-button,
            #editUserModal .user-modal-accordion .accordion-button {
                font-weight: 600;
                font-size: 0.875rem;
                box-shadow: none;
            }

            #createUserModal .user-modal-accordion .accordion-button:not(.collapsed),
            #editUserModal .user-modal-accordion .accordion-button:not(.collapsed) {
                color: inherit;
                background-color: #f8f9fa;
            }

            #createUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-btn,
            #editUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-btn {
                display: none;
            }

            #createUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-panel,
            #editUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-panel {
                display: block !important;
                height: auto !important;
                visibility: visible !important;
            }

            #createUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-panel.collapsing,
            #editUserModal .user-modal-accordion.user-modal-accordion--flat .js-user-student-accordion-panel.collapsing {
                display: block !important;
                height: auto !important;
                transition: none;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function ($) {
                'use strict';

                function studentAccordionPanel(prefix) {
                    return document.getElementById(prefix + 'UserStudentCollapse');
                }

                window.collapseStudentUserAccordion = function (prefix) {
                    const panelEl = studentAccordionPanel(prefix);
                    if (!panelEl || !window.bootstrap?.Collapse) {
                        return;
                    }

                    const instance = bootstrap.Collapse.getInstance(panelEl)
                        || bootstrap.Collapse.getOrCreateInstance(panelEl, { toggle: false });
                    instance.hide();
                };

                window.expandStudentUserAccordion = function (prefix) {
                    const panelEl = studentAccordionPanel(prefix);
                    if (!panelEl || !window.bootstrap?.Collapse) {
                        return;
                    }

                    const instance = bootstrap.Collapse.getInstance(panelEl)
                        || bootstrap.Collapse.getOrCreateInstance(panelEl, { toggle: false });
                    instance.show();
                };

                window.syncUserStudentAccordionMode = function (prefix, isStudent) {
                    const $accordion = $('#' + prefix + 'UserStudentAccordion');
                    if (!$accordion.length) {
                        return;
                    }

                    $accordion.toggleClass('user-modal-accordion--flat', !isStudent);
                };

                window.syncStudentUserAccordionsForErrors = function (prefix, $form) {
                    if (!$form || !$form.length) {
                        return;
                    }

                    const $studentPanel = $('#' + prefix + 'UserStudentCollapse');
                    const hasStudentFieldErrors = $studentPanel.length
                        && $form.find('#' + prefix + 'UserStudentCollapse .is-invalid').length > 0;

                    if (hasStudentFieldErrors) {
                        window.expandStudentUserAccordion(prefix);
                    }

                    if (typeof window.syncStudentParentAccordionForErrors === 'function') {
                        window.syncStudentParentAccordionForErrors(prefix);
                    }
                };

                window.resetStudentUserAccordions = function (prefix) {
                    window.collapseStudentUserAccordion(prefix);

                    if (typeof window.collapseStudentParentAccordion === 'function') {
                        window.collapseStudentParentAccordion(prefix);
                    }
                };
            })(window.jQuery);
        </script>
    @endpush
@endonce
