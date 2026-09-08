{{-- Общий Select2 multiselect для модалок: чекбоксы в dropdown, chip-теги, сводка при 3+ выбранных.
     CSS: resources/css/generic-multiselect.css (на /admin/school-leads ещё @import в school-leads-table.css). --}}
@include('partials.select2.multiselect-chip-font')

@once
    @push('styles')
        <style>{!! file_get_contents(resource_path('css/generic-multiselect.css')) !!}</style>
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
