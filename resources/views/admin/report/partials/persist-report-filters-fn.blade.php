            function kidsCrmPersistReportFilters($form, url, data, onSuccess) {
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.find('.select2-selection').removeClass('is-invalid');
                $form.find('.payments-report-filter-error').remove();

                $.ajax({
                    url: url,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: data,
                    success: function () {
                        onSuccess();
                    },
                    error: function (xhr) {
                        var errors = xhr.responseJSON && xhr.responseJSON.errors;
                        if (!errors) {
                            return;
                        }
                        Object.keys(errors).forEach(function (field) {
                            var base = String(field).split('.')[0];
                            var message = errors[field] && errors[field][0] ? errors[field][0] : '';
                            if (!message) {
                                return;
                            }
                            var $input = $form.find('[name="' + base + '"], [name="' + base + '[]"]').first();
                            if ($input.length) {
                                $input.addClass('is-invalid');
                                var $selection = $input.next('.select2').find('.select2-selection');
                                if ($selection.length) {
                                    $selection.addClass('is-invalid');
                                }
                            }
                            var $slot = $form.find('[data-error-for="' + base + '"]');
                            if (!$slot.length) {
                                $slot = $('<div class="small text-danger mt-1 payments-report-filter-error" data-error-for="' + base + '"></div>');
                                var $host = $input.length ? $input.closest('[class*="col-"]') : $();
                                if (!$host.length && $input.length) {
                                    $host = $input.parent();
                                }
                                if ($host.length) {
                                    $host.append($slot);
                                }
                            }
                            if ($slot.length && !$slot.text()) {
                                $slot.text(message);
                            }
                        });
                    }
                });
            }
