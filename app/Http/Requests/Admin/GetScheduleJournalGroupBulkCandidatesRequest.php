<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

class GetScheduleJournalGroupBulkCandidatesRequest extends GetScheduleJournalGroupRowsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'occurrence_date' => ['required', 'date', 'date_format:Y-m-d'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'occurrence_date' => 'дата занятия',
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Укажите дату занятия.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = Carbon::createFromFormat('Y-m-d', (string) $this->input('occurrence_date'));
            if ($date === false) {
                return;
            }

            $year = (int) ($this->input('year') ?: date('Y'));
            $month = (int) ($this->input('month') ?: date('n'));
            if ((int) $date->year !== $year || (int) $date->month !== $month) {
                $validator->errors()->add('occurrence_date', 'Дата должна быть в выбранном месяце.');
            }
        });
    }
}
