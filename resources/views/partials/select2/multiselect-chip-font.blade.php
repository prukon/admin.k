@push('styles')
    <style>
        /* Общие теги multiselect (группы в /admin/locations, локации в /admin/teams) */
        .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection.select2-selection--multiple {
            min-height: calc(1.5em + 0.5rem + 2px) !important;
            padding: 0.2rem 1.75rem 0.2rem 0.45rem !important;
            font-size: 0.8125rem !important;
            line-height: 1.35 !important;
            border: 1px solid #e3e6ea !important;
            border-radius: 0.5rem !important;
            background: #fff !important;
        }

        .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 0.2rem !important;
            margin: 0 !important;
            padding: 0 !important;
            font-size: inherit !important;
            line-height: inherit !important;
        }

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-chip {
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
            border: 1px solid #b6d4fe !important;
            border-radius: 999px !important;
            box-shadow: none !important;
            cursor: default !important;
        }

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-chip.kids-crm-ms-summary {
            background: transparent !important;
            border: 0 !important;
            padding: 0 0.2rem 0 0 !important;
            font-weight: 500 !important;
            max-width: 100%;
        }

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-chip .select2-selection__choice__remove {
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

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-chip .select2-selection__choice__remove:hover {
            opacity: 0.9 !important;
            background-color: transparent !important;
        }

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-summary .select2-selection__choice__remove {
            display: none !important;
        }

        .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection .select2-selection__rendered > .select2-search.select2-search--inline {
            display: inline-flex !important;
            align-items: center !important;
            float: none !important;
            width: auto !important;
            max-width: 100%;
            flex: 1 1 3.5rem;
            min-width: 3.5rem;
            height: auto !important;
            margin: 0 !important;
        }

        .select2-container--bootstrap-5.kids-crm-ms-summary-mode .select2-selection.kids-crm-ms-selection .select2-selection__rendered > .select2-search.select2-search--inline {
            flex: 1 1 4rem;
            min-width: 4rem;
        }

        .select2-container--bootstrap-5 .select2-selection.kids-crm-ms-selection .select2-search--inline .select2-search__field {
            margin: 0 !important;
            padding: 0 !important;
            width: auto !important;
            min-width: 1.5rem;
            min-height: 1.1rem !important;
            height: auto !important;
            font-size: 0.8125rem !important;
            line-height: 1.35 !important;
            color: #212529 !important;
        }

        .select2-container--bootstrap-5.kids-crm-ms-summary-mode .select2-selection.kids-crm-ms-selection .select2-search--inline .select2-search__field {
            min-width: 4rem;
        }

        .select2-container--bootstrap-5.kids-crm-ms-summary-mode .select2-selection__choice:not(.kids-crm-ms-summary) {
            display: none !important;
        }

        .select2-container--bootstrap-5 li.select2-selection__choice.kids-crm-ms-summary .kids-hover-list-dropdown__trigger {
            font-size: 0.8125rem !important;
            line-height: 1.35 !important;
            color: #212529 !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function (window, $) {
            'use strict';

            if (window.KidsCrmMultiselectChipStyles) {
                return;
            }

            window.KidsCrmMultiselectChipStyles = {
                apply: function ($select, options) {
                    options = options || {};
                    if (!$select || !$select.length) {
                        return;
                    }

                    const summaryClass = options.summaryClass || 'kids-crm-ms-summary';
                    const $container = $select.next('.select2-container');
                    if (!$container.length) {
                        return;
                    }

                    $container
                        .find('.select2-selection--multiple')
                        .addClass('kids-crm-ms-selection');

                    $container.find('.select2-selection__choice').each(function () {
                        const $choice = $(this);
                        $choice.addClass('kids-crm-ms-chip');

                        if ($choice.hasClass(summaryClass)
                            || $choice.hasClass('kids-crm-generic-ms-summary')
                            || $choice.hasClass('teams-multiselect-summary')
                            || $choice.hasClass('locations-multiselect-summary')) {
                            $choice.addClass('kids-crm-ms-summary');
                        } else {
                            $choice.removeClass('kids-crm-ms-summary');
                        }
                    });

                    const selectedCount = ($select.val() || []).length;
                    $container.toggleClass('kids-crm-ms-summary-mode', selectedCount >= 3);
                }
            };
        })(window, window.jQuery);
    </script>
@endpush
