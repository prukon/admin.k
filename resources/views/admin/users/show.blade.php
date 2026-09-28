<div class="modal-header">
    <div class="user-card-head">
        <div class="user-card-head-block user-card-identity">
            <img class="user-card-avatar" src="{{ $header['avatar'] }}" alt="" width="36" height="36">
            <div class="min-w-0">
                <h5 class="modal-title text-break" id="userCardModalLabel">{{ $header['name'] }}</h5>
                @if ($header['birthday'] !== '')
                    <div class="text-muted user-card-meta">{{ $header['birthday'] }}@if ($header['age'] !== '') · {{ $header['age'] }}@endif</div>
                @endif
            </div>
        </div>
        <div class="user-card-head-block user-card-presence">
            <div class="d-flex align-items-center justify-content-end gap-2">
                <span class="badge {{ $header['is_online'] ? 'bg-success' : 'bg-secondary' }}">{{ $header['presence_label'] }}</span>
                @if (! $header['is_online'] && $header['presence_seen'] !== '')
                    <span class="text-muted user-card-meta">{{ $header['presence_seen'] }}</span>
                @endif
            </div>
            @if ($loginHints['flags'] !== [] || $loginHints['device'] !== null)
                <div class="d-flex align-items-center justify-content-end gap-2">
                    @foreach ($loginHints['flags'] as $flag)
                        <span class="flag-icon flag-icon-{{ $flag['code'] }} user-card-login-flag" title="{{ $flag['label'] }}" aria-label="{{ $flag['label'] }}"></span>
                    @endforeach
                    @if ($loginHints['device'] === 'desktop')
                        <i class="fa-solid fa-desktop text-muted" title="{{ $loginHints['device_label'] }}" aria-label="{{ $loginHints['device_label'] }}"></i>
                    @elseif ($loginHints['device'] === 'mobile')
                        <i class="fa-solid fa-mobile-screen text-muted" title="{{ $loginHints['device_label'] }}" aria-label="{{ $loginHints['device_label'] }}"></i>
                    @elseif ($loginHints['device'] === 'tablet')
                        <i class="fa-solid fa-tablet-screen-button text-muted" title="{{ $loginHints['device_label'] }}" aria-label="{{ $loginHints['device_label'] }}"></i>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
</div>

@php
    $cardEmail = trim((string) ($student->email ?? ''));
    $canChangePassword = auth()->user()->can('users.password.update');
@endphp
<form id="user-card-form"
      method="post"
      action="{{ route('admin.user.update', $student) }}"
      data-payments-url="{{ $canViewPayments ? route('admin.user.payments-data', $student) : '' }}"
      data-contracts-url="{{ $canViewContracts ? route('admin.user.contracts-data', $student) : '' }}"
      data-logs-url="{{ route('admin.user.logs-data', $student) }}"
      data-emails-url="{{ $canViewEmails ? route('admin.user.emails-data', $student) : '' }}"
      data-password-url="{{ route('admin.user.password.update', $student) }}"
      data-welcome-url="{{ route('admin.user.send-welcome-credentials', $student) }}"
      data-delete-url="{{ route('admin.user.delete', $student) }}"
      data-email="{{ $cardEmail }}">
    @csrf
    @method('PATCH')

    <div class="modal-body py-2">
        <ul class="nav nav-tabs" id="userCardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="user-card-tab-info" data-bs-toggle="tab" data-bs-target="#user-card-pane-info" type="button" role="tab">Информация</button>
            </li>
            @if ($isStudent)
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="user-card-tab-family" data-bs-toggle="tab" data-bs-target="#user-card-pane-family" type="button" role="tab">Семья</button>
            </li>
            @endif
            @if ($canViewPayments)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="user-card-tab-payments" data-bs-toggle="tab" data-bs-target="#user-card-pane-payments" type="button" role="tab">Платежи</button>
                </li>
            @endif
            @if ($canViewContracts)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="user-card-tab-contracts" data-bs-toggle="tab" data-bs-target="#user-card-pane-contracts" type="button" role="tab">Договоры</button>
                </li>
            @endif
            @if ($canViewEmails)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="user-card-tab-emails" data-bs-toggle="tab" data-bs-target="#user-card-pane-emails" type="button" role="tab">Письма</button>
                </li>
            @endif
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="user-card-tab-logs" data-bs-toggle="tab" data-bs-target="#user-card-pane-logs" type="button" role="tab">История действий</button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom p-2" id="userCardTabContent">
            <div class="tab-pane fade show active" id="user-card-pane-info" role="tabpanel">
                <section class="user-card-block">
                    <div class="user-card-block__title">Анкетные данные</div>
                    <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label" for="user-card-lastname">Фамилия</label>
                    @if ($can['name'])
                        <input class="form-control" id="user-card-lastname" name="lastname" value="{{ $student->lastname }}" maxlength="30">
                        <div class="invalid-feedback" data-error-for="lastname"></div>
                    @else
                        <div>{{ $student->lastname !== '' && $student->lastname !== null ? $student->lastname : '—' }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user-card-name">Имя</label>
                    @if ($can['name'])
                        <input class="form-control" id="user-card-name" name="name" value="{{ $student->name }}" maxlength="30">
                        <div class="invalid-feedback" data-error-for="name"></div>
                    @else
                        <div>{{ $student->name !== '' && $student->name !== null ? $student->name : '—' }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user-card-middlename">Отчество</label>
                    @if ($can['name'])
                        <input class="form-control" id="user-card-middlename" name="middlename" value="{{ $student->middlename }}" maxlength="100">
                        <div class="invalid-feedback" data-error-for="middlename"></div>
                    @else
                        <div>{{ $student->middlename !== '' && $student->middlename !== null ? $student->middlename : '—' }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user-card-birthday">Дата рождения</label>
                    @if ($can['birthday'])
                        <input class="form-control" type="date" id="user-card-birthday" name="birthday" value="{{ $student->birthday?->format('Y-m-d') }}">
                        <div class="invalid-feedback" data-error-for="birthday"></div>
                    @else
                        <div>{{ $header['birthday'] !== '' ? $header['birthday'] : '—' }}</div>
                    @endif
                </div>
                @if ($can['sex'])
                    <div class="col-md-4">
                        <label class="form-label" for="user-card-sex">Пол</label>
                        <select class="form-select" id="user-card-sex" name="sex">
                            <option value="">Не указано</option>
                            <option value="male" @selected($student->sex === 'male')>Мужской</option>
                            <option value="female" @selected($student->sex === 'female')>Женский</option>
                        </select>
                        <div class="invalid-feedback" data-error-for="sex"></div>
                    </div>
                @endif
                <div class="col-md-4">
                    <label class="form-label" for="user-card-phone">Телефон</label>
                    @if ($can['phone'])
                        @include('includes.fields.phone-input', [
                            'name' => 'phone',
                            'id' => 'user-card-phone',
                            'value' => $student->phone,
                        ])
                        <div class="invalid-feedback" data-error-for="phone"></div>
                    @else
                        <div>{{ \App\Support\RuPhone::formatForInput($student->phone) !== '' ? \App\Support\RuPhone::formatForInput($student->phone) : '—' }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user-card-email">Email</label>
                    @if ($can['email'])
                        <input class="form-control" type="email" id="user-card-email" name="email" value="{{ $student->email }}">
                        <div class="invalid-feedback" data-error-for="email"></div>
                    @else
                        <div>{{ $student->email !== '' && $student->email !== null ? $student->email : '—' }}</div>
                    @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="user-card-address">Адрес проживания</label>
                    <textarea class="form-control" id="user-card-address" name="address" rows="2" maxlength="1000">{{ $student->address }}</textarea>
                    <div class="invalid-feedback" data-error-for="address"></div>
                </div>
                @if ($can['comment'])
                    <div class="col-md-4">
                        <label class="form-label" for="user-card-comment">Комментарий</label>
                        <textarea class="form-control" id="user-card-comment" name="comment" rows="2" maxlength="5000">{{ $student->comment }}</textarea>
                        <div class="invalid-feedback" data-error-for="comment"></div>
                    </div>
                @endif
                @if ($isStudent)
                <div class="col-12">
                    @if ($can['groups'])
                        <div class="generic-multiselect-field">
                            <label class="form-label" for="user-card-teams">Группы</label>
                            <select id="user-card-teams"
                                    name="team_ids[]"
                                    class="form-select js-generic-multiselect-select"
                                    multiple
                                    data-placeholder="Выберите группы">
                                @foreach ($teamOptions as $option)
                                    <option value="{{ $option['id'] }}" @selected(in_array($option['id'], $selectedTeamIds, true))>{{ $option['title'] }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" data-error-for="team_ids"></div>
                        </div>
                    @else
                        <div class="form-label">Группы</div>
                        @if ($groups === [])
                            <p class="text-muted mb-0">Групп нет</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Группа</th>
                                        <th>Объект</th>
                                        <th>Тренер</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($groups as $group)
                                        <tr>
                                            <td>
                                                @if ($canLinkTeams)
                                                    <a href="{{ route('admin.team.index') }}">{{ $group['title'] }}</a>
                                                @else
                                                    {{ $group['title'] }}
                                                @endif
                                            </td>
                                            <td>{{ $group['location'] }}</td>
                                            <td>{{ $group['trainers'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
                @endif
                @if ($customFields !== [])
                    @foreach ($customFields as $field)
                        <div class="col-md-4">
                            <label class="form-label" for="user-card-custom-{{ $field['slug'] }}">{{ $field['label'] }}</label>
                            @if ($field['field_type'] === 'text')
                                <textarea class="form-control" id="user-card-custom-{{ $field['slug'] }}" name="custom[{{ $field['slug'] }}]" rows="2" maxlength="255">{{ $field['raw'] }}</textarea>
                            @else
                                <input class="form-control" id="user-card-custom-{{ $field['slug'] }}" name="custom[{{ $field['slug'] }}]" value="{{ $field['raw'] }}" maxlength="255">
                            @endif
                            <div class="invalid-feedback" data-error-for="custom.{{ $field['slug'] }}"></div>
                        </div>
                    @endforeach
                @endif
                    </div>
                </section>

                @if ($isStudent)
                <section class="user-card-block">
                    <div class="user-card-block__title">Данные о здоровье</div>
                    <div class="user-card-health">
                    @foreach ([
                        'is_individual_traits' => 'Инд. особенности (физические, психологические)',
                        'is_on_medical_register' => 'Состоит на учёте у медицинских специалистов',
                        'is_with_disability' => 'Наличие инвалидности',
                    ] as $healthKey => $healthLabel)
                        <div class="form-check user-card-health__item mb-0">
                            @if ($can['health'])
                                <input type="hidden" name="{{ $healthKey }}" value="0">
                                <input class="form-check-input" type="checkbox" id="user-card-{{ $healthKey }}" name="{{ $healthKey }}" value="1" @checked($health[$healthKey] === '1')>
                            @else
                                <input class="form-check-input" type="checkbox" id="user-card-{{ $healthKey }}" disabled @checked($health[$healthKey] === '1')>
                            @endif
                            <label class="form-check-label" for="user-card-{{ $healthKey }}">{{ $healthLabel }}</label>
                            <div class="invalid-feedback" data-error-for="{{ $healthKey }}"></div>
                        </div>
                    @endforeach
                    </div>
                </section>
                @endif

                @if ($can['discount'])
                <section class="user-card-block">
                    <div class="user-card-block__title">Скидка</div>
                    <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" for="user-card-discount">Скидка, %</label>
                        <input class="form-control" type="number" min="0" max="100" step="1" id="user-card-discount" name="discount_percent" value="{{ $discountPercent >= 1 ? $discountPercent : '' }}">
                        <div class="invalid-feedback" data-error-for="discount_percent"></div>
                        <div class="d-none mt-2" id="user-card-discount-choice">
                            <div class="text-danger small" data-error-for="recalculate_unpaid_prices"></div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-user-card-recalc="0">Оставить цены</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-user-card-recalc="1">Пересчитать цены</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="user-card-discount-comment">Основание скидки</label>
                        <input class="form-control" id="user-card-discount-comment" name="discount_comment" maxlength="500" value="{{ $discountComment }}">
                        <div class="invalid-feedback" data-error-for="discount_comment"></div>
                    </div>
                    </div>
                </section>
                @endif

                <div class="user-card-activity">
                    <label class="form-label">Активность</label>
                    @if ($can['activity'])
                        <input type="hidden" name="is_enabled" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="user-card-enabled" name="is_enabled" value="1" @checked($student->is_enabled)>
                            <label class="form-check-label" for="user-card-enabled">Активен</label>
                        </div>
                        <div class="invalid-feedback" data-error-for="is_enabled"></div>
                    @else
                        <div>{{ $header['status_label'] }}</div>
                    @endif
                </div>
            </div>

            @if ($isStudent)
            <div class="tab-pane fade" id="user-card-pane-family" role="tabpanel">
                @php
                    $parentId = (int) ($parentFields['parent_id'] ?? 0);
                    $parentLabel = trim(implode(' ', array_filter([
                        $parentFields['parent_lastname'] ?? '',
                        $parentFields['parent_firstname'] ?? '',
                        $parentFields['parent_middlename'] ?? '',
                    ], fn ($part) => trim((string) $part) !== '')));
                    $parentHasFio = $parentLabel !== '';
                    $parentMode = $parentId > 0 || ! $parentHasFio ? 'directory' : 'new';
                @endphp
                <section class="user-card-block">
                    <div class="user-card-block__title">Родитель</div>
                    <div class="js-student-parent-fields"
                         data-parent-prefix="card"
                         data-has-parent-profiles="{{ $hasParentProfiles ? '1' : '0' }}"
                         data-parent-id="{{ $parentId > 0 ? $parentId : '' }}"
                         data-parent-lastname="{{ $parentFields['parent_lastname'] ?? '' }}"
                         data-parent-firstname="{{ $parentFields['parent_firstname'] ?? '' }}"
                         data-parent-middlename="{{ $parentFields['parent_middlename'] ?? '' }}"
                         data-parent-full-name-genitive="{{ $parentFields['parent_full_name_genitive'] ?? '' }}"
                         data-parent-passport="{{ $parentFields['parent_passport'] ?? '' }}"
                         data-parent-passport-issued="{{ $parentFields['parent_passport_issued'] ?? '' }}"
                         data-parent-address="{{ $parentFields['parent_address'] ?? '' }}"
                         data-parent-phone="{{ $parentFields['parent_phone'] ?? '' }}"
                         data-parent-email="{{ $parentFields['parent_email'] ?? '' }}">
                    @if ($hasParentProfiles)
                        <div class="js-parent-mode-toggle-wrap mb-2" data-parent-prefix="card">
                            <div class="parent-mode-segmented" role="group" aria-label="Способ указания родителя">
                                <button type="button"
                                        class="btn parent-mode-segmented__btn js-parent-mode-btn {{ $parentMode === 'directory' ? 'active' : '' }}"
                                        data-parent-prefix="card"
                                        data-mode="directory">
                                    Из справочника
                                </button>
                                <button type="button"
                                        class="btn parent-mode-segmented__btn js-parent-mode-btn {{ $parentMode === 'new' ? 'active' : '' }}"
                                        data-parent-prefix="card"
                                        data-mode="new">
                                    Новый родитель
                                </button>
                            </div>
                        </div>
                        <div class="js-parent-select-wrap {{ $parentMode === 'new' ? 'd-none' : '' }}" data-parent-prefix="card">
                            <div class="mb-2">
                                <label class="form-label" for="card-parent-id">Родитель в справочнике</label>
                                <select name="parent_id"
                                        id="card-parent-id"
                                        class="form-select js-parent-profile-select"
                                        data-parent-prefix="card"
                                        data-search-url="{{ route('admin.users.parents.search') }}">
                                    <option value=""></option>
                                    @if ($parentId > 0)
                                        <option value="{{ $parentId }}" selected>{{ $parentLabel !== '' ? $parentLabel : 'Родитель #'.$parentId }}</option>
                                    @endif
                                </select>
                                <div class="invalid-feedback" data-error-for="parent_id"></div>
                            </div>
                        </div>
                    @endif
                    <div class="js-parent-fio-section" data-parent-prefix="card">
                    <div class="row g-2">
                    @foreach ([
                        'parent_lastname' => ['Фамилия', 'card-parent-lastname', 'js-parent-lastname'],
                        'parent_firstname' => ['Имя', 'card-parent-firstname', 'js-parent-firstname'],
                        'parent_middlename' => ['Отчество', 'card-parent-middlename', 'js-parent-middlename'],
                        'parent_full_name_genitive' => ['ФИО в родительном падеже', 'card-parent-full-name-genitive', 'js-parent-full-name-genitive'],
                        'parent_phone' => ['Телефон', 'card-parent-phone', 'js-parent-phone'],
                        'parent_email' => ['Email', 'card-parent-email', 'js-parent-email'],
                        'parent_passport' => ['Паспорт', 'card-parent-passport', 'js-parent-passport'],
                        'parent_passport_issued' => ['Кем и когда выдан', 'card-parent-passport-issued', 'js-parent-passport-issued'],
                        'parent_address' => ['Адрес', 'card-parent-address', 'js-parent-address'],
                    ] as $parentKey => [$parentFieldLabel, $parentFieldId, $parentFieldClass])
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $parentFieldId }}">{{ $parentFieldLabel }}</label>
                            @if ($parentKey === 'parent_phone')
                                @include('includes.fields.phone-input', [
                                    'name' => 'parent_phone',
                                    'id' => $parentFieldId,
                                    'value' => $parentFields['parent_phone'] ?? '',
                                    'parentPhone' => true,
                                    'class' => $parentFieldClass,
                                    'attributes' => ['data-parent-prefix' => 'card'],
                                ])
                            @elseif (in_array($parentKey, ['parent_passport_issued', 'parent_address'], true))
                                <textarea class="form-control {{ $parentFieldClass }}" id="{{ $parentFieldId }}" name="{{ $parentKey }}" rows="2" data-parent-prefix="card">{{ $parentFields[$parentKey] ?? '' }}</textarea>
                            @else
                                <input class="form-control {{ $parentFieldClass }}" id="{{ $parentFieldId }}" name="{{ $parentKey }}" value="{{ $parentFields[$parentKey] ?? '' }}" data-parent-prefix="card" @if($parentKey === 'parent_email') type="email" @endif>
                            @endif
                            <div class="invalid-feedback" data-error-for="{{ $parentKey }}"></div>
                        </div>
                    @endforeach
                    </div>
                    </div>
                    </div>
                </section>

                <section class="user-card-block user-card-block--last">
                    <div class="user-card-block__title">Братья и сёстры</div>
                    @if ($siblings === [])
                        <p class="text-muted mb-0">Братьев и сестёр нет</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Имя</th>
                                    <th>Группы</th>
                                    <th>Статус</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($siblings as $sibling)
                                    <tr>
                                        <td><a href="{{ $sibling['url'] }}" class="js-open-user-card">{{ $sibling['name'] }}</a></td>
                                        <td>{{ $sibling['teams'] }}</td>
                                        <td>
                                            <span class="badge {{ $sibling['is_enabled'] ? 'bg-success' : 'bg-secondary' }}">{{ $sibling['status_label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
            @endif

            @if ($canViewPayments)
                <div class="tab-pane fade" id="user-card-pane-payments" role="tabpanel">
                    @if ($hasPayments)
                        <div class="table-responsive">
                            <table class="table table-striped w-100" id="user-card-payments-table">
                                <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Группа</th>
                                    <th>Тип</th>
                                    <th>Способ</th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Платежей нет</p>
                    @endif
                </div>
            @endif

            @if ($canViewContracts)
                <div class="tab-pane fade" id="user-card-pane-contracts" role="tabpanel">
                    @if ($hasContracts)
                        <div class="table-responsive">
                            <table class="table table-striped w-100" id="user-card-contracts-table">
                                <thead>
                                <tr>
                                    <th>Номер</th>
                                    <th>Статус</th>
                                    <th>Создан</th>
                                    <th>Подписан</th>
                                    <th></th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Договоров нет</p>
                    @endif
                </div>
            @endif

            @if ($canViewEmails)
                <div class="tab-pane fade" id="user-card-pane-emails" role="tabpanel">
                    @if ($hasEmails)
                        <div class="table-responsive">
                            <table class="table table-striped w-100" id="user-card-emails-table">
                                <thead>
                                <tr>
                                    <th>Отправлено</th>
                                    <th>Статус</th>
                                    <th>Кому</th>
                                    <th>Тема</th>
                                    <th>Ошибки</th>
                                    <th>Попытки</th>
                                    <th></th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Писем не было</p>
                    @endif
                </div>
            @endif

            <div class="tab-pane fade" id="user-card-pane-logs" role="tabpanel">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="user-card-hide-logins" checked>
                    <label class="form-check-label" for="user-card-hide-logins">Скрыть входы</label>
                    <div class="text-danger small d-none" id="user-card-hide-logins-error"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped w-100" id="user-card-logs-table">
                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Автор</th>
                            <th>Действие</th>
                            <th>Описание</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <div id="user-card-change-pass" class="d-none w-100">
            <div class="d-flex align-items-center gap-2">
                <div class="position-relative flex-grow-1">
                    <input type="password" id="user-card-new-password" class="form-control" placeholder="Новый пароль" autocomplete="new-password">
                    <span id="user-card-toggle-password" class="fa fa-fw fa-eye field-icon" role="button" aria-label="Показать пароль"></span>
                </div>
                <button type="button" id="user-card-apply-password" class="btn btn-primary">Применить</button>
                <button type="button" id="user-card-cancel-password" class="btn btn-danger">Отмена</button>
            </div>
            <div id="user-card-password-error" class="text-danger mt-2 d-none">Пароль должен быть не менее 8 символов</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button"
                    id="user-card-change-password"
                    class="btn btn-primary {{ $canChangePassword ? '' : 'opacity-50 pe-none' }}"
                    @unless($canChangePassword)
                        aria-disabled="true"
                        tabindex="-1"
                        title="Нет прав на изменение пароля"
                    @endunless
            >
                <i class="fa-solid fa-key me-1"></i> Изменить пароль
            </button>
            @if ($isStudent)
            <button type="button"
                    id="user-card-send-password"
                    class="btn btn-outline-primary {{ $cardEmail === '' ? 'd-none' : '' }}"
                    title="Сгенерировать новый пароль и отправить его на email ученика">
                <i class="fa-solid fa-envelope me-1"></i> Отправить новый пароль по почте
            </button>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <button type="button" id="user-card-delete" class="btn btn-danger">Удалить</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
            <button type="submit" class="btn btn-primary" id="user-card-save">Сохранить</button>
        </div>
    </div>
</form>
