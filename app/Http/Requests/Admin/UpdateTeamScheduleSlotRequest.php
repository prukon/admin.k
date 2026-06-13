<?php

namespace App\Http\Requests\Admin;

use App\Models\TeamScheduleSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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

        if ($this->input('apply_changes_from') === null || $this->input('apply_changes_from') === '') {
            $this->merge(['apply_changes_from' => $this->input('date_start')]);
        }

        $locationId = $this->input('location_id');
        if ($locationId === '' || $locationId === 'none') {
            $this->merge(['location_id' => null]);
        }

        $slot = $this->route('slot');
        if ($slot instanceof TeamScheduleSlot) {
            $slotStart = Carbon::parse((string) $slot->date_start)->format('Y-m-d');
            $apply = (string) $this->input('apply_changes_from');
            if ($apply !== '' && Carbon::parse($apply)->gt(Carbon::parse($slotStart))) {
                // Левый сегмент сохраняет исходное начало периода; подставляем для валидации.
                $this->merge(['date_start' => $slotStart]);
            }
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
            'apply_changes_from' => ['required', 'date_format:Y-m-d'],
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
            'apply_changes_from.required' => 'Укажите дату, с которой применять изменения',
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
            $apply = (string) $this->input('apply_changes_from');

            $slot = $this->route('slot');
            if ($slot instanceof TeamScheduleSlot) {
                $slotStart = Carbon::parse((string) $slot->date_start)->format('Y-m-d');
                $slotEnd = Carbon::parse((string) $slot->date_end)->format('Y-m-d');

                if ($apply !== '') {
                    if (Carbon::parse($apply)->lt(Carbon::parse($slotStart))) {
                        $v->errors()->add(
                            'apply_changes_from',
                            'Дата не может быть раньше начала текущего периода слота.'
                        );
                    }
                    if (Carbon::parse($apply)->gt(Carbon::parse($slotEnd))) {
                        $v->errors()->add(
                            'apply_changes_from',
                            'Дата не может быть позже окончания периода слота.'
                        );
                    }
                }

                if ($apply !== '' && Carbon::parse($apply)->gt(Carbon::parse($slotStart))) {
                    if ($dateEnd !== '' && $dateEnd !== '9999-12-31' && $dateEnd < $apply) {
                        $v->errors()->add(
                            'date_end',
                            'Дата окончания нового периода не может быть раньше даты начала изменений.'
                        );
                    }
                }
            }

            if ($dateStart !== '' && $dateEnd !== '' && $dateEnd < $dateStart) {
                $v->errors()->add('date_end', 'Дата окончания периода должна быть не раньше даты начала');
            }

            $maxEnd = Carbon::today()->addDays(365)->format('Y-m-d');
            if ($dateEnd !== '' && $dateEnd !== '9999-12-31' && $dateEnd > $maxEnd) {
                $v->errors()->add('date_end', 'Дата окончания не может быть позже '.$maxEnd.' (не более чем на 365 дней от сегодняшней даты).');
            }
        });
    }
}

