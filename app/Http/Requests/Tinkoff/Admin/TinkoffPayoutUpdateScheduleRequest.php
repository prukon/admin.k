<?php

namespace App\Http\Requests\Tinkoff\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class TinkoffPayoutUpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'when_to_run' => ['required', 'string', 'max:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $raw = (string) $this->input('when_to_run', '');
            if ($raw === '') {
                return;
            }
            $tz = (string) config('app.timezone', 'UTC');
            try {
                $dt = Carbon::parse($raw, $tz);
            } catch (\Throwable) {
                $v->errors()->add('when_to_run', 'Укажите корректную дату и время.');

                return;
            }
            if ($dt->lessThanOrEqualTo(now($tz))) {
                $v->errors()->add('when_to_run', 'Укажите время строго в будущем.');

                return;
            }
            if ($dt->greaterThan(now($tz)->addDays(366))) {
                $v->errors()->add('when_to_run', 'Слишком далёкая дата (не более 366 дней от сегодня).');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'when_to_run' => 'Запланирована',
        ];
    }

    public function messages(): array
    {
        return [
            'when_to_run.required' => 'Укажите дату и время.',
        ];
    }

    public function scheduledAt(): Carbon
    {
        $tz = (string) config('app.timezone', 'UTC');
        $raw = (string) $this->validated('when_to_run');

        return Carbon::parse($raw, $tz);
    }
}
