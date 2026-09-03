<style>
    #schoolLeadNotificationsModal {
        z-index: 1055;
    }
</style>

<div class="modal fade" id="schoolLeadNotificationsModal" tabindex="-1" aria-labelledby="schoolLeadNotificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="schoolLeadNotificationsForm"
                  method="POST"
                  action="{{ route('admin.school-leads.notifications.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolLeadNotificationsModalLabel">Уведомления о заявках</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="form-check mb-3">
                        <input class="form-check-input"
                               type="checkbox"
                               value="1"
                               id="schoolLeadEmailNotificationsDisabled"
                               name="email_notifications_disabled">
                        <label class="form-check-label" for="schoolLeadEmailNotificationsDisabled">
                            Не получать email-уведомления
                        </label>
                        <div class="invalid-feedback d-block" data-error-for="email_notifications_disabled"></div>
                    </div>

                    <div class="mb-0 generic-multiselect-field generic-multiselect-field--tags"
                         id="schoolLeadNotificationEmailsField">
                        <label class="form-label" for="schoolLeadNotificationEmails">Email для уведомлений</label>
                        <select id="schoolLeadNotificationEmails"
                                name="emails[]"
                                class="form-select js-generic-multiselect-select"
                                multiple
                                data-placeholder="Выберите или введите email"></select>
                        <div class="form-text">
                            Выберите email админов организации или введите другой адрес и нажмите Enter.
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="emails"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="schoolLeadNotificationsSaveBtn">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        $(function () {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var $modal = $('#schoolLeadNotificationsModal');
            var $form = $('#schoolLeadNotificationsForm');
            var $select = $('#schoolLeadNotificationEmails');
            var $emailsField = $('#schoolLeadNotificationEmailsField');
            var $disabled = $('#schoolLeadEmailNotificationsDisabled');
            var $saveBtn = $('#schoolLeadNotificationsSaveBtn');
            var routes = {
                show: @json(route('admin.school-leads.notifications.show')),
                update: @json(route('admin.school-leads.notifications.update'))
            };

            function isLikelyEmail(value) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
            }

            function emailSelect2Options() {
                return {
                    dropdownParent: $modal,
                    placeholder: $select.data('placeholder') || 'Выберите или введите email',
                    tags: true,
                    tokenSeparators: [','],
                    createTag: function (params) {
                        var term = $.trim(params.term || '');
                        if (!isLikelyEmail(term)) {
                            return null;
                        }
                        var email = term.toLowerCase();
                        return {
                            id: email,
                            text: email,
                            newTag: true
                        };
                    }
                };
            }

            function initEmailSelect2() {
                if (!$select.length || !window.KidsCrmGenericMultiselectSelect2) {
                    return;
                }
                KidsCrmGenericMultiselectSelect2.init($select, emailSelect2Options());
            }

            function clearNotificationErrors() {
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.find('[data-error-for]').text('');
                if (window.KidsCrmGenericMultiselectSelect2) {
                    KidsCrmGenericMultiselectSelect2.clearInvalid($select);
                }
            }

            function showNotificationErrors(errors) {
                clearNotificationErrors();
                if (!errors) {
                    return;
                }
                Object.keys(errors).forEach(function (field) {
                    var message = errors[field] && errors[field][0] ? errors[field][0] : '';
                    if (!message) {
                        return;
                    }
                    var key = field.indexOf('emails.') === 0 ? 'emails' : field;
                    if (key === 'emails' && window.KidsCrmGenericMultiselectSelect2) {
                        KidsCrmGenericMultiselectSelect2.markInvalid($select);
                    }
                    if (key === 'email_notifications_disabled') {
                        $disabled.addClass('is-invalid');
                    }
                    var $box = $form.find('[data-error-for="' + key + '"]');
                    if ($box.length && !$box.text()) {
                        $box.text(message);
                    }
                });
            }

            function syncEmailFieldVisibility() {
                var off = $disabled.is(':checked');
                $emailsField.toggleClass('d-none', off);
            }

            function rebuildEmailOptions(suggested, selected) {
                var seen = {};
                $select.empty();
                (suggested || []).forEach(function (item) {
                    var email = String(item.email || '').toLowerCase();
                    if (!email || !isLikelyEmail(email) || seen[email]) {
                        return;
                    }
                    seen[email] = true;
                    $select.append(new Option(item.label || email, email, false, false));
                });
                (selected || []).forEach(function (email) {
                    email = String(email || '').toLowerCase();
                    if (!email || seen[email]) {
                        return;
                    }
                    seen[email] = true;
                    $select.append(new Option(email, email, false, false));
                });
                initEmailSelect2();
                if (window.KidsCrmGenericMultiselectSelect2) {
                    KidsCrmGenericMultiselectSelect2.setValues($select, selected || []);
                }
            }

            function loadNotificationSettings() {
                clearNotificationErrors();
                $.ajax({
                    url: routes.show,
                    method: 'GET',
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                })
                    .done(function (data) {
                        $disabled.prop('checked', !!data.email_notifications_disabled);
                        rebuildEmailOptions(data.suggested_emails || [], data.emails || []);
                        syncEmailFieldVisibility();
                    })
                    .fail(function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Не удалось загрузить настройки уведомлений.';
                        if (typeof window.showToast === 'function') {
                            window.showToast(message, 'error');
                        }
                    });
            }

            $disabled.on('change', function () {
                syncEmailFieldVisibility();
            });

            $modal.on('shown.bs.modal', function () {
                loadNotificationSettings();
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                clearNotificationErrors();
                $saveBtn.prop('disabled', true);

                $.ajax({
                    url: routes.update,
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: {
                        emails: $select.val() || [],
                        email_notifications_disabled: $disabled.is(':checked') ? 1 : 0
                    }
                })
                    .done(function (data) {
                        if (typeof window.showToast === 'function') {
                            window.showToast(data.message || 'Настройки уведомлений сохранены.', 'success');
                        }
                        var modalEl = $modal.get(0);
                        if (modalEl && typeof bootstrap !== 'undefined') {
                            var instance = bootstrap.Modal.getInstance(modalEl);
                            if (instance) {
                                instance.hide();
                            }
                        }
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        if (body.errors) {
                            showNotificationErrors(body.errors);
                        }
                        var message = body.message || 'Не удалось сохранить настройки уведомлений.';
                        if (typeof window.showToast === 'function' && !body.errors) {
                            window.showToast(message, 'error');
                        }
                    })
                    .always(function () {
                        $saveBtn.prop('disabled', false);
                    });
            });
        });
    </script>
@endpush
