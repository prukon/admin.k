@php
    $canUpdateLeadHealth = auth()->user() && auth()->user()->can('users.other.update');
    $canViewContracts = $canViewContracts ?? (auth()->user() && auth()->user()->can('contracts.view'));
    $studentRoleId = $studentRoleId ?? 0;
    $userFieldsPayload = $userFieldsPayload ?? [];
    $modalTeams = $filterTeams ?? ($allTeams ?? collect());
    $hasCustomFields = $canCreateUserFromLead && !empty($userFieldsPayload);
@endphp

<div class="modal fade" id="editLeadModal" tabindex="-1" aria-labelledby="editLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="me-2 min-w-0">
                    <h5 class="modal-title" id="editLeadModalLabel">Редактирование лида</h5>
                    <div id="leadNeedsContactHelpBadge" class="d-none mt-2">
                        <span class="badge bg-info text-dark">Просит помочь с выбором секции</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="editLeadForm" class="text-start" novalidate>
                    <input type="hidden" id="editLeadId" name="lead_id" value="">
                    @if ($canCreateUserFromLead && $studentRoleId)
                        <input type="hidden" name="role_id" id="leadRoleId" value="{{ (int) $studentRoleId }}">
                        <input type="hidden" name="is_enabled" value="1">
                    @endif

                    <div class="edit-lead-top-fields mb-3">
                        <div class="mb-3">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <label class="form-label mb-0" for="leadModalStatusTrigger">Статус</label>
                                <input type="hidden"
                                       id="leadStatus"
                                       name="school_lead_status_id"
                                       class="js-lead-field"
                                       value="">
                                <div class="lead-status-inline-picker lead-modal-status-picker"
                                     id="leadModalStatusPicker">
                                    <span class="badge bg-secondary lead-status-badge lead-status-inline-trigger"
                                          id="leadModalStatusTrigger"
                                          role="button"
                                          tabindex="0"
                                          title="Нажмите, чтобы изменить статус"
                                          aria-haspopup="listbox"
                                          aria-expanded="false"
                                          aria-label="Нажмите, чтобы изменить статус: —"
                                          data-status="">
                                        —
                                        <i class="fas fa-caret-down lead-status-inline-caret" aria-hidden="true"></i>
                                    </span>
                                    <div class="lead-status-inline-menu d-none"
                                         role="listbox"
                                         aria-label="Нажмите, чтобы изменить статус"></div>
                                </div>
                            </div>
                            <div class="invalid-feedback" data-field-error="school_lead_status_id"></div>
                        </div>

                        <div id="leadClientCreatedBadge"
                             class="alert alert-success py-2 px-3 mb-3 d-none small"
                             role="status">
                            На основании лида был создан клиент.
                        </div>
                        @if ($canViewContracts)
                            <div id="leadCreateContractWrap" class="mb-3 d-none">
                                <button type="button"
                                        id="leadCreateContractBtn"
                                        class="btn btn-sm btn-primary">
                                    Создать договор
                                </button>
                            </div>
                        @endif

                        <div class="mb-0">
                            <label for="leadComment" class="form-label">Комментарий</label>
                            <textarea id="leadComment"
                                      name="comment"
                                      class="form-control js-lead-field"
                                      rows="3"></textarea>
                            <div class="invalid-feedback" data-field-error="comment"></div>
                        </div>
                    </div>

                    <div class="accordion accordion-flush edit-lead-accordion" id="editLeadAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="editLeadAccordionStudentHeading">
                                <button class="accordion-button"
                                        type="button"
                                        data-bs-target="#editLeadAccordionStudent"
                                        aria-expanded="true"
                                        aria-controls="editLeadAccordionStudent">
                                    Ученик
                                </button>
                            </h2>
                            <div id="editLeadAccordionStudent"
                                 class="accordion-collapse collapse show"
                                 aria-labelledby="editLeadAccordionStudentHeading">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-4">
                                            <div class="mb-0">
                                                <label for="leadChildLastname" class="form-label">Фамилия ученика</label>
                                                <input type="text"
                                                       name="child_lastname"
                                                       class="form-control js-lead-field js-lead-child-name"
                                                       id="leadChildLastname"
                                                       maxlength="100">
                                                <div class="invalid-feedback" data-field-error="child_lastname"></div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="mb-0">
                                                <label for="leadChildFirstname" class="form-label">Имя ученика</label>
                                                <input type="text"
                                                       name="child_firstname"
                                                       class="form-control js-lead-field js-lead-child-name"
                                                       id="leadChildFirstname"
                                                       maxlength="100">
                                                <div class="invalid-feedback" data-field-error="child_firstname"></div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="mb-0">
                                                <label for="leadChildMiddlename" class="form-label">Отчество ученика</label>
                                                <input type="text"
                                                       name="child_middlename"
                                                       class="form-control js-lead-field"
                                                       id="leadChildMiddlename"
                                                       maxlength="100">
                                                <div class="invalid-feedback" data-field-error="child_middlename"></div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="mb-0">
                                                <label for="leadChildBirthday" class="form-label">Дата рождения</label>
                                                <input type="date"
                                                       name="child_birthday"
                                                       class="form-control js-lead-field"
                                                       id="leadChildBirthday">
                                                <div class="invalid-feedback" data-field-error="child_birthday"></div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="mb-0">
                                                <label for="leadTeam" class="form-label">Группа</label>
                                                <select id="leadTeam" name="team_id" class="form-select js-lead-field">
                                                    <option value="">Без группы</option>
                                                    @foreach ($modalTeams as $team)
                                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="invalid-feedback" data-field-error="team_id"></div>
                                            </div>
                                        </div>

                                        @if ($canViewDistricts)
                                            <div class="col-12 col-md-6">
                                                <div class="mb-0">
                                                    <label for="leadDistrict" class="form-label">Район</label>
                                                    <select id="leadDistrict" name="district_id" class="form-select js-lead-field">
                                                        <option value="">— не выбран —</option>
                                                        @foreach ($activeDistricts as $district)
                                                            <option value="{{ $district->id }}">{{ $district->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="invalid-feedback" data-field-error="district_id"></div>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($canViewLocations)
                                            <div class="col-12 col-md-6">
                                                <div class="mb-0">
                                                    <label for="leadLocation" class="form-label">Объект</label>
                                                    <select id="leadLocation" name="location_id" class="form-select js-lead-field">
                                                        <option value="">— не выбран —</option>
                                                        @foreach ($activeLocations as $location)
                                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="invalid-feedback" data-field-error="location_id"></div>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($canUpdateLeadHealth)
                                            @include('includes.modal._student_health_fields', [
                                                'prefix' => 'lead',
                                                'variant' => 'checkbox',
                                            ])
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="editLeadAccordionParentHeading">
                                <button class="accordion-button collapsed"
                                        type="button"
                                        data-bs-target="#editLeadAccordionParent"
                                        aria-expanded="false"
                                        aria-controls="editLeadAccordionParent">
                                    Родитель
                                </button>
                            </h2>
                            <div id="editLeadAccordionParent"
                                 class="accordion-collapse collapse"
                                 aria-labelledby="editLeadAccordionParentHeading">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        @include('admin.users._parent_form', [
                                            'prefix' => 'lead',
                                            'hideSectionTitle' => true,
                                            'parentLastname' => '',
                                            'parentFirstname' => '',
                                            'parentMiddlename' => '',
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($hasCustomFields)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="editLeadAccordionCustomHeading">
                                    <button class="accordion-button collapsed"
                                            type="button"
                                            data-bs-target="#editLeadAccordionCustom"
                                            aria-expanded="false"
                                            aria-controls="editLeadAccordionCustom">
                                        Дополнительные поля клиента
                                    </button>
                                </h2>
                                <div id="editLeadAccordionCustom"
                                     class="accordion-collapse collapse"
                                     aria-labelledby="editLeadAccordionCustomHeading">
                                    <div class="accordion-body">
                                        <div id="lead-custom-fields-container" class="row g-3">
                                            @foreach ($userFieldsPayload as $field)
                                                <div class="col-12 custom-field" data-slug="{{ $field['slug'] }}">
                                                    <div class="mb-0">
                                                        <label for="lead-custom-{{ $field['slug'] }}" class="form-label">{{ $field['name'] }}</label>
                                                        <input
                                                            type="text"
                                                            name="custom[{{ $field['slug'] }}]"
                                                            class="form-control js-lead-custom-field"
                                                            id="lead-custom-{{ $field['slug'] }}"
                                                            value=""
                                                            @unless(!empty($field['editable'])) disabled @endunless
                                                        />
                                                        <div class="invalid-feedback" data-field-error="custom.{{ $field['slug'] }}"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </form>

                <div class="alert alert-danger d-none mt-3" id="editLeadError"></div>
                <div class="alert alert-success d-none mt-3" id="editLeadSuccess"></div>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <div id="editLeadEditableActions" class="d-flex flex-wrap gap-2 ms-sm-auto">
                    <button type="button" class="btn btn-primary" id="saveLeadBtn">Сохранить изменения</button>
                    @if ($canCreateUserFromLead)
                        <span class="d-inline-block"
                              id="createClientBtnWrap"
                              data-kids-tooltip-hint
                              data-bs-toggle="tooltip"
                              data-bs-placement="top"
                              data-bs-custom-class="ulp-assignment-paid-tooltip"
                              title="Заполните имя и фамилию ученика">
                            <button type="button"
                                    class="btn btn-success"
                                    id="createClientBtn"
                                    disabled>
                                Создать клиента
                            </button>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        #editLeadModal .edit-lead-top-fields {
            padding-bottom: 0.25rem;
            border-bottom: 1px solid #dee2e6;
        }

        #editLeadModal .lead-modal-status-picker {
            max-width: 100%;
        }

        #editLeadModal .edit-lead-accordion {
            --bs-accordion-btn-padding-x: 0.75rem;
            --bs-accordion-btn-padding-y: 0.65rem;
            --bs-accordion-body-padding-x: 0.75rem;
            --bs-accordion-body-padding-y: 0.85rem;
        }

        #editLeadModal .edit-lead-accordion .accordion-item {
            border-right: 0;
            border-left: 0;
        }

        #editLeadModal .edit-lead-accordion .accordion-button {
            font-weight: 600;
            box-shadow: none;
        }

        #editLeadModal .edit-lead-accordion .accordion-button:not(.collapsed) {
            color: inherit;
            background-color: #f8f9fa;
        }

        #editLeadModal .edit-lead-accordion .accordion-collapse.collapse:not(.show) {
            display: none;
        }

        #editLeadModal .edit-lead-accordion .accordion-collapse.collapse.show {
            display: block;
        }

        #editLeadModal #createClientBtn:disabled {
            opacity: 1;
            pointer-events: none;
            color: #6c757d !important;
            background-color: #e9ecef !important;
            border-color: #ced4da !important;
        }

        #editLeadModal .invalid-feedback:not(:empty) {
            display: block;
        }

        #editLeadModal.lead-modal-readonly .js-lead-field,
        #editLeadModal.lead-modal-readonly .js-lead-custom-field,
        #editLeadModal.lead-modal-readonly .js-parent-profile-select,
        #editLeadModal.lead-modal-readonly .js-parent-lastname,
        #editLeadModal.lead-modal-readonly .js-parent-firstname,
        #editLeadModal.lead-modal-readonly .js-parent-middlename,
        #editLeadModal.lead-modal-readonly .js-parent-passport,
        #editLeadModal.lead-modal-readonly .js-parent-passport-issued,
        #editLeadModal.lead-modal-readonly .js-parent-address,
        #editLeadModal.lead-modal-readonly .js-parent-email,
        #editLeadModal.lead-modal-readonly .js-user-health-field,
        #editLeadModal.lead-modal-readonly .lead-modal-status-picker .lead-status-inline-trigger {
            pointer-events: none;
            background-color: #e9ecef;
        }

        #editLeadModal.lead-modal-readonly .js-lead-health-checkbox {
            opacity: 0.65;
        }

        #editLeadModal.lead-modal-readonly .js-parent-mode-btn {
            pointer-events: none;
            opacity: 0.65;
        }
    </style>
@endpush
