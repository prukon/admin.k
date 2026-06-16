{{-- Общий Select2 multiselect: чекбоксы в dropdown, chip-теги, сводка при 3+ выбранных. --}}
@include('partials.select2.multiselect-chip-font')

@once
    @push('styles')
        <style>
            .generic-multiselect-field .select2-container--bootstrap-5.kids-crm-generic-ms-select2 {
                width: 100% !important;
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

            /* Filter multiselect: оболочка как form-select, текст выбора — компактный. */
            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 {
                width: 100% !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection.select2-selection--multiple {
                min-height: calc(1.5em + 0.75rem + 2px) !important;
                padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
                font-weight: 400 !important;
                color: var(--bs-body-color, #212529) !important;
                background-color: var(--bs-body-bg, #fff) !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
                background-repeat: no-repeat !important;
                background-position: right 0.75rem center !important;
                background-size: 16px 12px !important;
                border: var(--bs-border-width, 1px) solid var(--bs-border-color, #ced4da) !important;
                border-radius: var(--bs-border-radius, 0.375rem) !important;
                box-shadow: none !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2.select2-container--focus .select2-selection.kids-crm-filter-ms-selection.select2-selection--multiple,
            .select2-container--bootstrap-5.kids-crm-filter-ms-select2.select2-container--open .select2-selection.kids-crm-filter-ms-selection.select2-selection--multiple {
                border-color: #86b7fe !important;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection .select2-selection__rendered {
                display: flex !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                font-size: 0.8125rem !important;
                line-height: 1.35 !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection li.select2-selection__choice {
                display: inline !important;
                flex: 0 1 auto !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 0.8125rem !important;
                font-weight: 400 !important;
                line-height: 1.35 !important;
                color: #212529 !important;
                background: transparent !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                max-width: 100% !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection li.select2-selection__choice + li.select2-selection__choice::before {
                content: ', ';
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection .select2-selection__choice__remove {
                display: none !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection.select2-selection--multiple .select2-search {
                display: inline-flex !important;
                width: auto !important;
                height: auto !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection .select2-selection__rendered > .select2-search.select2-search--inline {
                display: inline-flex !important;
                align-items: center !important;
                float: none !important;
                width: auto !important;
                flex: 0 0 auto !important;
                min-width: 0.75rem;
                height: auto !important;
                margin: 0 !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection .select2-search--inline .select2-search__field {
                margin: 0 !important;
                padding: 0 !important;
                width: 0.75em !important;
                min-width: 0.75rem !important;
                min-height: 0 !important;
                height: auto !important;
                font-family: inherit !important;
                font-size: 0.8125rem !important;
                font-weight: 400 !important;
                line-height: 1.35 !important;
                color: #212529 !important;
                background: transparent !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection .select2-search--inline .select2-search__field::placeholder {
                color: var(--bs-secondary-color, #6c757d) !important;
                opacity: 1;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2.kids-crm-filter-ms-summary-mode .select2-selection.kids-crm-filter-ms-selection li.select2-selection__choice:not(.kids-crm-filter-ms-summary) {
                display: none !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection li.select2-selection__choice.kids-crm-filter-ms-summary {
                display: inline !important;
                font-size: 0.8125rem !important;
                line-height: 1.35 !important;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap !important;
            }

            .select2-container--bootstrap-5.kids-crm-filter-ms-select2 .select2-selection.kids-crm-filter-ms-selection li.select2-selection__choice.kids-crm-filter-ms-summary .kids-hover-list-dropdown__trigger {
                font-size: 0.8125rem !important;
                line-height: 1.35 !important;
                color: #212529 !important;
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
                    if (window.KidsCrmTooltip) {
                        return KidsCrmTooltip.renderList(summary, texts, {
                            minItemsForHover: 3
                        });
                    }

                    return escapeHtml(summary);
                }

                function isFilterMode($select, options) {
                    if (options && options.mode === 'filter') {
                        return true;
                    }

                    return $select.hasClass('js-filter-multiselect-select');
                }

                function syncFilterInlineSearchWidth($select) {
                    if (!$select.data('kidsCrmMsFilterMode')) {
                        return;
                    }

                    const $search = $select.next('.select2-container').find('.select2-search__field');
                    if (!$search.length) {
                        return;
                    }

                    const selectedCount = getSelectedIds($select).length;
                    const searchValue = String($search.val() || '');
                    let widthEm;

                    if (searchValue.length > 0) {
                        widthEm = Math.max(1.25, (searchValue.length + 0.5) * 0.65);
                    } else if (selectedCount > 0) {
                        widthEm = 0.75;
                    } else {
                        const placeholder = String($search.attr('placeholder') || '');
                        widthEm = Math.max(2, Math.min(placeholder.length * 0.5, 10));
                    }

                    $search.css('width', widthEm + 'em');
                }

                function scheduleSyncFilterInlineSearchWidth($select) {
                    if (!$select.data('kidsCrmMsFilterMode')) {
                        return;
                    }

                    window.requestAnimationFrame(function () {
                        syncFilterInlineSearchWidth($select);
                        window.setTimeout(function () {
                            syncFilterInlineSearchWidth($select);
                        }, 0);
                    });
                }

                function bindFilterSearchWidthEvents($select) {
                    if (!$select.data('kidsCrmMsFilterMode')) {
                        return;
                    }

                    const $search = $select.next('.select2-container').find('.select2-search__field');
                    $search.off('input' + namespace + ' keyup' + namespace);
                    $search.on('input' + namespace + ' keyup' + namespace, function () {
                        scheduleSyncFilterInlineSearchWidth($select);
                    });
                }

                function syncSelectionSummary($select) {
                    const $container = $select.next('.select2-container');
                    if (!$container.length) {
                        return;
                    }

                    const filterMode = isFilterMode($select, {}) || !!$select.data('kidsCrmMsFilterMode');
                    const $rendered = $container.find('.select2-selection__rendered');
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
                        const summaryClasses = filterMode
                            ? 'select2-selection__choice kids-crm-generic-ms-summary kids-crm-filter-ms-summary'
                            : 'select2-selection__choice kids-crm-generic-ms-summary kids-crm-ms-chip kids-crm-ms-summary';

                        $rendered.prepend(
                            '<li class="' + summaryClasses + '">' +
                            summaryHtml +
                            '</li>'
                        );

                        if (window.KidsCrmTooltip) {
                            KidsCrmTooltip.init($container[0], { scopes: ['list'] });
                        }
                    }

                    if (filterMode) {
                        const selectedCount = ($select.val() || []).length;
                        $container.toggleClass('kids-crm-filter-ms-summary-mode', selectedCount >= 3);
                        $container.removeClass('kids-crm-ms-summary-mode');
                        scheduleSyncFilterInlineSearchWidth($select);
                        return;
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
                        scheduleSyncFilterInlineSearchWidth($select);
                    });

                    $select.on('select2:unselect' + namespace, function (e) {
                        resolveUnselectTarget($select, e.params)
                            .find('.kids-crm-generic-ms-option-check')
                            .removeClass('is-checked');

                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select, 0);
                        scheduleSyncFilterInlineSearchWidth($select);
                    });

                    $select.on('change' + namespace, function () {
                        syncSelectionSummary($select);
                        scheduleSyncDropdownCheckboxes($select);
                        scheduleSyncFilterInlineSearchWidth($select);
                    });

                    $select.on('select2:open' + namespace, function () {
                        bindSearchFieldKeepOpen($select);
                        bindFilterSearchWidthEvents($select);
                        scheduleSyncDropdownCheckboxes($select);
                        scheduleDropdownReposition($select);
                        scheduleSyncFilterInlineSearchWidth($select);

                        window.setTimeout(function () {
                            $select.next('.select2-container').find('.select2-search__field').trigger('focus');
                            scheduleSyncFilterInlineSearchWidth($select);
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
                        const filterMode = isFilterMode($select, options);

                        $select.data('kidsCrmMsFilterMode', filterMode);

                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: $select.data('placeholder') || options.placeholder || 'Выберите значения',
                            language: select2Language,
                            allowClear: options.allowClear !== false,
                            multiple: true,
                            closeOnSelect: false,
                            dropdownParent: $dropdownParent && $dropdownParent.length ? $dropdownParent : undefined,
                            containerCssClass: filterMode
                                ? 'kids-crm-generic-ms-select2 kids-crm-filter-ms-select2'
                                : 'kids-crm-generic-ms-select2',
                            selectionCssClass: filterMode
                                ? 'kids-crm-filter-ms-selection'
                                : 'kids-crm-ms-selection',
                            dropdownCssClass: 'kids-crm-generic-ms-dropdown',
                            templateResult: function (data) {
                                return formatOption(data, getSelectedIds($select));
                            }
                        });

                        bindEvents($select);
                        bindSearchFieldKeepOpen($select);
                        bindFilterSearchWidthEvents($select);
                        bindModalReposition($select, $dropdownParent);
                        syncSelectionSummary($select);
                        scheduleSyncFilterInlineSearchWidth($select);
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
