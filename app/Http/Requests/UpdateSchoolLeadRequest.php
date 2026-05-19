<?php

namespace App\Http\Requests;

use App\Enums\SchoolLeadStatus;
use App\Models\Location;
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
            'status' => [
                'nullable',
                'string',
                'in:' . implode(',', SchoolLeadStatus::values()),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];

        if ($this->user()?->can('locations.view')) {
            $rules['location_id'] = ['sometimes', 'nullable', 'integer'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $afterValidator) {
            if (!$this->user()?->can('locations.view') || !$this->has('location_id')) {
                return;
            }

            $locationId = $this->input('location_id');
            if ($locationId === null || $locationId === '') {
                return;
            }

            $partnerId = app(PartnerContext::class)->partnerId();
            if (!$partnerId) {
                $afterValidator->errors()->add('location_id', 'Текущий партнёр не определён.');

                return;
            }

            $location = Location::query()
                ->where('id', $locationId)
                ->where('partner_id', $partnerId)
                ->first();

            if (!$location) {
                $afterValidator->errors()->add(
                    'location_id',
                    'Выбранная локация не существует или принадлежит другому партнёру.'
                );

                return;
            }

            if (!$location->is_enabled) {
                $afterValidator->errors()->add(
                    'location_id',
                    'Нельзя назначить отключённую локацию.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'status'      => 'Статус',
            'comment'     => 'Комментарий',
            'location_id' => 'Локация',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'         => 'Недопустимый статус.',
            'comment.max'       => 'Комментарий слишком длинный.',
            'location_id.integer' => 'Поле «Локация» должно быть числом (ID локации).',
        ];
    }
}
