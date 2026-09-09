<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Partner;
use App\Models\SchoolLead;
use App\Services\Contracts\ContractCreationService;
use App\Services\PartnerContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ValidatesLeadSendContract
{
    protected function prepareLeadSendContractForValidation(): void
    {
        if ($this->boolean('validate_only') || !$this->filled('school_lead_id') || !$this->boolean('send_contract')) {
            $this->merge(['send_contract' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function leadSendContractRules(): array
    {
        $partnerId = app(PartnerContext::class)->partnerId();
        $sendContract = $this->boolean('send_contract') && $this->filled('school_lead_id');

        $templateRules = [
            Rule::requiredIf($sendContract),
            'nullable',
            'integer',
            'min:1',
        ];

        if ($partnerId && $sendContract) {
            $templateRules[] = Rule::exists('contract_templates', 'id')
                ->where(fn ($query) => $query
                    ->where('partner_id', $partnerId)
                    ->where('is_archived', false)
                    ->whereNotNull('current_version_id'));
        }

        return [
            'send_contract' => ['sometimes', 'boolean'],
            'contract_template_id' => $templateRules,
            'validate_only' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function leadSendContractAttributes(): array
    {
        return [
            'send_contract' => 'Отправка договора',
            'contract_template_id' => 'Шаблон договора',
            'validate_only' => 'Только проверка данных',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function leadSendContractMessages(): array
    {
        return [
            'send_contract.boolean' => 'Некорректное значение выбора отправки договора.',
            'contract_template_id.required' => 'Выберите шаблон договора.',
            'contract_template_id.integer' => 'Некорректный идентификатор шаблона договора.',
            'contract_template_id.min' => 'Выберите шаблон договора.',
            'contract_template_id.exists' => 'Шаблон договора не найден или недоступен.',
            'validate_only.boolean' => 'Некорректное значение флага проверки данных.',
        ];
    }

    protected function validateLeadSendContract($validator): void
    {
        if (!$this->boolean('send_contract') || !$this->filled('school_lead_id')) {
            return;
        }

        if (trim((string) $this->input('parent_email', '')) === '') {
            return;
        }

        if (!$this->user()?->can('contracts.view')) {
            $validator->errors()->add(
                'send_contract',
                'Недостаточно прав для отправки договора.'
            );

            return;
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $partner = app('current_partner');
        if (!$partner instanceof Partner) {
            $validator->errors()->add('send_contract', 'Партнёр не выбран.');

            return;
        }

        $templateId = (int) $this->input('contract_template_id', 0);
        $groupId = $this->resolveLeadSendContractGroupId();

        try {
            app(ContractCreationService::class)->assertCanCreateTemplateContract(
                $partner,
                $templateId,
                $groupId,
                'send_contract',
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }

    private function resolveLeadSendContractGroupId(): ?int
    {
        $teamIds = $this->input('team_ids');
        if (is_array($teamIds)) {
            foreach ($teamIds as $teamId) {
                $id = (int) $teamId;
                if ($id > 0) {
                    return $id;
                }
            }
        }

        $teamId = (int) $this->input('team_id', 0);
        if ($teamId > 0) {
            return $teamId;
        }

        $leadId = (int) $this->input('school_lead_id', 0);
        $partnerId = app(PartnerContext::class)->partnerId();
        if ($leadId <= 0 || !$partnerId) {
            return null;
        }

        $leadTeamId = SchoolLead::query()
            ->whereKey($leadId)
            ->where('partner_id', $partnerId)
            ->value('team_id');

        return $leadTeamId ? (int) $leadTeamId : null;
    }
}
