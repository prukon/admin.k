<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InAppNotificationComposeRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PartnerContext::class)->isSuperAdmin($user);
    }

    protected function prepareForValidation(): void
    {
        $allPartners = filter_var($this->input('all_partners'), FILTER_VALIDATE_BOOLEAN);
        $partnerIds = $this->input('partner_ids', []);
        if (! is_array($partnerIds)) {
            $partnerIds = [];
        }

        $this->merge([
            'all_partners' => $allPartners,
            'partner_ids' => array_values(array_unique(array_filter(array_map('intval', $partnerIds)))),
        ]);
    }

    public function rules(): array
    {
        return [
            'all_partners' => ['required', 'boolean'],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', Rule::exists('partners', 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'all_partners' => 'все школы',
            'partner_ids' => 'школы',
            'partner_ids.*' => 'школа',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_ids.*.exists' => 'Выбрана несуществующая школа.',
        ];
    }

    /**
     * @return array{all_partners: bool, partner_ids: list<int>}
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'all_partners' => (bool) $data['all_partners'],
            'partner_ids' => array_values(array_map('intval', $data['partner_ids'] ?? [])),
        ];
    }
}
