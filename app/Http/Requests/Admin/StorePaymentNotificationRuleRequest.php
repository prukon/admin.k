<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\PaymentNotificationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('setPrices.paymentNotifications.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'is_enabled' => ['sometimes', 'boolean'],
            'trigger_type' => ['required', 'string', Rule::in(PaymentNotificationRule::TRIGGER_TYPES)],
            'trigger_value' => ['required', 'integer', 'min:1', 'max:90'],
            'billing_month_offset' => ['nullable', 'integer', Rule::in([0, -1])],
            'schedule_types' => ['required', 'array', 'min:1'],
            'schedule_types.*' => ['required', 'string', Rule::in(PaymentNotificationRule::ALLOWED_SCHEDULE_TYPES)],
            'subject_template' => ['required', 'string', 'max:255'],
            'body_html_template' => ['required', 'string', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = (string) $this->input('trigger_type');
            $value = (int) $this->input('trigger_value');

            if ($type === PaymentNotificationRule::TRIGGER_DAY_OF_MONTH && ($value < 1 || $value > 31)) {
                $validator->errors()->add('trigger_value', 'Для дня месяца укажите число от 1 до 31.');
            }

            if ($type === PaymentNotificationRule::TRIGGER_DAYS_AFTER_OVERDUE && ($value < 1 || $value > 90)) {
                $validator->errors()->add('trigger_value', 'Для дней просрочки укажите число от 1 до 90.');
            }

            $types = $this->input('schedule_types');
            if (is_array($types)) {
                $normalized = array_values(array_unique(array_filter(array_map(
                    static fn ($v) => is_string($v) ? trim($v) : '',
                    $types
                ))));
                if ($normalized === []) {
                    $validator->errors()->add('schedule_types', 'Выберите хотя бы один тип абонемента.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_enabled')) {
            $this->merge([
                'is_enabled' => filter_var($this->input('is_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        if ($this->has('schedule_types') && is_string($this->input('schedule_types'))) {
            $decoded = json_decode((string) $this->input('schedule_types'), true);
            if (is_array($decoded)) {
                $this->merge(['schedule_types' => $decoded]);
            }
        }
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'is_enabled' => 'включено',
            'trigger_type' => 'тип триггера',
            'trigger_value' => 'значение триггера',
            'billing_month_offset' => 'месяц начисления',
            'schedule_types' => 'типы абонементов',
            'schedule_types.*' => 'тип абонемента',
            'subject_template' => 'тема письма',
            'body_html_template' => 'текст письма',
            'sort_order' => 'порядок',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название правила.',
            'name.max' => 'Название слишком длинное (максимум 160 символов).',

            'trigger_type.required' => 'Выберите тип триггера.',
            'trigger_type.in' => 'Некорректный тип триггера.',

            'trigger_value.required' => 'Укажите значение триггера.',
            'trigger_value.integer' => 'Значение триггера должно быть целым числом.',
            'trigger_value.min' => 'Значение триггера слишком маленькое.',
            'trigger_value.max' => 'Значение триггера слишком большое.',

            'billing_month_offset.in' => 'Месяц начисления: текущий или предыдущий.',

            'schedule_types.required' => 'Выберите хотя бы один тип абонемента.',
            'schedule_types.min' => 'Выберите хотя бы один тип абонемента.',
            'schedule_types.*.in' => 'Некорректный тип абонемента. Доступны: фиксированный, гибкий, постоплата.',

            'subject_template.required' => 'Укажите тему письма.',
            'subject_template.max' => 'Тема письма слишком длинная (максимум 255 символов).',

            'body_html_template.required' => 'Укажите текст письма.',
            'body_html_template.max' => 'Текст письма слишком длинный.',
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     is_enabled: bool,
     *     trigger_type: string,
     *     trigger_value: int,
     *     billing_month_offset: int,
     *     schedule_types: list<string>,
     *     subject_template: string,
     *     body_html_template: string,
     *     sort_order: int
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();
        $types = array_values(array_unique(array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', $data['schedule_types'] ?? []),
            static fn ($v) => $v !== '' && in_array($v, PaymentNotificationRule::ALLOWED_SCHEDULE_TYPES, true)
        ))));

        $triggerType = (string) $data['trigger_type'];
        $offset = (int) ($data['billing_month_offset'] ?? 0);
        if ($triggerType !== PaymentNotificationRule::TRIGGER_DAY_OF_MONTH) {
            $offset = 0;
        }

        return [
            'name' => trim((string) $data['name']),
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
            'trigger_type' => $triggerType,
            'trigger_value' => (int) $data['trigger_value'],
            'billing_month_offset' => $offset,
            'schedule_types' => $types,
            'subject_template' => (string) $data['subject_template'],
            'body_html_template' => (string) $data['body_html_template'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
