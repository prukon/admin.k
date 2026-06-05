<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оставить заявку</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 16px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: #fff;
        }
        .widget-form .form-label {
            font-weight: 500;
        }
        .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .consent-label {
            font-size: 0.9rem;
        }
        #successMessage {
            display: none;
        }
    </style>
</head>
<body>
<div class="widget-form">
    <div id="successMessage" class="alert alert-success" role="alert">
        Заявка отправлена! Мы свяжемся с вами в ближайшее время.
    </div>

    <form id="schoolLeadForm" novalidate>
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Имя</label>
            <input type="text" name="name" id="name" class="form-control" autocomplete="name">
            <div class="field-error" data-error-for="name" style="display:none;"></div>
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Телефон</label>
            @include('includes.fields.phone-input', [
                'name' => 'phone',
                'id' => 'phone',
            ])
            <div class="field-error" data-error-for="phone" style="display:none;"></div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="consent_accepted" id="consent_accepted" value="1" class="form-check-input">
            <label for="consent_accepted" class="form-check-label consent-label">
                Даю согласие на
                <a href="{{ $policyUrl }}" target="_blank" rel="noopener noreferrer">обработку персональных данных</a>
            </label>
            <div class="field-error" data-error-for="consent_accepted" style="display:none;"></div>
        </div>

        <input type="hidden" name="utm_source" id="utm_source" value="">
        <input type="hidden" name="utm_medium" id="utm_medium" value="">
        <input type="hidden" name="utm_campaign" id="utm_campaign" value="">
        <input type="hidden" name="utm_content" id="utm_content" value="">
        <input type="hidden" name="utm_term" id="utm_term" value="">
        <input type="hidden" name="page_url" id="page_url" value="">
        <input type="hidden" name="referrer" id="referrer" value="">

        <div class="field-error mb-2" data-error-for="form" style="display:none;"></div>

        <button type="submit" class="btn btn-success w-100" id="submitBtn">Отправить заявку</button>
    </form>
</div>

@if ($recaptchaSiteKey)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
@endif
<script>
(function () {
    var form = document.getElementById('schoolLeadForm');
    var submitBtn = document.getElementById('submitBtn');
    var successMessage = document.getElementById('successMessage');
    var submitUrl = @json($submitUrl);
    var recaptchaSiteKey = @json($recaptchaSiteKey);

    function fillTrackingFields() {
        var params = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (key) {
            var el = document.getElementById(key);
            if (el && params.get(key)) {
                el.value = params.get(key);
            }
        });

        try {
            if (window.parent && window.parent !== window) {
                document.getElementById('page_url').value = window.parent.location.href;
                document.getElementById('referrer').value = document.referrer || '';
            } else {
                document.getElementById('page_url').value = window.location.href;
                document.getElementById('referrer').value = document.referrer || '';
            }
        } catch (e) {
            document.getElementById('page_url').value = window.location.href;
            document.getElementById('referrer').value = document.referrer || '';
        }
    }

    function clearErrors() {
        document.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    function showErrors(errors) {
        if (!errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            var el = document.querySelector('[data-error-for="' + field + '"]');
            if (el && errors[field] && errors[field][0]) {
                el.textContent = errors[field][0];
                el.style.display = 'block';
            }
        });
    }

    function submitWithToken(token) {
        var data = new FormData(form);
        if (token) {
            data.set('recaptcha_token', token);
        }

        submitBtn.disabled = true;

        fetch(submitUrl, {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, status: response.status, json: json };
                });
            })
            .then(function (result) {
                if (result.ok) {
                    form.style.display = 'none';
                    successMessage.style.display = 'block';
                    return;
                }

                if (result.status === 422 && result.json.errors) {
                    showErrors(result.json.errors);
                } else {
                    var formError = document.querySelector('[data-error-for="form"]');
                    formError.textContent = result.json.message || 'Не удалось отправить заявку.';
                    formError.style.display = 'block';
                }
            })
            .catch(function () {
                var formError = document.querySelector('[data-error-for="form"]');
                formError.textContent = 'Ошибка сети. Попробуйте позже.';
                formError.style.display = 'block';
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    }

    fillTrackingFields();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        if (!recaptchaSiteKey || typeof grecaptcha === 'undefined') {
            submitWithToken('');
            return;
        }

        grecaptcha.ready(function () {
            grecaptcha.execute(recaptchaSiteKey, { action: 'school_lead_widget' })
                .then(function (token) {
                    submitWithToken(token);
                })
                .catch(function () {
                    var formError = document.querySelector('[data-error-for="form"]');
                    formError.textContent = 'Не удалось пройти проверку от спама. Обновите страницу.';
                    formError.style.display = 'block';
                });
        });
    });
})();
</script>
@include('includes.scripts.phone-inputmask', ['requireJquery' => true])
</body>
</html>
