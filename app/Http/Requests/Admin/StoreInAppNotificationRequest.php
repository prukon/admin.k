<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\InAppNotification;
use App\Models\Partner;
use App\Services\InAppNotifications\InAppNotificationAudience;
use App\Services\InAppNotifications\InAppNotificationBodyHtml;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInAppNotificationRequest extends FormRequest
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

        $roleIds = $this->input('role_ids', []);
        if (! is_array($roleIds)) {
            $roleIds = [];
        }

        $this->merge([
            'all_partners' => $allPartners,
            'partner_ids' => array_values(array_unique(array_filter(array_map('intval', $partnerIds)))),
            'role_ids' => array_values(array_unique(array_filter(array_map('intval', $roleIds)))),
            'title' => is_string($this->input('title')) ? trim((string) $this->input('title')) : $this->input('title'),
            'body' => is_string($this->input('body')) ? trim((string) $this->input('body')) : $this->input('body'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['required', 'string', Rule::in(InAppNotification::CATEGORIES)],
            'all_partners' => ['required', 'boolean'],
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => ['integer', 'distinct', Rule::exists('partners', 'id')],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'ttl_preset' => ['required', 'string', Rule::in(InAppNotification::TTL_PRESETS)],
            'custom_expires_at' => [
                Rule::requiredIf(fn () => $this->input('ttl_preset') === InAppNotification::TTL_CUSTOM),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $allPartners = (bool) $this->input('all_partners');
            $partnerIds = array_map('intval', $this->input('partner_ids', []));

            if (! $allPartners && $partnerIds === []) {
                $validator->errors()->add('partner_ids', 'Выберите хотя бы одну школу или отметьте «Все школы».');
            }

            $body = (string) $this->input('body', '');
            if (InAppNotificationBodyHtml::isBlank($body)) {
                $validator->errors()->add('body', 'Укажите текст уведомления.');
            }

            $audience = app(InAppNotificationAudience::class);
            $allowed = $audience->allowedRoleIds(
                array_map('intval', $this->input('role_ids', [])),
                $allPartners ? [] : $partnerIds,
                $allPartners
            );

            $requested = array_map('intval', $this->input('role_ids', []));
            if ($allowed === [] || count($allowed) !== count($requested)) {
                $validator->errors()->add(
                    'role_ids',
                    $allPartners || count($partnerIds) !== 1
                        ? 'При рассылке в несколько школ можно выбрать только системные роли: ученик, администратор, тренер.'
                        : 'Выбрана недопустимая роль для этой школы.'
                );
            }

            if (! $allPartners && $partnerIds !== []) {
                $found = Partner::query()->whereIn('id', $partnerIds)->count();
                if ($found !== count($partnerIds)) {
                    $validator->errors()->add('partner_ids', 'Одна или несколько школ не найдены.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'title' => 'заголовок',
            'body' => 'текст',
            'category' => 'тип',
            'all_partners' => 'все школы',
            'partner_ids' => 'школы',
            'partner_ids.*' => 'школа',
            'role_ids' => 'роли',
            'role_ids.*' => 'роль',
            'ttl_preset' => 'срок жизни',
            'custom_expires_at' => 'дата окончания',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Укажите заголовок уведомления.',
            'title.max' => 'Заголовок слишком длинный (максимум 160 символов).',

            'body.required' => 'Укажите текст уведомления.',
            'body.max' => 'Текст слишком длинный (максимум 8000 символов).',

            'category.required' => 'Выберите тип уведомления.',
            'category.in' => 'Некорректный тип уведомления.',

            'role_ids.required' => 'Выберите хотя бы одну роль.',
            'role_ids.min' => 'Выберите хотя бы одну роль.',
            'role_ids.*.exists' => 'Выбрана несуществующая роль.',

            'partner_ids.*.exists' => 'Выбрана несуществующая школа.',

            'ttl_preset.required' => 'Выберите срок жизни уведомления.',
            'ttl_preset.in' => 'Некорректный срок жизни.',

            'custom_expires_at.required' => 'Укажите дату окончания показа.',
            'custom_expires_at.date' => 'Некорректная дата окончания.',
            'custom_expires_at.after_or_equal' => 'Дата окончания не может быть в прошлом.',
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     category: string,
     *     all_partners: bool,
     *     partner_ids: list<int>,
     *     role_ids: list<int>,
     *     ttl_preset: string,
     *     custom_expires_at: ?string
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'title' => trim((string) $data['title']),
            'body' => InAppNotificationBodyHtml::sanitize((string) $data['body']),
            'category' => (string) $data['category'],
            'all_partners' => (bool) $data['all_partners'],
            'partner_ids' => array_values(array_map('intval', $data['partner_ids'] ?? [])),
            'role_ids' => array_values(array_map('intval', $data['role_ids'] ?? [])),
            'ttl_preset' => (string) $data['ttl_preset'],
            'custom_expires_at' => $data['custom_expires_at'] ?? null,
        ];
    }
}
