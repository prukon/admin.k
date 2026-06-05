<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Запись — {{ $partner->landingDisplayName() }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #1d6f42;
            --brand-primary-dark: #155a35;
            --brand-bg: #f4f7f5;
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--brand-bg);
            color: #1a1a1a;
        }
        .page-header {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #fff;
            padding: 2.75rem 1rem 3.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(255, 255, 255, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 40%);
            pointer-events: none;
        }
        .page-header-inner {
            position: relative;
            z-index: 1;
            max-width: 640px;
            margin: 0 auto;
        }
        .landing-hero {
            margin-bottom: 1.5rem;
        }
        .landing-hero-title {
            margin: 0;
            line-height: 1.15;
        }
        .landing-hero-title-main {
            display: block;
            font-size: clamp(2rem, 6vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 16px rgba(0, 0, 0, 0.15);
        }
        .landing-hero-title-sub {
            display: block;
            margin-top: 0.45rem;
            font-size: clamp(1.05rem, 3.2vw, 1.35rem);
            font-weight: 500;
            line-height: 1.35;
            color: rgba(255, 255, 255, 0.92);
        }
        .landing-partner-name {
            display: inline-block;
            margin: 0 0 0.75rem;
            padding: 0.55rem 1.1rem;
            font-size: clamp(1rem, 2.8vw, 1.15rem);
            font-weight: 600;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            backdrop-filter: blur(4px);
        }
        .form-card {
            max-width: 720px;
            margin: -2rem auto 3rem;
            padding: 0 1rem;
        }
        .form-card-inner {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 2rem 1.75rem;
        }
        .section-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--brand-primary);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.35rem;
            border-bottom: 2px solid #e8f0eb;
        }
        .section-title:first-child {
            margin-top: 0;
        }
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        .required-mark {
            color: #dc3545;
        }
        .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .health-options .form-check {
            margin-bottom: 0.5rem;
        }
        .btn-submit {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            font-weight: 600;
            padding: 0.75rem;
        }
        .btn-submit:hover {
            background: var(--brand-primary-dark);
            border-color: var(--brand-primary-dark);
        }
        #successMessage {
            display: none;
            text-align: center;
            padding: 3rem 1rem;
        }
        #successMessage .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        #successBackBtn {
            margin-top: 1.5rem;
            min-width: 140px;
            font-weight: 600;
        }
        .consent-label {
            font-size: 0.9rem;
        }
        #teamSelect:disabled {
            background-color: #f8f9fa;
        }
        .team-info-block {
            display: none;
            margin-top: 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            background: #f8fafc;
        }
        .team-info-block.is-visible {
            display: block;
        }
        .team-info-block__header {
            padding: 0.75rem 1rem;
            background: #e8f0eb;
            font-weight: 600;
            color: var(--brand-primary-dark);
            font-size: 0.95rem;
        }
        .team-info-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .team-info-table th,
        .team-info-table td {
            padding: 0.65rem 1rem;
            border-top: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .team-info-table th {
            width: 42%;
            font-weight: 500;
            color: #475569;
            background: #fff;
        }
        .team-info-table td {
            color: #1a1a1a;
            background: #fff;
        }
        .team-info-table tr:first-child th,
        .team-info-table tr:first-child td {
            border-top: none;
        }
        .team-info-loading {
            padding: 1rem;
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<header class="page-header">
    <div class="page-header-inner">
        <div class="landing-hero">
            <h2 class="landing-hero-title">
                <span class="landing-hero-title-main">Записаться</span>
                <span class="landing-hero-title-sub">на регулярные тренировочные занятия</span>
            </h2>
        </div>

        <p class="landing-partner-name mb-0">{{ $partner->landingDisplayName() }}</p>
    </div>
</header>

<div class="form-card">
    <div class="form-card-inner">
        <div id="successMessage" role="alert">
            <div class="icon text-success">✓</div>
            <h2 class="h4">Заявка отправлена!</h2>
            <p class="text-muted mb-0">Мы свяжемся с вами в ближайшее время.</p>
            <button type="button" class="btn btn-outline-secondary" id="successBackBtn">Назад</button>
        </div>

        <form id="leadForm" novalidate>
            @csrf

            <h2 class="section-title">Законный представитель</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="parent_lastname" class="form-label">Фамилия <span class="required-mark">*</span></label>
                    <input type="text" name="parent_lastname" id="parent_lastname" class="form-control" autocomplete="family-name">
                    <div class="field-error" data-error-for="parent_lastname" style="display:none;"></div>
                </div>
                <div class="col-md-4">
                    <label for="parent_firstname" class="form-label">Имя <span class="required-mark">*</span></label>
                    <input type="text" name="parent_firstname" id="parent_firstname" class="form-control" autocomplete="given-name">
                    <div class="field-error" data-error-for="parent_firstname" style="display:none;"></div>
                </div>
                <div class="col-md-4">
                    <label for="parent_middlename" class="form-label">Отчество <span class="required-mark">*</span></label>
                    <input type="text" name="parent_middlename" id="parent_middlename" class="form-control" autocomplete="additional-name">
                    <div class="field-error" data-error-for="parent_middlename" style="display:none;"></div>
                </div>
                <div class="col-md-6">
                    <label for="parent_phone" class="form-label">Телефон <span class="required-mark">*</span></label>
                    @include('includes.fields.phone-input', [
                        'name' => 'parent_phone',
                        'id' => 'parent_phone',
                    ])
                    <div class="field-error" data-error-for="parent_phone" style="display:none;"></div>
                </div>
                <div class="col-md-6">
                    <label for="parent_email" class="form-label">Email <span class="required-mark">*</span></label>
                    <input type="email" name="parent_email" id="parent_email" class="form-control" autocomplete="email">
                    <div class="field-error" data-error-for="parent_email" style="display:none;"></div>
                </div>
            </div>

            <h2 class="section-title">Ребёнок</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="child_lastname" class="form-label">Фамилия <span class="required-mark">*</span></label>
                    <input type="text" name="child_lastname" id="child_lastname" class="form-control">
                    <div class="field-error" data-error-for="child_lastname" style="display:none;"></div>
                </div>
                <div class="col-md-4">
                    <label for="child_firstname" class="form-label">Имя <span class="required-mark">*</span></label>
                    <input type="text" name="child_firstname" id="child_firstname" class="form-control">
                    <div class="field-error" data-error-for="child_firstname" style="display:none;"></div>
                </div>
                <div class="col-md-4">
                    <label for="child_middlename" class="form-label">Отчество <span class="required-mark">*</span></label>
                    <input type="text" name="child_middlename" id="child_middlename" class="form-control">
                    <div class="field-error" data-error-for="child_middlename" style="display:none;"></div>
                </div>
                <div class="col-md-6">
                    <label for="child_birthday" class="form-label">Дата рождения <span class="required-mark">*</span></label>
                    <input type="date" name="child_birthday" id="child_birthday" class="form-control">
                    <div class="field-error" data-error-for="child_birthday" style="display:none;"></div>
                </div>
            </div>

            <h2 class="section-title">Особенности</h2>
            <p class="text-muted small mb-2">Пожалуйста, укажите, если у ребёнка есть особенности:</p>
            <div class="health-options">
                <div class="form-check">
                    <input type="checkbox" name="is_individual_traits" id="is_individual_traits" value="1" class="form-check-input">
                    <label for="is_individual_traits" class="form-check-label">
                        Индивидуальные особенности воспитанника (физические, психологические)
                    </label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_on_medical_register" id="is_on_medical_register" value="1" class="form-check-input">
                    <label for="is_on_medical_register" class="form-check-label">
                        Состоит на учёте у медицинских специалистов
                    </label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_with_disability" id="is_with_disability" value="1" class="form-check-input">
                    <label for="is_with_disability" class="form-check-label">
                        Наличие инвалидности
                    </label>
                </div>
            </div>

            <h2 class="section-title">Район и услуга</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="location_id" class="form-label">Район <span class="required-mark">*</span></label>
                    <select name="location_id" id="location_id" class="form-select">
                        <option value="">— Выберите район —</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                        @endforeach
                    </select>
                    <div class="field-error" data-error-for="location_id" style="display:none;"></div>
                </div>
                <div class="col-md-6">
                    <label for="team_id" class="form-label">Услуга</label>
                    <select name="team_id" id="team_id" class="form-select" disabled>
                        <option value="">— Сначала выберите район —</option>
                    </select>
                    <div class="field-error" data-error-for="team_id" style="display:none;"></div>
                </div>
            </div>

            <div id="teamInfoBlock" class="team-info-block" aria-live="polite">
                <div class="team-info-block__header" id="teamInfoTitle">Об услуге</div>
                <div id="teamInfoContent"></div>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" name="needs_contact_help" id="needs_contact_help" value="1" class="form-check-input">
                <label for="needs_contact_help" class="form-check-label">
                    Не могу определиться с секцией, прошу со мной связаться
                </label>
            </div>

            <div class="mt-3">
                <label for="comment" class="form-label">Комментарий</label>
                <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                <div class="field-error" data-error-for="comment" style="display:none;"></div>
            </div>

            <div class="form-check mt-4">
                <input type="checkbox" name="consent_accepted" id="consent_accepted" value="1" class="form-check-input">
                <label for="consent_accepted" class="form-check-label consent-label">
                    Подтверждаю своё согласие на обработку моих персональных данных
                    <span class="required-mark">*</span>
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

            <div class="field-error mt-2" data-error-for="form" style="display:none;"></div>

            <button type="submit" class="btn btn-success btn-submit w-100 mt-4" id="submitBtn">Отправить заявку</button>
        </form>
    </div>
</div>

@if ($recaptchaSiteKey)
    <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptchaSiteKey }}"></script>
@endif
<script>
(function () {
    var form = document.getElementById('leadForm');
    var submitBtn = document.getElementById('submitBtn');
    var successMessage = document.getElementById('successMessage');
    var successBackBtn = document.getElementById('successBackBtn');
    var locationSelect = document.getElementById('location_id');
    var teamSelect = document.getElementById('team_id');
    var needsContactHelp = document.getElementById('needs_contact_help');
    var submitUrl = @json($submitUrl);
    var teamsUrl = @json($teamsUrl);
    var teamInfoUrl = @json($teamInfoUrl);
    var recaptchaSiteKey = @json($recaptchaSiteKey);
    var teamInfoBlock = document.getElementById('teamInfoBlock');
    var teamInfoTitle = document.getElementById('teamInfoTitle');
    var teamInfoContent = document.getElementById('teamInfoContent');

    function fillTrackingFields() {
        var params = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (key) {
            var el = document.getElementById(key);
            if (el && params.get(key)) {
                el.value = params.get(key);
            }
        });
        document.getElementById('page_url').value = window.location.href;
        document.getElementById('referrer').value = document.referrer || '';
    }

    function clearErrors() {
        document.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    function showErrors(errors) {
        if (!errors) return;
        Object.keys(errors).forEach(function (field) {
            var el = document.querySelector('[data-error-for="' + field + '"]');
            if (el && errors[field] && errors[field][0]) {
                el.textContent = errors[field][0];
                el.style.display = 'block';
            }
        });
    }

    function resetTeamSelect(message) {
        teamSelect.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = message || '— Выберите услугу —';
        teamSelect.appendChild(opt);
        hideTeamInfo();
    }

    function hideTeamInfo() {
        if (!teamInfoBlock) {
            return;
        }
        teamInfoBlock.classList.remove('is-visible');
        if (teamInfoContent) {
            teamInfoContent.innerHTML = '';
        }
    }

    function renderTeamInfoTable(rows) {
        if (!teamInfoContent) {
            return;
        }

        var html = '<table class="team-info-table"><tbody>';
        rows.forEach(function (row) {
            html += '<tr><th scope="row">' + escapeHtml(row.label) + '</th><td>' + escapeHtml(row.value) + '</td></tr>';
        });
        html += '</tbody></table>';
        teamInfoContent.innerHTML = html;
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function loadTeamInfo() {
        if (!teamInfoBlock || needsContactHelp.checked) {
            hideTeamInfo();
            return;
        }

        var locationId = locationSelect.value;
        var teamId = teamSelect.value;

        if (!locationId || !teamId) {
            hideTeamInfo();
            return;
        }

        teamInfoBlock.classList.add('is-visible');
        teamInfoContent.innerHTML = '<div class="team-info-loading">Загрузка…</div>';

        fetch(teamInfoUrl + '?location_id=' + encodeURIComponent(locationId) + '&team_id=' + encodeURIComponent(teamId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (result) {
                if (!result.ok || !result.json.data) {
                    hideTeamInfo();
                    return;
                }

                if (teamInfoTitle && result.json.data.title) {
                    teamInfoTitle.textContent = result.json.data.title;
                }

                renderTeamInfoTable(result.json.data.rows || []);
                teamInfoBlock.classList.add('is-visible');
            })
            .catch(function () {
                hideTeamInfo();
            });
    }

    function updateTeamSelectState() {
        var hasLocation = locationSelect.value !== '';
        var helpChecked = needsContactHelp.checked;

        if (!hasLocation) {
            teamSelect.disabled = true;
            resetTeamSelect('— Сначала выберите район —');
            return;
        }

        if (helpChecked) {
            teamSelect.disabled = true;
            teamSelect.value = '';
            resetTeamSelect('— Свяжемся для выбора секции —');
            return;
        }

        teamSelect.disabled = false;
    }

    function loadTeams() {
        var locationId = locationSelect.value;
        updateTeamSelectState();

        if (!locationId || needsContactHelp.checked) {
            return;
        }

        resetTeamSelect('Загрузка…');
        teamSelect.disabled = true;

        fetch(teamsUrl + '?location_id=' + encodeURIComponent(locationId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (result) {
                resetTeamSelect('— Выберите услугу —');
                if (result.ok && result.json.data) {
                    result.json.data.forEach(function (team) {
                        var opt = document.createElement('option');
                        opt.value = team.id;
                        opt.textContent = team.title;
                        teamSelect.appendChild(opt);
                    });
                }
                updateTeamSelectState();
                hideTeamInfo();
            })
            .catch(function () {
                resetTeamSelect('Не удалось загрузить услуги');
                updateTeamSelectState();
            });
    }

    function submitWithToken(token) {
        var data = new FormData(form);
        if (token) {
            data.set('recaptcha_token', token);
        }
        if (needsContactHelp.checked) {
            data.delete('team_id');
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
                    document.querySelectorAll('.section-title').forEach(function (el) {
                        el.style.display = 'none';
                    });
                    successMessage.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
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
    updateTeamSelectState();

    if (successBackBtn) {
        successBackBtn.addEventListener('click', function () {
            window.location.href = window.location.pathname + window.location.search;
        });
    }

    locationSelect.addEventListener('change', loadTeams);
    needsContactHelp.addEventListener('change', function () {
        loadTeams();
        hideTeamInfo();
    });

    teamSelect.addEventListener('change', loadTeamInfo);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        if (!recaptchaSiteKey || typeof grecaptcha === 'undefined') {
            submitWithToken('');
            return;
        }

        grecaptcha.ready(function () {
            grecaptcha.execute(recaptchaSiteKey, { action: 'school_lead_landing' })
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
