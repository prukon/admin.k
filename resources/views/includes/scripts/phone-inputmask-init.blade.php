@once('phone-inputmask-init')
<script>
(function ($) {
    'use strict';

    if (typeof $ === 'undefined' || !$.fn) {
        return;
    }

    var PHONE_MASK = '+7 (999) 999-99-99';
    var SELECTOR = '.js-phone-mask, .js-phone-mask-unmask, .js-contract-fill-phone, .js-parent-phone';

    var defaultOptions = {
        showMaskOnHover: false,
        clearIncomplete: false
    };

    var unmaskOptions = {
        showMaskOnHover: false,
        clearIncomplete: false,
        autoUnmask: true,
        removeMaskOnSubmit: true
    };

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function normalizeRuDigits(value) {
        var d = onlyDigits(value);
        if (!d) {
            return '';
        }
        if (d.length === 11 && d.charAt(0) === '8') {
            d = '7' + d.slice(1);
        } else if (d.length >= 11 && d.charAt(0) === '8') {
            d = '7' + d.slice(1, 11);
        }
        if (d.length === 10) {
            d = '7' + d;
        }
        if (d.length > 11 && d.charAt(0) === '7') {
            d = d.slice(0, 11);
        }
        return d;
    }

    function formatRuDisplay(value) {
        var d = normalizeRuDigits(value);
        if (d.length !== 11 || d.charAt(0) !== '7') {
            return String(value || '');
        }
        return '+7 (' + d.slice(1, 4) + ') ' + d.slice(4, 7) + '-' + d.slice(7, 9) + '-' + d.slice(9, 11);
    }

    function resolveOptions($input, options) {
        if (options) {
            return options;
        }
        if ($input.hasClass('js-phone-mask-unmask') || $input.hasClass('js-contract-fill-phone')) {
            return unmaskOptions;
        }
        return defaultOptions;
    }

    function buildMaskOptions($input, options) {
        var opts = resolveOptions($input, options ? Object.assign({}, options) : null);
        if (opts && opts.force) {
            delete opts.force;
        }

        var previousOnBeforeMask = opts.onBeforeMask;
        opts.onBeforeMask = function (value, maskOpts) {
            if (normalizeRuDigits(value).length === 11) {
                return formatRuDisplay(value);
            }
            if (typeof previousOnBeforeMask === 'function') {
                return previousOnBeforeMask(value, maskOpts);
            }
            return value;
        };

        return opts;
    }

    function prepareInputValue($input) {
        var raw = $input.val();
        var normalized = normalizeRuDigits(raw);
        if (normalized.length === 11) {
            $input.val(formatRuDisplay(normalized));
        }
    }

    window.PhoneInputMask = window.PhoneInputMask || {
        mask: PHONE_MASK,
        defaultOptions: defaultOptions,
        unmaskOptions: unmaskOptions,
        normalize: normalizeRuDigits,
        format: formatRuDisplay,

        remove: function (target) {
            var $el = target instanceof $ ? target : $(target);
            $el.each(function () {
                var $input = $(this);
                if ($input.data('inputmask')) {
                    $input.inputmask('remove');
                }
                $input.removeAttr('data-phone-mask-init');
            });
            return $el;
        },

        init: function (target, options) {
            if (!$.fn.inputmask) {
                return $(target);
            }

            var $el = target instanceof $ ? target : $(target);
            $el.each(function () {
                var $input = $(this);
                var force = !!(options && options.force);
                if (!force && $input.attr('data-phone-mask-init') === '1') {
                    return;
                }

                if ($input.data('inputmask')) {
                    $input.inputmask('remove');
                }

                prepareInputValue($input);

                var opts = buildMaskOptions($input, options ? Object.assign({}, options) : null);
                $input.inputmask(PHONE_MASK, opts || defaultOptions);
                $input.attr('data-phone-mask-init', '1');
            });

            return $el;
        },

        setValue: function (target, value) {
            var $el = target instanceof $ ? target : $(target);
            $el.each(function () {
                var $input = $(this);
                window.PhoneInputMask.remove($input);
                var display = formatRuDisplay(value);
                $input.val(display || (value || ''));
                window.PhoneInputMask.init($input, {force: true});
            });
            return $el;
        },

        initIn: function (root) {
            var $root = root ? $(root) : $(document);
            $root.find(SELECTOR).each(function () {
                window.PhoneInputMask.init(this);
            });
        },

        isComplete: function (target) {
            var $el = target instanceof $ ? target : $(target);
            if (!$el.length) {
                return false;
            }
            try {
                return $el.inputmask('isComplete');
            } catch (e) {
                return normalizeRuDigits($el.val()).length === 11;
            }
        },

        digits: function (target) {
            return normalizeRuDigits($(target).val());
        },

        formatDisplay: function (target) {
            var $el = target instanceof $ ? target : $(target);
            return $el.length ? String($el.val() || '') : '';
        }
    };

    $(function () {
        window.PhoneInputMask.initIn(document);
    });

    $(document).on('shown.bs.modal', function (event) {
        window.PhoneInputMask.initIn(event.target);
    });

    $(document).on('phone-inputmask:refresh', function (_event, root) {
        window.PhoneInputMask.initIn(root || document);
    });
})(window.jQuery);
</script>
@endonce
