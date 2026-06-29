<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserCustomPaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);
        $userId = (int) $this->input('user_id');
        $dateStart = (string) $this->input('date_start', '');
        $dateEnd = (string) $this->input('date_end', '');

        return [
            'user_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(function ($q) use ($partnerId) {
                    $q->where('partner_id', $partnerId);
                }),
            ],
            'team_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(function ($q) use ($partnerId) {
                    $q->where('partner_id', $partnerId)->whereNull('deleted_at');
                }),
            ],
            'date_start' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_end' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    fn () => $this->filled('date_start') && $this->filled('date_end'),
                    'after_or_equal:date_start'
                ),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'note' => [
                'nullable',
                'string',
                'max:255',
            ],
            // композитная уникальность по (user_id, date_start, date_end)
            'uniq_period' => [
                Rule::unique('user_custom_payment', 'id')->where(function ($q) use ($userId, $dateStart, $dateEnd) {
                    $q->where('user_id', $userId);
                    if ($dateStart !== '') {
                        $q->whereDate('date_start', $dateStart);
                    } else {
                        $q->whereNull('date_start');
                    }
                    if ($dateEnd !== '') {
                        $q->whereDate('date_end', $dateEnd);
                    } else {
                        $q->whereNull('date_end');
                    }
                }),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $partnerId = (int) (app('current_partner')->id ?? 0);
            $userId = (int) $this->input('user_id');
            $teamId = (int) $this->input('team_id');

            if ($userId <= 0 || $teamId <= 0 || $partnerId <= 0) {
                return;
            }

            $user = \App\Models\User::query()->find($userId);
            if (! $user) {
                return;
            }

            $belongs = \App\Support\UserPriceTeamMembership::studentBelongsToTeam(
                $user,
                $teamId,
                $partnerId,
            );

            if (! $belongs) {
                $validator->errors()->add('team_id', 'Ученик не состоит в выбранной группе.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'date_start' => 'дата начала',
            'date_end' => 'дата окончания',
            'amount' => 'сумма',
            'note' => 'комментарий',
            'uniq_period' => 'период',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.integer' => 'Некорректный ученик.',
            'user_id.exists' => 'Ученик не найден или недоступен в контексте текущего партнёра.',

            'team_id.required' => 'Выберите группу.',
            'team_id.integer' => 'Некорректная группа.',
            'team_id.exists' => 'Группа не найдена или недоступна в контексте текущего партнёра.',

            'date_start.date_format' => 'Дата начала должна быть в формате ГГГГ-ММ-ДД.',

            'date_end.date_format' => 'Дата окончания должна быть в формате ГГГГ-ММ-ДД.',
            'date_end.after_or_equal' => 'Дата окончания должна быть не раньше даты начала.',

            'amount.required' => 'Укажите сумму.',
            'amount.numeric' => 'Сумма должна быть числом.',
            'amount.min' => 'Сумма должна быть больше нуля.',
            'amount.max' => 'Сумма слишком большая.',

            'note.string' => 'Комментарий должен быть строкой.',
            'note.max' => 'Комментарий слишком длинный (максимум 255 символов).',

            'uniq_period.unique' => 'Дополнительный платеж на такой период для этого ученика уже существует.',
        ];
    }
}

