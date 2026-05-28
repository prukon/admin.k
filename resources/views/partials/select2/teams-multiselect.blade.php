@once
    @push('styles')
        <style>
            .teams-multiselect-field .select2-container--bootstrap-5.teams-multiselect-select2 {
                width: 100% !important;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple {
                min-height: calc(1.5em + 0.625rem + 2px);
                padding: 0.25rem 2rem 0.25rem 0.5rem;
                border: 1px solid #e3e6ea;
                border-radius: 0.5rem;
                background-color: #fff;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.2rem;
                margin: 0;
                padding: 0;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
                margin: 0;
                padding: 0.1rem 0.45rem 0.1rem 0.5rem;
                border: 1px solid #eceff3;
                border-radius: 999px;
                background: #f8f9fb;
                color: #5c636a;
                font-size: 0.75rem;
                line-height: 1.3;
                font-weight: 400;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
                margin-right: 0.25rem;
                padding: 0;
                border: 0;
                color: #adb5bd;
                font-weight: 500;
                line-height: 1;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
                color: #868e96;
                background: transparent;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-search--inline {
                flex: 1 1 3.5rem;
                min-width: 3.5rem;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-search--inline .select2-search__field {
                margin: 0;
                padding: 0;
                min-height: 1.25rem;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-search--inline .select2-search__field::placeholder {
                color: #b0b8c1;
            }

            .teams-multiselect-select2.select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple,
            .teams-multiselect-select2.select2-container--bootstrap-5.select2-container--open .select2-selection--multiple {
                border-color: #d0d7de;
                box-shadow: 0 0 0 0.18rem rgba(108, 117, 125, 0.08);
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545);
            }

            .teams-multiselect-select2.select2-container--bootstrap-5 .select2-selection--multiple.is-invalid:focus,
            .teams-multiselect-select2.select2-container--bootstrap-5.select2-container--open .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545);
                box-shadow: 0 0 0 0.18rem rgba(220, 53, 69, 0.12);
            }

            .teams-multiselect-select2--summary .select2-selection__choice:not(.teams-multiselect-summary) {
                display: none !important;
            }

            .teams-multiselect-select2--summary .select2-search--inline {
                flex: 1 1 4rem;
                min-width: 4rem;
            }

            .teams-multiselect-select2--summary .select2-search--inline .select2-search__field {
                width: 100% !important;
                min-width: 4rem;
            }

            .teams-multiselect-select2--summary .teams-multiselect-summary {
                background: transparent !important;
                border: 0 !important;
                padding: 0 0.35rem 0 0 !important;
                border-radius: 0 !important;
                color: #495057;
                font-size: 0.8125rem;
                font-weight: 400;
                cursor: default;
                flex: 0 1 auto;
                max-width: 55%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .teams-multiselect-select2--summary .teams-multiselect-summary .select2-selection__choice__remove {
                display: none;
            }

            .select2-dropdown.teams-multiselect-dropdown {
                border: 1px solid #e9ecef;
                border-radius: 0.625rem;
                box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.08);
                overflow: hidden;
                padding: 0.35rem;
                background: #fff;
            }

            .select2-dropdown.teams-multiselect-dropdown .select2-results__option {
                padding: 0.35rem 0.5rem;
                border-radius: 0.45rem;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
                background-color: transparent !important;
            }

            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--selectable,
            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--selected,
            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option[aria-selected="true"] {
                background-color: transparent !important;
                color: #495057 !important;
                font-weight: 400;
            }

            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--highlighted,
            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--highlighted.select2-results__option--selectable,
            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--highlighted.select2-results__option--selected,
            .select2-container--bootstrap-5 .select2-dropdown.teams-multiselect-dropdown .select2-results__option--highlighted[aria-selected="true"],
            .select2-dropdown.teams-multiselect-dropdown .select2-results__option--highlighted.select2-results__option--selectable,
            .select2-dropdown.teams-multiselect-dropdown .select2-results__option--selected.select2-results__option--highlighted {
                background-color: #f6f7f9 !important;
                color: #495057 !important;
            }

            .teams-multiselect-option {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                width: 100%;
                min-height: 1.25rem;
            }

            .teams-multiselect-option-check {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 0.875rem;
                height: 0.875rem;
                margin: 0;
                flex-shrink: 0;
                border: 1px solid #b6d4fe;
                border-radius: 0.2rem;
                background: #fff;
                box-sizing: border-box;
                pointer-events: none;
                transition: background-color 0.12s ease, border-color 0.12s ease;
            }

            .teams-multiselect-option-check.is-checked {
                position: relative;
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                border-color: #86b7fe;
            }

            .teams-multiselect-option-check.is-checked::after {
                content: '';
                position: absolute;
                top: 42%;
                left: 50%;
                width: 0.24rem;
                height: 0.44rem;
                border: solid var(--bs-primary, #0d6efd);
                border-width: 0 1.5px 1.5px 0;
                transform: translate(-50%, -50%) rotate(45deg);
            }

            .teams-multiselect-option-label {
                flex: 1 1 auto;
                min-width: 0;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function ($) {
                'use strict';

                if (window.KidsCrmTeamsMultiselectSelect2) {
                    return;
                }

                const select2Language = @include('partials.select2.ru');
                const namespace = '.kidsCrmTeamsMultiselect';

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function getSelectedIds($select) {
                    if (!$select.length) {
                        return [];
                    }

                    const values = $select.val();
                    if (values === null || values === undefined || values === '') {
                        return [];
                    }

                    return (Array.isArray(values) ? values : [values]).map(String);
                }

                function resolveUnselectTarget($select, eventParams) {
                    if (!eventParams) {
                        return $();
                    }

                    const originalTarget = eventParams.originalEvent
                        ? eventParams.originalEvent.currentTarget
                        : null;

                    if (originalTarget) {
                        return $(originalTarget);
                    }

                    if (eventParams.data) {
                        return findResultOption($select, eventParams.data.id);
                    }

                    return $();
                }

                function formatSelectionSummary(texts) {
                    if (texts.length === 0) {
                        return '';
                    }
                    if (texts.length === 1) {
                        return texts[0];
                    }
                    if (texts.length === 2) {
                        return texts[0] + ', ' + texts[1];
                    }

                    return texts[0] + ', еще ' + (texts.length - 1) + ' шт.';
                }

                function syncSelectionSummary($select) {
                    const $container = $select.next('.select2-container');
                    if (!$container.length) {
                        return;
                    }

                    const $rendered = $container.find('.select2-selection__rendered');
                    const texts = $select.find('option:selected').map(function () {
                        return $(this).text();
                    }).get();

                    $rendered.find('.teams-multiselect-summary').remove();

                    if (texts.length >= 3) {
                        $container.addClass('teams-multiselect-select2--summary');
                        const summary = formatSelectionSummary(texts);
                        const fullTitle = texts.join(', ');

                        $rendered.prepend(
                            '<li class="select2-selection__choice teams-multiselect-summary" title="' +
                            escapeHtml(fullTitle) +
                            '">' +
                            escapeHtml(summary) +
                            '</li>'
                        );
                        return;
                    }

                    $container.removeClass('teams-multiselect-select2--summary');
                }

                function formatOption(option, selectedIds) {
                    if (!option.id) {
                        return escapeHtml(option.text);
                    }

                    const checked = selectedIds.includes(String(option.id));
                    const $row = $(
                        '<span class="teams-multiselect-option">' +
                        '<span class="teams-multiselect-option-check" aria-hidden="true"></span>' +
                        '<span class="teams-multiselect-option-label"></span>' +
                        '</span>'
                    );

                    if (checked) {
                        $row.find('.teams-multiselect-option-check').addClass('is-checked');
                    }

                    $row.find('.teams-multiselect-option-label').text(option.text);

                    return $row;
                }

                function findResultOption($select, optionId) {
                    const instance = $select.data('select2');
                    if (!instance || !instance.$results) {
                        return $();
                    }

                    const targetId = String(optionId);

                    return instance.$results.find('.select2-results__option.select2-results__option--selectable').filter(function () {
                        const data = $(this).data('data');
                        return data && String(data.id) === targetId;
                    }).first();
                }

                function markResultOptionChecked($select, optionId, checked) {
                    findResultOption($select, optionId)
                        .find('.teams-multiselect-option-check')
                        .toggleClass('is-checked', checked);
                }

                function syncDropdownCheckboxes($select) {
                    const instance = $select.data('select2');
                    if (!instance || !instance.isOpen() || !instance.$results) {
                        return;
                    }

                    const selectedIds = getSelectedIds($select);

                    instance.$results.find('.select2-results__option.select2-results__option--selectable').each(function () {
                        const data = $(this).data('data');
                        if (!data || data.id === undefined || data.id === '') {
                            return;
                        }

                        const isSelected = selectedIds.includes(String(data.id));
                        $(this).find('.teams-multiselect-option-check').toggleClass('is-checked', isSelected);
                    });
                }

                function scheduleSyncDropdownCheckboxes($select, delayMs) {
                    const runSync = function () {
                        syncDropdownCheckboxes($select);
                    };

                    if (delayMs) {
                        window.setTimeout(runSync, delayMs);
                        return;
                    }

                    window.requestAnimationFrame(runSync);
                }

                function bindSearchFieldKeepOpen($select) {
                    const $container = $select.next('.select2-container');
                    if (!$container.length) {
                        return;
                    }

                    $container.off('mousedown' + namespace, '.select2-search--inline, .select2-search__field');
                    $container.on('mousedown' + namespace, '.select2-search--inline, .select2-search__field', function (e) {
                        e.stopPropagation();

                        const instance = $select.data('select2');
                        if (instance && !instance.isOpen()) {
                            $select.select2('open');
                        }
                    });

                    $container.off('focusin' + namespace, '.select2-search__field');
                    $container.on('focusin' + namespace, '.select2-search__field', function () {
                        const instance = $select.data('select2');
                        if (instance && !instance.isOpen()) {
                            $select.select2('open');
                        }
                    });
                }

                function bindEvents($select) {
                    $select.off(namespace);

                    $select.on('select2:closing' + namespace, function (e) {
                        const originalEvent = e.params && e.params.originalEvent;
                        if (originalEvent && originalEvent.type === 'keydown' && originalEvent.key === 'Escape') {
                            return;
                        }

                        const $container = $select.next('.select2-container');
                        const active = document.activeElement;

                        if ($container.length && active && $.contains($container[0], active)) {
                            e.preventDefault();
                        }
                    });

                    $select.on('select2:select' + namespace, function (e) {
                        syncSelectionSummary($select);

                        if (e.params && e.params.data) {
                            markResultOptionChecked($select, e.params.data.id, true);
                        }

                        const originalTarget = e.params && e.params.originalEvent
                            ? e.params.originalEvent.currentTarget
                            : null;

                        if (originalTarget) {
                            $(originalTarget)
                                .find('.teams-multiselect-option-check')
                                .addClass('is-checked');
                        }

                        scheduleSyncDropdownCheckboxes($select);
                    });

                    $select.on('select2:unselect' + namespace, function (e) {
                        resolveUnselectTarget($select, e.params)
                            .find('.teams-multiselect-option-check')
                            .removeClass('is-checked');

                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select, 0);
                    });

                    $select.on('change' + namespace, function () {
                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select);
                    });

                    $select.on('select2:open' + namespace, function () {
                        bindSearchFieldKeepOpen($select);
                        scheduleSyncDropdownCheckboxes($select);

                        window.setTimeout(function () {
                            $select.next('.select2-container').find('.select2-search__field').trigger('focus');
                        }, 0);
                    });
                }

                window.KidsCrmTeamsMultiselectSelect2 = {
                    init: function ($select, options) {
                        options = options || {};

                        if (!$select.length || !$.fn.select2) {
                            return;
                        }

                        if ($select.data('select2')) {
                            $select.off(namespace);
                            $select.select2('destroy');
                        }

                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: $select.data('placeholder') || options.placeholder || 'Выберите группы',
                            language: select2Language,
                            allowClear: options.allowClear !== false,
                            multiple: true,
                            closeOnSelect: false,
                            dropdownParent: options.dropdownParent || undefined,
                            containerCssClass: 'teams-multiselect-select2',
                            dropdownCssClass: 'teams-multiselect-dropdown',
                            templateResult: function (data) {
                                return formatOption(data, getSelectedIds($select));
                            }
                        });

                        bindEvents($select);
                        bindSearchFieldKeepOpen($select);
                        syncSelectionSummary($select);
                    },

                    reset: function ($select) {
                        if (!$select.length) {
                            return;
                        }

                        $select.val(null).trigger('change');
                        syncSelectionSummary($select);
                    },

                    setValues: function ($select, ids) {
                        if (!$select.length) {
                            return;
                        }

                        const values = (ids || []).map(String);
                        $select.val(values).trigger('change');
                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select);
                    },

                    clearInvalid: function ($select) {
                        if (!$select.length) {
                            return;
                        }

                        $select.removeClass('is-invalid');
                        $select.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
                    },

                    markInvalid: function ($select) {
                        if (!$select.length) {
                            return;
                        }

                        $select.addClass('is-invalid');
                        $select.next('.select2-container').find('.select2-selection').addClass('is-invalid');
                    }
                };
            })(window.jQuery);
        </script>
    @endpush
@endonce
