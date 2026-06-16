{{-- Фильтр multiselect без Select2: вид как form-select, выбор через dropdown с чекбоксами. --}}
@once
    @push('styles')
        <style>
            .kids-crm-filter-ms {
                position: relative;
            }

            .kids-crm-filter-ms__trigger.form-select {
                display: flex;
                align-items: center;
                min-height: calc(1.5em + 0.75rem + 2px);
                padding-top: 0.375rem;
                padding-bottom: 0.375rem;
                cursor: pointer;
                user-select: none;
            }

            .kids-crm-filter-ms__trigger.form-select:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }

            .kids-crm-filter-ms__value {
                display: block;
                flex: 1 1 auto;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 0.8125rem;
                line-height: 1.35;
                color: #212529;
            }

            .kids-crm-filter-ms__value.is-placeholder {
                color: var(--bs-secondary-color, #6c757d);
            }

            .kids-crm-filter-ms__panel {
                position: absolute;
                top: calc(100% + 0.25rem);
                left: 0;
                right: 0;
                z-index: 1060;
                max-height: 16rem;
                overflow-y: auto;
                padding: 0.35rem;
                background: #fff;
            }

            .kids-crm-filter-ms__option {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                width: 100%;
                margin: 0;
                padding: 0.35rem 0.5rem;
                border-radius: 0.45rem;
                font-size: 0.8125rem;
                line-height: 1.3;
                color: #495057;
                cursor: pointer;
                box-sizing: border-box;
            }

            .kids-crm-filter-ms__option:hover {
                background: #f6f7f9;
            }

            .kids-crm-filter-ms__option-check {
                appearance: none;
                -webkit-appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 0.875rem;
                height: 0.875rem;
                margin: 0;
                padding: 0;
                flex: 0 0 0.875rem;
                border: 1px solid #b6d4fe;
                border-radius: 0.2rem;
                background: #fff;
                box-sizing: border-box;
                cursor: pointer;
            }

            .kids-crm-filter-ms__option-check:checked {
                position: relative;
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                border-color: #86b7fe;
            }

            .kids-crm-filter-ms__option-check:checked::after {
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

            .kids-crm-filter-ms__option-label {
                flex: 1 1 auto;
                min-width: 0;
                line-height: 1.3;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function ($) {
                'use strict';

                if (window.KidsCrmFilterMultiselectSelect2) {
                    return;
                }

                const namespace = '.kidsCrmFilterMs';

                function getState($select) {
                    return $select.data('kidsCrmFilterMs') || null;
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

                function syncLabel($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    const texts = $select.find('option:selected').map(function () {
                        return $(this).text();
                    }).get();

                    if (window.KidsCrmTooltip) {
                        KidsCrmTooltip.dispose(state.$trigger[0], { scopes: ['list'] });
                    }

                    state.$value.empty();

                    if (texts.length === 0) {
                        state.$value
                            .addClass('is-placeholder')
                            .text(state.placeholder);
                        return;
                    }

                    state.$value.removeClass('is-placeholder');
                    const summary = formatSelectionSummary(texts);

                    if (texts.length >= 3 && window.KidsCrmTooltip) {
                        state.$value.html(
                            KidsCrmTooltip.renderList(summary, texts, { minItemsForHover: 3 })
                        );
                        KidsCrmTooltip.init(state.$trigger[0], { scopes: ['list'] });
                    } else {
                        state.$value.text(summary);
                    }
                }

                function syncCheckboxes($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    const selected = ($select.val() || []).map(String);
                    state.$options.find('.kids-crm-filter-ms__option-check').each(function () {
                        const id = String($(this).val());
                        $(this).prop('checked', selected.includes(id));
                    });
                }

                function rebuildOptions($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    state.$options.empty();
                    $select.find('option').each(function () {
                        const $option = $(this);
                        const id = String($option.val());
                        const $row = $('<label class="kids-crm-filter-ms__option"></label>');
                        const $check = $('<input type="checkbox" class="kids-crm-filter-ms__option-check">')
                            .attr('value', id);
                        const $label = $('<span class="kids-crm-filter-ms__option-label"></span>')
                            .text($option.text());

                        $row.append($check, $label);
                        state.$options.append($row);
                    });

                    syncCheckboxes($select);
                }

                function closePanel($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    state.$panel.addClass('d-none');
                    state.$trigger.attr('aria-expanded', 'false');
                }

                function openPanel($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    state.$panel.removeClass('d-none');
                    state.$trigger.attr('aria-expanded', 'true');
                }

                function togglePanel($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    if (state.$panel.hasClass('d-none')) {
                        openPanel($select);
                    } else {
                        closePanel($select);
                    }
                }

                function bindDocumentClose($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    const docNs = namespace + '-doc-' + ($select.attr('id') || 'filter-ms');
                    $(document).off('mousedown' + docNs);

                    $(document).on('mousedown' + docNs, function (event) {
                        const $target = $(event.target);
                        if ($target.closest(state.$wrap).length) {
                            return;
                        }
                        closePanel($select);
                    });
                }

                function bindEvents($select) {
                    const state = getState($select);
                    if (!state) {
                        return;
                    }

                    state.$trigger.off(namespace);
                    state.$options.off(namespace);

                    state.$trigger.on('click' + namespace, function (event) {
                        event.preventDefault();
                        togglePanel($select);
                    });

                    state.$trigger.on('keydown' + namespace, function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            togglePanel($select);
                        }
                        if (event.key === 'Escape') {
                            closePanel($select);
                        }
                    });

                    state.$options.on('change' + namespace, '.kids-crm-filter-ms__option-check', function () {
                        const ids = state.$options
                            .find('.kids-crm-filter-ms__option-check:checked')
                            .map(function () {
                                return String($(this).val());
                            })
                            .get();

                        $select.val(ids.length ? ids : null);
                        syncLabel($select);
                        $select.trigger('change');
                    });

                    bindDocumentClose($select);
                }

                window.KidsCrmFilterMultiselectSelect2 = {
                    init: function ($select, options) {
                        options = options || {};

                        if (!$select.length || $select.data('kidsCrmFilterMs')) {
                            return;
                        }

                        const placeholder = $select.data('placeholder') || options.placeholder || 'Выберите значения';
                        const $wrap = $('<div class="kids-crm-filter-ms"></div>');
                        const $trigger = $('<div class="form-select kids-crm-filter-ms__trigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false"></div>');
                        const $value = $('<span class="kids-crm-filter-ms__value is-placeholder"></span>');
                        const $panel = $('<div class="kids-crm-filter-ms__panel border d-none" role="listbox"></div>');
                        const $options = $('<div class="kids-crm-filter-ms__options"></div>');

                        $trigger.append($value);
                        $panel.append($options);

                        $select.addClass('d-none').before($wrap);
                        $wrap.append($select, $trigger, $panel);

                        $select.data('kidsCrmFilterMs', {
                            placeholder: placeholder,
                            $wrap: $wrap,
                            $trigger: $trigger,
                            $value: $value,
                            $panel: $panel,
                            $options: $options
                        });

                        rebuildOptions($select);
                        syncLabel($select);
                        bindEvents($select);
                    },

                    rebuild: function ($select) {
                        rebuildOptions($select);
                        syncLabel($select);
                    },

                    setValues: function ($select, ids) {
                        if (!$select.length) {
                            return;
                        }

                        const values = (ids || []).map(String);
                        $select.val(values.length ? values : null);
                        syncCheckboxes($select);
                        syncLabel($select);
                        $select.trigger('change');
                    },

                    reset: function ($select) {
                        this.setValues($select, []);
                    },

                    clearInvalid: function ($select) {
                        const state = getState($select);
                        if (!state) {
                            return;
                        }

                        state.$trigger.removeClass('is-invalid');
                    },

                    markInvalid: function ($select) {
                        const state = getState($select);
                        if (!state) {
                            return;
                        }

                        state.$trigger.addClass('is-invalid');
                    }
                };
            })(window.jQuery);
        </script>
    @endpush
@endonce
