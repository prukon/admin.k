<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\AllowedActorTeam;
use App\Services\PartnerContext;
use Illuminate\Validation\Validator;

class GetScheduleJournalGroupRowsRequest extends GetScheduleJournalIndexRequest
{
    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return array_merge(parent::rules(), [
            'group_key' => ['required', 'string', 'max:32', 'regex:/^(none|[1-9][0-9]*)$/', new AllowedActorTeam($partnerId)],
            'group_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'group_key' => 'группа',
            'group_page' => 'страница группы',
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'group_key.required' => 'Выберите группу из списка.',
            'group_key.regex' => 'Выберите группу из списка.',
            'group_page.integer' => 'Номер страницы группы должен быть числом.',
            'group_page.min' => 'Номер страницы группы должен быть не меньше 1.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $partnerId = (int) app(PartnerContext::class)->partnerId();
            $this->assertTeamExistsForPartner(
                $validator,
                (string) $this->input('group_key'),
                'group_key',
                $partnerId,
            );
        });
    }
}
