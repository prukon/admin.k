<?php

namespace App\Http\Requests\User;

use App\Services\Users\CabinetTeamAttachService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttachCabinetTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('account.user.team.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'team_id' => 'новая группа',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Выберите группу.',
            'team_id.integer' => 'Некорректное значение поля «Новая группа».',
            'team_id.min' => 'Выберите группу.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            if (! $actor) {
                $validator->errors()->add('team_id', 'Необходима авторизация.');

                return;
            }

            /** @var CabinetTeamAttachService $service */
            $service = app(CabinetTeamAttachService::class);
            $student = $service->resolveEligibleStudent($actor);

            if ($student === null) {
                $validator->errors()->add(
                    'team_id',
                    'Добавление группы недоступно: у ученика нет группы с привязанным объектом.'
                );

                return;
            }

            $teamId = (int) $this->input('team_id');
            if (! $service->isTeamAllowedForStudent($student, $teamId)) {
                $validator->errors()->add(
                    'team_id',
                    'Выберите группу из списка объектов текущих групп ученика.'
                );
            }
        });
    }
}
