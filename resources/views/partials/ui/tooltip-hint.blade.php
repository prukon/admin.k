{{--
    Иконка-подсказка (Bootstrap Tooltip, стиль ulp-assignment-paid-tooltip).
    @include('partials.ui.tooltip-hint', ['title' => 'Текст подсказки', 'placement' => 'top'])
--}}
@php
    $hintTitle = trim((string) ($title ?? ''));
    $hintPlacement = in_array(($placement ?? 'top'), ['top', 'bottom', 'left', 'right'], true)
        ? $placement
        : 'top';
    $hintIconClass = trim((string) ($iconClass ?? 'fa fa-info-circle'));
@endphp

@if($hintTitle !== '')
    @once
        @push('styles')
            <style>
                .kids-tooltip-hint {
                    cursor: help;
                    color: #6c757d;
                    line-height: 1;
                    vertical-align: middle;
                }

                .kids-tooltip-hint:focus {
                    outline: none;
                }

                .tooltip.ulp-assignment-paid-tooltip .tooltip-inner {
                    max-width: min(22rem, 85vw);
                    text-align: left;
                    white-space: pre-wrap;
                    font-size: 0.8125rem;
                    line-height: 1.45;
                }
            </style>
        @endpush

        @push('scripts')
            <script>
                window.initKidsCrmTooltipHints = function (root) {
                    root = root || document;
                    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                        return;
                    }

                    root.querySelectorAll('[data-kids-tooltip-hint][data-bs-toggle="tooltip"]').forEach(function (el) {
                        const existing = bootstrap.Tooltip.getInstance(el);
                        if (existing) {
                            existing.dispose();
                        }

                        new bootstrap.Tooltip(el, {
                            placement: el.getAttribute('data-bs-placement') || 'top',
                            customClass: 'ulp-assignment-paid-tooltip',
                            trigger: 'hover focus',
                        });
                    });
                };

                document.addEventListener('DOMContentLoaded', function () {
                    window.initKidsCrmTooltipHints(document);
                });
            </script>
        @endpush
    @endonce

    <span class="kids-tooltip-hint d-inline-block ms-1"
          tabindex="0"
          data-kids-tooltip-hint
          data-bs-toggle="tooltip"
          data-bs-placement="{{ $hintPlacement }}"
          data-bs-custom-class="ulp-assignment-paid-tooltip"
          title="{{ $hintTitle }}"
          aria-label="{{ $hintTitle }}">
        <i class="{{ $hintIconClass }}" aria-hidden="true"></i>
    </span>
@endif
