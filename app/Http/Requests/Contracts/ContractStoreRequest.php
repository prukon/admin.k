<?php

namespace App\Http\Requests\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ContractStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('group_id') && $this->input('group_id') === '') {
            $this->merge(['group_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = app('current_partner')?->id;

        return [
            'creation_mode' => [
                'required',
                'string',
                Rule::in([Contract::CREATION_MODE_PDF, Contract::CREATION_MODE_TEMPLATE]),
            ],
            'user_id' => ['required', 'integer', 'min:1'],
            'group_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
            'pdf'     => ['required_if:creation_mode,' . Contract::CREATION_MODE_PDF, 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'contract_template_id' => [
                'required_if:creation_mode,' . Contract::CREATION_MODE_TEMPLATE,
                'nullable',
                'integer',
                'min:1',
                Rule::exists('contract_templates', 'id')
                    ->where(fn ($q) => $q
                        ->where('partner_id', $partnerId)
                        ->where('is_archived', false)
                        ->whereNotNull('current_version_id')),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($afterValidator) {
            $partnerId = (int) (app('current_partner')?->id ?? 0);
            $userId = (int) $this->input('user_id', 0);

            if ($partnerId <= 0 || $userId <= 0) {
                return;
            }

            $student = User::query()
                ->whereKey($userId)
                ->where('partner_id', $partnerId)
                ->where('is_enabled', 1)
                ->with(['teams' => fn ($query) => $query->where('teams.partner_id', $partnerId)])
                ->first();

            if (! $student) {
                return;
            }

            $teamIds = app(TeamUserSyncService::class)->teamIdsForStudent($student);
            $groupId = $this->input('group_id');

            if ($teamIds === []) {
                if ($groupId !== null && $groupId !== '') {
                    $afterValidator->errors()->add(
                        'group_id',
                        'У ученика нет групп — поле «Группа» для договора не заполняется.'
                    );
                }

                return;
            }

            if (count($teamIds) === 1) {
                return;
            }

            if ($groupId === null || $groupId === '') {
                $afterValidator->errors()->add('group_id', 'Выберите группу для договора.');

                return;
            }

            if (! in_array((int) $groupId, $teamIds, true)) {
                $afterValidator->errors()->add('group_id', 'Выберите группу из списка групп ученика.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'creation_mode'          => 'Способ создания',
            'user_id'                => 'Ученик',
            'group_id'               => 'Группа',
            'pdf'                    => 'PDF-файл договора',
            'contract_template_id'   => 'Шаблон договора',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $params = ['create' => 1];

        $userId = (int) $this->input('user_id', 0);
        if ($userId > 0) {
            $params['user_id'] = $userId;
        }

        throw new HttpResponseException(
            redirect()
                ->route('contracts.index', $params)
                ->withErrors($validator)
                ->withInput()
        );
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.integer'  => 'Некорректный идентификатор ученика.',
            'user_id.min'      => 'Некорректный идентификатор ученика.',

            'group_id.integer' => 'Некорректный формат группы.',
            'group_id.min'     => 'Некорректный формат группы.',
            'group_id.exists'  => 'Выберите группу из списка.',

            'creation_mode.required' => 'Выберите способ создания договора.',
            'creation_mode.in'       => 'Некорректный способ создания договора.',

            'pdf.required_if' => 'Загрузите PDF-файл договора.',
            'pdf.file'        => 'Файл договора должен быть файлом.',
            'pdf.mimes'       => 'Файл договора должен быть в формате PDF.',
            'pdf.max'         => 'PDF-файл договора не должен превышать :max КБ.',

            'contract_template_id.required_if' => 'Выберите шаблон договора.',
            'contract_template_id.exists'      => 'Шаблон договора не найден или недоступен.',
        ];
    }
}
