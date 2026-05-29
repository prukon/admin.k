{{-- Общий Select2 multiselect: чекбоксы в dropdown, chip-теги, сводка при 3+ выбранных. --}}
@include('partials.select2.multiselect-chip-font')

@once
    @push('styles')
        <style>
            .generic-multiselect-field .select2-container--bootstrap-5.kids-crm-generic-ms-select2 {
                width: 100% !important;
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5 .select2-search--inline {
                flex: 1 1 3.5rem;
                min-width: 3.5rem;
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5 .select2-search--inline .select2-search__field::placeholder {
                color: #b0b8c1;
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple,
            .kids-crm-generic-ms-select2.select2-container--bootstrap-5.select2-container--open .select2-selection--multiple {
                border-color: #d0d7de;
                box-shadow: 0 0 0 0.18rem rgba(108, 117, 125, 0.08);
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5 .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545);
            }

            .kids-crm-generic-ms-select2.select2-container--bootstrap-5 .select2-selection--multiple.is-invalid:focus,
            .kids-crm-generic-ms-select2.select2-container--bootstrap-5.select2-container--open .select2-selection--multiple.is-invalid {
                border-color: var(--bs-form-invalid-border-color, #dc3545);
                box-shadow: 0 0 0 0.18rem rgba(220, 53, 69, 0.12);
            }

            .select2-container--bootstrap-5.kids-crm-ms-summary-mode.kids-crm-generic-ms-select2 .select2-search--inline {
                flex: 1 1 4rem;
                min-width: 4rem;
            }

            .select2-container--bootstrap-5.kids-crm-ms-summary-mode.kids-crm-generic-ms-select2 .select2-search--inline .select2-search__field {
                width: 100% !important;
                min-width: 4rem;
            }

            .select2-dropdown.kids-crm-generic-ms-dropdown {
                border: 1px solid #e9ecef;
                border-radius: 0.625rem;
                box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.08);
                overflow: hidden;
                padding: 0.35rem;
                background: #fff;
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
            .select2-container--bootstrap-5 .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted[aria-selected="true"],
            .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--highlighted.select2-results__option--selectable,
            .select2-dropdown.kids-crm-generic-ms-dropdown .select2-results__option--selected.select2-results__option--highlighted {
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
                position: relative;
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                border-color: #86b7fe;
            }

            .kids-crm-generic-ms-option-check.is-checked::after {
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

            .kids-crm-generic-ms-option-label {
                flex: 1 1 auto;
                min-width: 0;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
            }

            .modal-content > .select2-container.select2-container--open {
                position: absolute !important;
                z-index: 1060;
            }

            .modal .select2-dropdown.kids-crm-generic-ms-dropdown {
                z-index: 1060;
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

                function renderSummaryWithHover(summary, texts) {
                    if (window.KidsCrmHoverListDropdown) {
                        return KidsCrmHoverListDropdown.renderCell(summary, texts, {
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
                    const texts = $select.find('option:selected').map(function () {
                        return $(this).text();
                    }).get();

                    if (window.KidsCrmHoverListDropdown) {
                        KidsCrmHoverListDropdown.dispose($container[0]);
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

                        if (window.KidsCrmHoverListDropdown) {
                            KidsCrmHoverListDropdown.init($container[0]);
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

                    const checked = selectedIds.includes(String(option.id));
                    const $row = $(
                        '<span class="kids-crm-generic-ms-option">' +
                        '<span class="kids-crm-generic-ms-option-check" aria-hidden="true"></span>' +
                        '<span class="kids-crm-generic-ms-option-label"></span>' +
                        '</span>'
                    );

                    if (checked) {
                        $row.find('.kids-crm-generic-ms-option-check').addClass('is-checked');
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

                    return instance.$results.find('.select2-results__option.select2-results__option--selectable').filter(function () {
                        const data = $(this).data('data');
                        return data && String(data.id) === targetId;
                    }).first();
                }

                function markResultOptionChecked($select, optionId, checked) {
                    findResultOption($select, optionId)
                        .find('.kids-crm-generic-ms-option-check')
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
                        $(this).find('.kids-crm-generic-ms-option-check').toggleClass('is-checked', isSelected);
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

                function repositionDropdown($select) {
                    const instance = $select.data('select2');
                    if (!instance || !instance.dropdown) {
                        return;
                    }

                    const dropdown = instance.dropdown;

                    if (typeof dropdown._positionDropdown === 'function') {
                        dropdown._positionDropdown();
                    }

                    if (typeof dropdown._resizeDropdown === 'function') {
                        dropdown._resizeDropdown();
                    }
                }

                function scheduleDropdownReposition($select) {
                    window.requestAnimationFrame(function () {
                        repositionDropdown($select);
                    });
                }

                function unbindModalReposition($select) {
                    const modalNs = $select.data('kidsCrmGenericMsModalNs');
                    if (!modalNs) {
                        return;
                    }

                    $select.closest('.modal').off('shown.bs.modal' + modalNs);
                    $select.removeData('kidsCrmGenericMsModalNs');
                }

                function bindModalReposition($select, $dropdownParent) {
                    unbindModalReposition($select);

                    if (!$dropdownParent || !$dropdownParent.length) {
                        return;
                    }

                    const $modal = $dropdownParent.closest('.modal');
                    if (!$modal.length) {
                        return;
                    }

                    const modalNs = namespace + '-modal-' + ($select.attr('id') || 'generic-ms');
                    $select.data('kidsCrmGenericMsModalNs', modalNs);

                    $modal.on('shown.bs.modal' + modalNs, function () {
                        const instance = $select.data('select2');
                        if (instance && instance.isOpen()) {
                            scheduleDropdownReposition($select);
                        }
                    });
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
                        const instance = $select.data('select2');
                        const $dropdown = instance && instance.dropdown && instance.dropdown.$dropdown
                            ? instance.dropdown.$dropdown
                            : $();

                        if (originalEvent && originalEvent.target) {
                            const $target = $(originalEvent.target);
                            const clickedInsideControl = $target.closest($container).length > 0;
                            const clickedInsideDropdown = $target.closest($dropdown).length > 0;

                            if (!clickedInsideControl && !clickedInsideDropdown) {
                                return;
                            }
                        }

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
                                .find('.kids-crm-generic-ms-option-check')
                                .addClass('is-checked');
                        }

                        scheduleSyncDropdownCheckboxes($select);
                    });

                    $select.on('select2:unselect' + namespace, function (e) {
                        resolveUnselectTarget($select, e.params)
                            .find('.kids-crm-generic-ms-option-check')
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
                        scheduleDropdownReposition($select);

                        window.setTimeout(function () {
                            $select.next('.select2-container').find('.select2-search__field').trigger('focus');
                        }, 0);
                    });
                }

                window.KidsCrmGenericMultiselectSelect2 = {
                    init: function ($select, options) {
                        options = options || {};

                        if (!$select.length || !$.fn.select2) {
                            return;
                        }

                        if ($select.data('select2')) {
                            unbindModalReposition($select);
                            $select.off(namespace);
                            $select.select2('destroy');
                        }

                        const $dropdownParent = normalizeDropdownParent($select, options.dropdownParent);

                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: $select.data('placeholder') || options.placeholder || 'Выберите значения',
                            language: select2Language,
                            allowClear: options.allowClear !== false,
                            multiple: true,
                            closeOnSelect: false,
                            dropdownParent: $dropdownParent && $dropdownParent.length ? $dropdownParent : undefined,
                            containerCssClass: 'kids-crm-generic-ms-select2',
                            selectionCssClass: 'kids-crm-ms-selection',
                            dropdownCssClass: 'kids-crm-generic-ms-dropdown',
                            templateResult: function (data) {
                                return formatOption(data, getSelectedIds($select));
                            }
                        });

                        bindEvents($select);
                        bindSearchFieldKeepOpen($select);
                        bindModalReposition($select, $dropdownParent);
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

                window.KidsCrmTeamsMultiselectSelect2 = window.KidsCrmGenericMultiselectSelect2;
                window.KidsCrmLocationsMultiselectSelect2 = window.KidsCrmGenericMultiselectSelect2;
            })(window.jQuery);
        </script>
    @endpush
@endonce
