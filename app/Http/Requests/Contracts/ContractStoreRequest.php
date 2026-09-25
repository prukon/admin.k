<?php

namespace App\Http\Requests\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use App\Services\Contracts\ContractCreationService;
use App\Services\Contracts\ContractLessonPackageBinder;
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

        if ($this->has('lesson_package_id') && $this->input('lesson_package_id') === '') {
            $this->merge(['lesson_package_id' => null]);
        }

        if (!$this->binder()->canBind($this->user())) {
            $this->merge(['lesson_package_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = app('current_partner')?->id;
        $canBindPackage = $this->binder()->canBind($this->user());

        $rules = [
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

        if ($canBindPackage) {
            $rules['lesson_package_id'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
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
                $afterValidator->errors()->add(
                    'group_id',
                    ContractCreationService::NO_STUDENT_GROUP_MESSAGE,
                );
            } elseif (count($teamIds) > 1) {
                if ($groupId === null || $groupId === '') {
                    $afterValidator->errors()->add('group_id', 'Выберите группу для договора.');
                } elseif (! in_array((int) $groupId, $teamIds, true)) {
                    $afterValidator->errors()->add('group_id', 'Выберите группу из списка групп ученика.');
                }
            }

            $this->validateLessonPackageSelection($afterValidator, $partnerId, $userId);
        });
    }

    public function attributes(): array
    {
        return [
            'creation_mode'          => 'Способ создания',
            'user_id'                => 'Ученик',
            'group_id'               => 'Группа',
            'lesson_package_id'      => 'Абонемент',
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

            'lesson_package_id.integer' => 'Некорректный идентификатор абонемента.',
            'lesson_package_id.min'     => 'Выберите абонемент.',

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

    private function validateLessonPackageSelection($afterValidator, int $partnerId, int $userId): void
    {
        $binder = $this->binder();
        if (!$binder->canBind($this->user())) {
            return;
        }

        $packageId = $this->input('lesson_package_id');
        $packageIdInt = ($packageId !== null && $packageId !== '') ? (int) $packageId : 0;

        if ($packageIdInt > 0) {
            $package = $binder->resolveSelectablePackage($partnerId, $packageIdInt, $userId);
            if ($package === null) {
                $afterValidator->errors()->add('lesson_package_id', 'Выберите абонемент из списка.');
            }
        }

        if ((string) $this->input('creation_mode') !== Contract::CREATION_MODE_TEMPLATE) {
            return;
        }

        $templateId = (int) $this->input('contract_template_id', 0);
        if ($templateId <= 0) {
            return;
        }

        $template = ContractTemplate::query()
            ->forPartner($partnerId)
            ->active()
            ->whereKey($templateId)
            ->with('currentVersion')
            ->first();

        if (!$template || !$binder->templateRequiresPackage($template)) {
            return;
        }

        if ($packageIdInt <= 0) {
            $afterValidator->errors()->add('lesson_package_id', 'Выберите абонемент.');
        }
    }

    private function binder(): ContractLessonPackageBinder
    {
        return app(ContractLessonPackageBinder::class);
    }
}
