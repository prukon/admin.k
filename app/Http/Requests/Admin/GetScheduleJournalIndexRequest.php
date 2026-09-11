<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\AllowedActorTeam;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'regex:/^(0?[1-9]|1[0-2])$/'],
            'team' => ['nullable', 'string', 'max:32', 'regex:/^(all|none|[1-9][0-9]*)$/', new AllowedActorTeam($partnerId)],
            'q' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'integer', 'min:1'],
            'fullscreen' => ['nullable', 'in:0,1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $team = (string) ($this->input('team') ?? 'all');
            if ($team === 'all' || $team === 'none' || $team === '') {
                return;
            }

            $partnerId = (int) app(PartnerContext::class)->partnerId();
            $exists = \App\Models\Team::query()
                ->whereKey((int) $team)
                ->where('partner_id', $partnerId)
                ->exists();
            if (! $exists) {
                $validator->errors()->add('team', 'Выберите группу из списка.');
            }
        });
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
