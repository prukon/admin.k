<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduleJournalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $team = $this->input('team');
        if ($team === '') {
            $this->merge(['team' => 'all']);
        }

        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'regex:/^(0?[1-9]|1[0-2])$/'],
            'team' => ['nullable', 'string', 'max:32', 'regex:/^(all|none|[1-9][0-9]*)$/'],
            'q' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'integer', 'min:1'],
            'fullscreen' => ['nullable', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'year' => 'год',
            'month' => 'месяц',
            'team' => 'группа',
            'q' => 'поиск',
            'page' => 'страница',
            'fullscreen' => 'полноэкранный режим',
        ];
    }

    public function messages(): array
    {
        return [
            'year.integer' => 'Год должен быть числом.',
            'year.min' => 'Год должен быть не раньше :min.',
            'year.max' => 'Год должен быть не позже :max.',
            'month.regex' => 'Выберите месяц из списка.',
            'team.regex' => 'Выберите группу из списка.',
            'q.max' => 'Поисковый запрос слишком длинный.',
            'page.integer' => 'Номер страницы должен быть числом.',
            'page.min' => 'Номер страницы должен быть не меньше 1.',
        ];
    }
}
