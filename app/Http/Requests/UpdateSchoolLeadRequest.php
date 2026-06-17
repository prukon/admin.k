<?php

namespace App\Http\Requests;

use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLeadStatus;
use App\Models\Team;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSchoolLeadRequest extends FormRequest
{
    private const HEALTH_FIELDS = [
        'is_individual_traits',
        'is_on_medical_register',
        'is_with_disability',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('school_lead_status_id') && $this->input('school_lead_status_id') === '') {
            $this->merge(['school_lead_status_id' => null]);
        }

        if ($this->user()?->can('districts.view')) {
            if ($this->has('district_id') && $this->input('district_id') === '') {
                $this->merge(['district_id' => null]);
            }
        } else {
            $this->offsetUnset('district_id');
        }

        if ($this->user()?->can('locations.view')) {
            if ($this->has('location_id') && $this->input('location_id') === '') {
                $this->merge(['location_id' => null]);
            }
        } else {
            $this->offsetUnset('location_id');
        }

        if ($this->has('team_id') && $this->input('team_id') === '') {
            $this->merge(['team_id' => null]);
        }

        if ($this->has('child_birthday') && $this->input('child_birthday') === '') {
            $this->merge(['child_birthday' => null]);
        }

        foreach ([
            'parent_lastname',
            'parent_firstname',
            'parent_middlename',
            'parent_phone',
            'parent_email',
            'child_lastname',
            'child_firstname',
            'child_middlename',
        ] as $key) {
            if (!$this->has($key)) {
                continue;
            }

            $value = $this->input($key);
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim(preg_replace('/\s+/', ' ', $value));
            $this->merge([$key => $trimmed !== '' ? $trimmed : null]);
        }

        if ($this->user()?->can('users.other.update')) {
            foreach (self::HEALTH_FIELDS as $field) {
                if (!$this->has($field)) {
                    continue;
                }

                $value = $this->input($field);
                if ($value === '' || $value === null) {
                    $this->merge([$field => false]);

                    continue;
                }

                if (in_array((string) $value, ['0', '1'], true)) {
                    $this->merge([$field => $value === '1']);
                }
            }
        } else {
            foreach (self::HEALTH_FIELDS as $field) {
                $this->offsetUnset($field);
            }
        }
    }

    public function rules(): array
    {
        $partnerId = app(PartnerContext::class)->partnerId();

        $rules = [
            'school_lead_status_id' => [
                'nullable',
                'integer',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'parent_lastname'   => ['nullable', 'string', 'max:100'],
            'parent_firstname'  => ['nullable', 'string', 'max:100'],
            'parent_middlename' => ['nullable', 'string', 'max:100'],
            'parent_phone'      => ['nullable', 'string', 'max:50'],
            'parent_email'      => ['nullable', 'email', 'max:255'],
            'child_lastname'    => ['nullable', 'string', 'max:100'],
            'child_firstname'   => ['nullable', 'string', 'max:100'],
            'child_middlename'  => ['nullable', 'string', 'max:100'],
            'child_birthday'    => ['nullable', 'date'],
            'team_id'           => ['nullable', 'integer'],
        ];

        if ($this->user()?->can('districts.view')) {
            $rules['district_id'] = ['sometimes', 'nullable', 'integer'];
        }

        if ($this->user()?->can('locations.view')) {
            $rules['location_id'] = ['sometimes', 'nullable', 'integer'];
        }

        if ($this->user()?->can('users.other.update')) {
            foreach (self::HEALTH_FIELDS as $field) {
                $rules[$field] = ['sometimes', 'boolean'];
            }
        }

        if ($partnerId) {
            $rules['team_id'][] = Rule::exists('teams', 'id')->where(
                fn ($query) => $query->where('partner_id', $partnerId)
            );
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $afterValidator) {
            $partnerId = app(PartnerContext::class)->partnerId();
            if (!$partnerId) {
                return;
            }

            $lead = $this->route('schoolLead');
            if ($lead?->user_id) {
                $afterValidator->errors()->add(
                    'school_lead',
                    'Заявка уже привязана к клиенту и не может быть изменена.'
                );

                return;
            }

            if ($this->filled('school_lead_status_id')) {
                $statusExists = SchoolLeadStatus::query()
                    ->availableForPartner((int) $partnerId)
                    ->whereKey((int) $this->input('school_lead_status_id'))
                    ->exists();

                if (!$statusExists) {
                    $afterValidator->errors()->add(
                        'school_lead_status_id',
                        'Выбранный статус не существует или недоступен для этого партнёра.'
                    );
                }
            }

            $district = null;
            if ($this->user()?->can('districts.view') && $this->filled('district_id')) {
                $district = District::query()
                    ->where('id', $this->input('district_id'))
                    ->where('partner_id', $partnerId)
                    ->first();

                if (!$district) {
                    $afterValidator->errors()->add(
                        'district_id',
                        'Выбранный район не существует или принадлежит другому партнёру.'
                    );

                    return;
                }

                if (!$district->is_enabled) {
                    $afterValidator->errors()->add(
                        'district_id',
                        'Нельзя назначить отключённый район.'
                    );
                }
            }

            $location = null;
            if ($this->user()?->can('locations.view') && $this->filled('location_id')) {
                $location = Location::query()
                    ->where('id', $this->input('location_id'))
                    ->where('partner_id', $partnerId)
                    ->first();

                if (!$location) {
                    $afterValidator->errors()->add(
                        'location_id',
                        'Выбранный объект не существует или принадлежит другому партнёру.'
                    );

                    return;
                }

                if (!$location->is_enabled) {
                    $afterValidator->errors()->add(
                        'location_id',
                        'Нельзя назначить отключённый объект.'
                    );
                }
            }

            if ($this->filled('team_id')) {
                $teamExists = Team::query()
                    ->where('partner_id', $partnerId)
                    ->whereKey((int) $this->input('team_id'))
                    ->exists();

                if (!$teamExists) {
                    $afterValidator->errors()->add(
                        'team_id',
                        'Выбранная группа не существует или принадлежит другому партнёру.'
                    );
                }
            }

            if ($district !== null && $location !== null) {
                if ((int) $location->district_id !== (int) $district->id) {
                    $afterValidator->errors()->add(
                        'location_id',
                        'Выбранный объект не относится к выбранному району.'
                    );
                }
            } elseif ($district !== null && !$this->has('location_id')) {
                $lead = $this->route('schoolLead');
                $locationId = $lead?->location_id;

                if ($locationId) {
                    $existingLocation = Location::query()
                        ->where('partner_id', $partnerId)
                        ->whereKey($locationId)
                        ->first();

                    if ($existingLocation && (int) $existingLocation->district_id !== (int) $district->id) {
                        $afterValidator->errors()->add(
                            'district_id',
                            'Текущий объект заявки не относится к выбранному району.'
                        );
                    }
                }
            } elseif ($location !== null && !$this->has('district_id')) {
                $lead = $this->route('schoolLead');
                $districtId = $lead?->district_id;

                if ($districtId && (int) $location->district_id !== (int) $districtId) {
                    $afterValidator->errors()->add(
                        'location_id',
                        'Выбранный объект не относится к району заявки.'
                    );
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'school_lead_status_id'  => 'Статус',
            'comment'                => 'Комментарий',
            'district_id'            => 'Район',
            'location_id'            => 'Объект',
            'parent_lastname'        => 'Фамилия родителя',
            'parent_firstname'       => 'Имя родителя',
            'parent_middlename'      => 'Отчество родителя',
            'parent_phone'           => 'Телефон родителя',
            'parent_email'           => 'Email родителя',
            'child_lastname'         => 'Фамилия ученика',
            'child_firstname'        => 'Имя ученика',
            'child_middlename'       => 'Отчество ученика',
            'child_birthday'         => 'Дата рождения ученика',
            'team_id'                => 'Группа',
            'is_individual_traits'   => 'Индивидуальные особенности воспитанника',
            'is_on_medical_register' => 'Учёт у медицинских специалистов',
            'is_with_disability'     => 'Наличие инвалидности',
        ];
    }

    public function messages(): array
    {
        return [
            'school_lead_status_id.integer' => 'Поле «Статус» должно быть числом (ID статуса).',
            'comment.max'                     => 'Комментарий слишком длинный.',
            'district_id.integer'             => 'Поле «Район» должно быть числом (ID района).',
            'location_id.integer'             => 'Поле «Объект» должно быть числом (ID объекта).',
            'parent_email.email'              => 'Введите корректный email родителя.',
            'child_birthday.date'             => 'Некорректная дата рождения ученика.',
            'team_id.integer'                 => 'Некорректный формат группы.',
            'team_id.exists'                  => 'Выбранная группа не существует в базе.',
        ];
    }
}
