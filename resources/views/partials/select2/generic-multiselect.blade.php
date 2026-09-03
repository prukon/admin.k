{{-- Общий Select2 multiselect для модалок: чекбоксы в dropdown, chip-теги, сводка при 3+ выбранных. --}}
@include('partials.select2.multiselect-chip-font')

@once
    @push('styles')
        <style>
            .generic-multiselect-field .select2-container--bootstrap-5.kids-crm-generic-ms-select2 {
                width: 100% !important;
            }

            .select2-dropdown.kids-crm-generic-ms-dropdown {
                border: 1px solid #e9ecef;
                border-radius: 0.625rem;
                box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.08);
                overflow: hidden;
                padding: 0.35rem;
                background: #fff;
                z-index: 1060;
            }

            .modal .select2-dropdown.kids-crm-generic-ms-dropdown {
                z-index: 1060;
            }

            .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option {
                padding: 0.35rem 0.5rem;
                border-radius: 0.45rem;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
                background-color: transparent !important;
            }

            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--selectable,
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--selected,
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option[aria-selected="true"] {
                background-color: transparent !important;
                color: #495057 !important;
                font-weight: 400;
            }

            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted,
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted.select2-results__option--selectable,
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted.select2-results__option--selected,
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted[aria-selected="true"] {
                background-color: #f6f7f9 !important;
                color: #495057 !important;
            }

            .kids-crm-generic-ms-option {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                width: 100%;
                min-height: 1.25rem;
            }

            .kids-crm-generic-ms-option-check {
                position: relative;
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

            .kids-crm-generic-ms-option-check.is-checked {
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                border-color: #86b7fe;
            }

            .kids-crm-generic-ms-option-check::after {
                content: '';
                position: absolute;
                top: 42%;
                left: 50%;
                width: 0.24rem;
                height: 0.44rem;
                border: solid var(--bs-primary, #0d6efd);
                border-width: 0 1.5px 1.5px 0;
                transform: translate(-50%, -50%) rotate(45deg) scale(0.4);
                opacity: 0;
                transition: opacity 0.12s ease, transform 0.12s ease;
            }

            .kids-crm-generic-ms-option-check.is-checked::after {
                opacity: 1;
                transform: translate(-50%, -50%) rotate(45deg) scale(1);
            }

            .kids-crm-generic-ms-option-check.is-checked.is-unchecking,
            .kids-crm-generic-ms-option-check.is-unchecking {
                background: #fff;
                border-color: #b6d4fe;
            }

            .kids-crm-generic-ms-option-check.is-checked.is-unchecking::after,
            .kids-crm-generic-ms-option-check.is-unchecking::after {
                opacity: 0;
                transform: translate(-50%, -50%) rotate(45deg) scale(0.4);
            }

            .kids-crm-generic-ms-option-label {
                flex: 1 1 auto;
                min-width: 0;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
                white-space: normal;
                overflow: visible;
                word-break: break-word;
            }

            .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option {
                white-space: normal;
            }

            .generic-multiselect-field .select2-container--bootstrap-5 .select2-selection.select2-selection--multiple {
                min-height: calc(1.6em + 0.75rem + 2px) !important;
                height: calc(2.25rem + 2px) !important;
                padding: 0.375rem 0.75rem !important;
                font-size: 0.9rem !important;
                line-height: 1.6 !important;
                color: #495057 !important;
                background: #f8fafc !important;
                background-color: #f8fafc !important;
                border: 1px solid #ced4da !important;
                border-radius: var(--bs-border-radius, 0.375rem) !important;
                box-shadow: none !important;
            }

            .generic-multiselect-field .select2-container .select2-selection__rendered {
                align-items: center !important;
                gap: 0.25rem !important;
            }

            .generic-multiselect-field .select2-container--focus .select2-selection--multiple,
            .generic-multiselect-field .select2-container--open .select2-selection--multiple {
                border-color: #86b7fe !important;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
                background: #f8fafc !important;
                background-color: #f8fafc !important;
            }

            /* tags: поле как chip-input, не как однострочный form-select */
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection.select2-selection--multiple {
                height: auto !important;
                min-height: calc(1.5em + 0.5rem + 2px) !important;
                padding: 0.2rem 0.45rem !important;
                font-size: 0.8125rem !important;
                line-height: 1.35 !important;
                color: #212529 !important;
                background: #fff !important;
                background-color: #fff !important;
                border: 1px solid #e3e6ea !important;
                border-radius: 0.5rem !important;
                overflow: visible !important;
            }

            .generic-multiselect-field--tags .select2-container .select2-selection__rendered {
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                gap: 0.2rem !important;
                overflow: visible !important;
                white-space: normal !important;
            }

            .generic-multiselect-field--tags .select2-container--focus .select2-selection--multiple,
            .generic-multiselect-field--tags .select2-container--open .select2-selection--multiple {
                background: #fff !important;
                background-color: #fff !important;
            }

            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice {
                display: inline-flex !important;
                flex-direction: row !important;
                align-items: center !important;
                margin: 0 !important;
                padding: 0.06rem 0.28rem 0.06rem 0.32rem !important;
                font-size: 0.8125rem !important;
                line-height: 1.25 !important;
                font-weight: 500 !important;
                color: #212529 !important;
                background: #cfe2ff !important;
                background-color: #cfe2ff !important;
                border: 1px solid #b6d4fe !important;
                border-radius: 999px !important;
                box-shadow: none !important;
                cursor: default !important;
                max-width: 100%;
                white-space: normal !important;
                overflow: visible !important;
                text-overflow: unset !important;
            }

            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered .select2-selection__choice .select2-selection__choice__remove {
                position: relative !important;
                width: 0.5rem !important;
                height: 0.5rem !important;
                min-width: 0.5rem !important;
                margin: 0 0.15rem 0 0 !important;
                padding: 0 !important;
                border: 0 !important;
                opacity: 0.55 !important;
                background-size: 0.5rem auto !important;
                background-position: center !important;
                order: -1;
            }

            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-search,
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-search.select2-search--inline,
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection .select2-selection__rendered > .select2-search.select2-search--inline,
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection.select2-selection--multiple .select2-search.select2-search--inline {
                display: flex !important;
                align-items: center !important;
                float: none !important;
                width: 100% !important;
                max-width: 100% !important;
                flex: 1 1 100% !important;
                min-width: 0 !important;
                height: auto !important;
                margin: 0 !important;
            }

            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-search .select2-search__field,
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection--multiple .select2-search--inline .select2-search__field,
            .generic-multiselect-field--tags .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection .select2-search--inline .select2-search__field {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
                min-height: 1.1rem !important;
                height: auto !important;
                font-size: 0.8125rem !important;
                line-height: 1.35 !important;
                color: #212529 !important;
                box-sizing: border-box !important;
                overflow: visible !important;
                text-overflow: clip !important;
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5 .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545) !important;
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple.is-invalid,
            .kids-crm-generic-ms-select2.select2-container--bootstrap-5.select2-container--open .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545) !important;
                box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function ($) {
                'use strict';

                if (window.KidsCrmGenericMultiselectSelect2) {
                    return;
                }

                const select2Language = @include('partials.select2.ru');
                const namespace = '.kidsCrmGenericMultiselect';
                const uncheckAnimationMs = 130;

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

                function normalizeDropdownParent($select, dropdownParent) {
                    let $parent;

                    if (dropdownParent) {
                        $parent = dropdownParent.jquery ? dropdownParent : $(dropdownParent);
                    } else {
                        $parent = $select.closest('.modal');
                    }

                    if (!$parent || !$parent.length) {
                        return null;
                    }

                    if ($parent.hasClass('modal')) {
                        const $content = $parent.find('.modal-content').first();
                        return $content.length ? $content : $parent;
                    }

                    return $parent;
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

                function renderSummaryWithHover(summary, texts) {
                    if (window.KidsCrmTooltip) {
                        return KidsCrmTooltip.renderList(summary, texts, {
                            minItemsForHover: 3
                        });
                    }

                    return escapeHtml(summary);
                }

                function syncSelectionSummary($select) {
                    const $container = $select.next('.select2-container');
                    if (!$container.length) {
                        return;
                    }

                    const $rendered = $container.find('.select2-selection__rendered');

                    if ($select.data('kidsCrmMsTags') === true) {
                        $rendered.find('.kids-crm-generic-ms-summary').remove();
                        if (window.KidsCrmMultiselectChipStyles) {
                            KidsCrmMultiselectChipStyles.apply($select, { skipSummary: true });
                            window.requestAnimationFrame(function () {
                                KidsCrmMultiselectChipStyles.apply($select, { skipSummary: true });
                            });
                        }
                        return;
                    }
                    const texts = $select.find('option:selected').map(function () {
                        return $(this).text();
                    }).get();

                    if (window.KidsCrmTooltip) {
                        KidsCrmTooltip.dispose($container[0], { scopes: ['list'] });
                    }

                    $rendered.find('.kids-crm-generic-ms-summary').remove();

                    if (texts.length >= 3) {
                        const summary = formatSelectionSummary(texts);
                        const summaryHtml = renderSummaryWithHover(summary, texts);

                        $rendered.prepend(
                            '<li class="select2-selection__choice kids-crm-generic-ms-summary kids-crm-ms-chip kids-crm-ms-summary">' +
                            summaryHtml +
                            '</li>'
                        );

                        if (window.KidsCrmTooltip) {
                            KidsCrmTooltip.init($container[0], { scopes: ['list'] });
                        }
                    }

                    if (window.KidsCrmMultiselectChipStyles) {
                        KidsCrmMultiselectChipStyles.apply($select);
                    }
                }

                function formatOption(option, selectedIds) {
                    if (!option.id) {
                        return escapeHtml(option.text);
                    }

                    const id = String(option.id);
                    const checked = selectedIds.includes(id);
                    const $row = $(
                        '<span class="kids-crm-generic-ms-option">' +
                        '<span class="kids-crm-generic-ms-option-check" aria-hidden="true"></span>' +
                        '<span class="kids-crm-generic-ms-option-label"></span>' +
                        '</span>'
                    );

                    const $check = $row.find('.kids-crm-generic-ms-option-check');
                    if (checked) {
                        $check.addClass('is-checked');
                    }

                    $row.find('.kids-crm-generic-ms-option-label').text(option.text);

                    return $row;
                }

                function findResultOption($select, optionId) {
                    const instance = $select.data('select2');
                    if (!instance || !instance.$results) {
                        return $();
                    }

                    const targetId = String(optionId);

                    return instance.$results.find('.select2-results__option[aria-selected]').filter(function () {
                        const data = $(this).data('data');
                        return data && String(data.id) === targetId;
                    }).first();
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

                function syncDropdownCheckboxes($select) {
                    const instance = $select.data('select2');
                    if (!instance || !instance.isOpen() || !instance.$results) {
                        return;
                    }

                    const selectedIds = getSelectedIds($select);

                    instance.$results.find('.select2-results__option[aria-selected]').each(function () {
                        const data = $(this).data('data');
                        if (!data || data.id === undefined || data.id === '') {
                            return;
                        }

                        const id = String(data.id);
                        const $check = $(this).find('.kids-crm-generic-ms-option-check');

                        $check.toggleClass('is-checked', selectedIds.includes(id));
                        $check.removeClass('is-unchecking');
                    });
                }

                function animateUncheck($check) {
                    if (!$check.length) {
                        return;
                    }

                    if (!$check.hasClass('is-checked')) {
                        $check.removeClass('is-unchecking');
                        return;
                    }

                    $check.addClass('is-unchecking');
                    void $check[0].offsetWidth;

                    window.setTimeout(function () {
                        $check.removeClass('is-checked is-unchecking');
                    }, uncheckAnimationMs);
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

                function unbindTagSearchKeyboard($select) {
                    const prev = $select.data('kidsCrmMsKeydown');
                    if (prev && prev.node && prev.handler) {
                        prev.node.removeEventListener('keydown', prev.handler, true);
                    }
                    $select.removeData('kidsCrmMsKeydown');
                }

                function tagSearchFields($select) {
                    const instance = $select.data('select2');
                    let $fields = $select.next('.select2-container').find('.select2-search__field');
                    if (instance && instance.$dropdown) {
                        $fields = $fields.add(instance.$dropdown.find('.select2-search__field'));
                    }
                    return $fields;
                }

                function clearTagSearchField($select) {
                    tagSearchFields($select).val('');
                }

                function expandTagSearchField($select) {
                    if ($select.data('kidsCrmMsTags') !== true) {
                        return;
                    }

                    const $fields = tagSearchFields($select);
                    $fields.css('width', '100%');

                    const placeholder = String(
                        $fields.first().attr('placeholder')
                        || $select.data('placeholder')
                        || ''
                    );
                    if (placeholder) {
                        $fields.attr('size', String(placeholder.length));
                    }

                    const instance = $select.data('select2');
                    if (instance && instance.selection && typeof instance.selection.resizeSearch === 'function') {
                        instance.selection.resizeSearch = function () {
                            this.$search.css('width', '100%');
                        };
                    }
                }

                function removeLastTagChoice($select) {
                    const values = getSelectedIds($select);
                    if (!values.length) {
                        return;
                    }

                    const next = values.slice(0, -1);
                    $select.val(next.length ? next : null).trigger('change');
                    syncSelectionSummary($select);
                    syncDropdownCheckboxes($select);
                }

                function bindTagSearchKeyboard($select) {
                    unbindTagSearchKeyboard($select);

                    const $container = $select.next('.select2-container');
                    if ($select.data('kidsCrmMsTags') !== true || !$container.length) {
                        return;
                    }

                    const node = $container.get(0);
                    const handler = function (e) {
                        const isBackspace = e.key === 'Backspace' || e.which === 8;
                        if (!isBackspace) {
                            return;
                        }

                        if (!getSelectedIds($select).length) {
                            return;
                        }

                        const term = String((e.target && e.target.value) || '');
                        if (term.indexOf('@') !== -1) {
                            return;
                        }

                        e.preventDefault();
                        e.stopImmediatePropagation();
                        if (e.target && 'value' in e.target) {
                            e.target.value = '';
                        }
                        clearTagSearchField($select);
                        removeLastTagChoice($select);
                    };

                    node.addEventListener('keydown', handler, true);
                    $select.data('kidsCrmMsKeydown', { node: node, handler: handler });
                }

                function bindEvents($select) {
                    $select.off(namespace);

                    $select.on('select2:select' + namespace, function (e) {
                        syncSelectionSummary($select);

                        if (e.params && e.params.data) {
                            findResultOption($select, e.params.data.id)
                                .find('.kids-crm-generic-ms-option-check')
                                .addClass('is-checked');
                        }

                        const originalTarget = e.params && e.params.originalEvent
                            ? e.params.originalEvent.currentTarget
                            : null;

                        if (originalTarget) {
                            $(originalTarget)
                                .find('.kids-crm-generic-ms-option-check')
                                .addClass('is-checked');
                        }

                        if ($select.data('kidsCrmMsTags') === true) {
                            clearTagSearchField($select);
                            expandTagSearchField($select);
                            window.requestAnimationFrame(function () {
                                clearTagSearchField($select);
                                expandTagSearchField($select);
                            });
                        }

                        scheduleSyncDropdownCheckboxes($select);
                    });

                    $select.on('select2:unselect' + namespace, function (e) {
                        animateUncheck(
                            resolveUnselectTarget($select, e.params)
                                .find('.kids-crm-generic-ms-option-check')
                        );

                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select, 0);
                    });

                    $select.on('change' + namespace, function () {
                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select);
                    });

                    $select.on('select2:open' + namespace, function () {
                        expandTagSearchField($select);
                        scheduleSyncDropdownCheckboxes($select);
                    });

                    bindTagSearchKeyboard($select);
                    expandTagSearchField($select);
                }

                window.KidsCrmGenericMultiselectSelect2 = {
                    init: function ($select, options) {
                        options = options || {};

                        if (!$select.length || !$.fn.select2) {
                            return;
                        }

                        if ($select.data('select2')) {
                            unbindTagSearchKeyboard($select);
                            $select.off(namespace);
                            $select.select2('destroy');
                        }

                        const $dropdownParent = normalizeDropdownParent($select, options.dropdownParent);

                        const select2Options = {
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: $select.data('placeholder') || options.placeholder || 'Выберите значения',
                            language: select2Language,
                            allowClear: options.allowClear === true,
                            multiple: true,
                            closeOnSelect: options.tags === true,
                            dropdownParent: $dropdownParent && $dropdownParent.length ? $dropdownParent : undefined,
                            containerCssClass: 'kids-crm-generic-ms-select2',
                            selectionCssClass: 'kids-crm-ms-selection',
                            dropdownCssClass: 'kids-crm-generic-ms-dropdown',
                            templateResult: function (data) {
                                return formatOption(data, getSelectedIds($select));
                            }
                        };

                        if (options.tags === true) {
                            select2Options.tags = true;
                            if (Array.isArray(options.tokenSeparators)) {
                                select2Options.tokenSeparators = options.tokenSeparators;
                            }
                            if (typeof options.createTag === 'function') {
                                select2Options.createTag = options.createTag;
                            }
                        }

                        $select.data('kidsCrmMsTags', options.tags === true);
                        $select.closest('.generic-multiselect-field').toggleClass(
                            'generic-multiselect-field--tags',
                            options.tags === true
                        );

                        $select.select2(select2Options);

                        bindEvents($select);
                        syncSelectionSummary($select);
                    },

                    initAll: function ($root, options) {
                        ($root || $(document)).find('.js-generic-multiselect-select').each(function () {
                            window.KidsCrmGenericMultiselectSelect2.init($(this), options || {});
                        });
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

                        $select.val((ids || []).map(String)).trigger('change');
                        syncSelectionSummary($select);
                        syncDropdownCheckboxes($select);
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

                window.KidsCrmUserStudentTeamsSelect2 = window.KidsCrmGenericMultiselectSelect2;
                window.KidsCrmTeamsMultiselectSelect2 = window.KidsCrmGenericMultiselectSelect2;
                window.KidsCrmLocationsMultiselectSelect2 = window.KidsCrmGenericMultiselectSelect2;
            })(window.jQuery);
        </script>
    @endpush
@endonce
