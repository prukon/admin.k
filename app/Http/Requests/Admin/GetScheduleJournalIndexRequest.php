<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Team;
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
        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }

        if ($this->exists('team_ids')) {
            $raw = $this->input('team_ids');
            if (! is_array($raw)) {
                $raw = ($raw === null || $raw === '') ? [] : [$raw];
            }
            $tokens = [];
            foreach ($raw as $value) {
                if (! is_scalar($value)) {
                    continue;
                }
                $token = trim((string) $value);
                if ($token === '' || $token === 'all') {
                    continue;
                }
                $tokens[] = $token;
            }
            $this->merge(['team_ids' => array_values(array_unique($tokens))]);

            return;
        }

        $team = $this->input('team');
        if ($team === null || $team === '' || $team === 'all') {
            $this->merge(['team' => 'all', 'team_ids' => []]);

            return;
        }

        $this->merge([
            'team' => is_scalar($team) ? trim((string) $team) : $team,
            'team_ids' => [is_scalar($team) ? trim((string) $team) : ''],
        ]);
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'regex:/^(0?[1-9]|1[0-2])$/'],
            'team' => ['nullable', 'string', 'max:32', 'regex:/^(all|none|[1-9][0-9]*)$/', new AllowedActorTeam($partnerId)],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['string', 'max:32', 'regex:/^(none|[1-9][0-9]*)$/', new AllowedActorTeam($partnerId)],
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

            $partnerId = (int) app(PartnerContext::class)->partnerId();
            $this->assertTeamExistsForPartner($validator, (string) ($this->input('team') ?? 'all'), 'team', $partnerId);

            foreach ((array) $this->input('team_ids', []) as $index => $token) {
                $this->assertTeamExistsForPartner(
                    $validator,
                    (string) $token,
                    'team_ids.'.$index,
                    $partnerId,
                    true,
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'year' => 'год',
            'month' => 'месяц',
            'team' => 'группа',
            'team_ids' => 'группы',
            'team_ids.*' => 'группа',
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
            'team_ids.*.regex' => 'Выберите группу из списка.',
            'q.max' => 'Поисковый запрос слишком длинный.',
            'page.integer' => 'Номер страницы должен быть числом.',
            'page.min' => 'Номер страницы должен быть не меньше 1.',
        ];
    }

    private function assertTeamExistsForPartner(
        Validator $validator,
        string $token,
        string $errorKey,
        int $partnerId,
        bool $copyToTeam = false,
    ): void {
        if ($token === 'all' || $token === 'none' || $token === '') {
            return;
        }

        if (! ctype_digit($token)) {
            return;
        }

        $exists = Team::query()
            ->whereKey((int) $token)
            ->where('partner_id', $partnerId)
            ->exists();
        if ($exists) {
            return;
        }

        $validator->errors()->add($errorKey, 'Выберите группу из списка.');
        if ($copyToTeam && $errorKey !== 'team' && ! $validator->errors()->has('team')) {
            $validator->errors()->add('team', 'Выберите группу из списка.');
        }
    }
}
