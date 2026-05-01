<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeamScheduleSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('date_end') === null || $this->input('date_end') === '') {
            $this->merge(['date_end' => '9999-12-31']);
        }
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'date_start' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['required', 'date_format:Y-m-d'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу',
            'weekday.required' => 'Укажите день недели',
            'time_start.required' => 'Укажите время начала',
            'time_end.required' => 'Укажите время окончания',
            'date_start.required' => 'Укажите дату начала периода',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $timeStart = (string) $this->input('time_start');
            $timeEnd = (string) $this->input('time_end');

            if ($timeStart !== '' && $timeEnd !== '' && $timeEnd <= $timeStart) {
                $v->errors()->add('time_end', 'Время окончания должно быть позже времени начала');
            }

            $dateStart = (string) $this->input('date_start');
            $dateEnd = (string) $this->input('date_end');

            if ($dateStart !== '' && $dateEnd !== '' && $dateEnd < $dateStart) {
                $v->errors()->add('date_end', 'Дата окончания периода должна быть не раньше даты начала');
            }
        });
    }
}

