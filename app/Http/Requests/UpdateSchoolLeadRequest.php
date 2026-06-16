<?php

namespace App\Http\Requests;

use App\Models\District;
use App\Models\Location;
use App\Models\SchoolLeadStatus;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSchoolLeadRequest extends FormRequest
{
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
    }

    public function rules(): array
    {
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
        ];

        if ($this->user()?->can('districts.view')) {
            $rules['district_id'] = ['sometimes', 'nullable', 'integer'];
        }

        if ($this->user()?->can('locations.view')) {
            $rules['location_id'] = ['sometimes', 'nullable', 'integer'];
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
            'school_lead_status_id' => 'Статус',
            'comment'               => 'Комментарий',
            'district_id'           => 'Район',
            'location_id'           => 'Объект',
        ];
    }

    public function messages(): array
    {
        return [
            'school_lead_status_id.integer' => 'Поле «Статус» должно быть числом (ID статуса).',
            'comment.max'                   => 'Комментарий слишком длинный.',
            'district_id.integer'           => 'Поле «Район» должно быть числом (ID района).',
            'location_id.integer'           => 'Поле «Объект» должно быть числом (ID объекта).',
        ];
    }
}
