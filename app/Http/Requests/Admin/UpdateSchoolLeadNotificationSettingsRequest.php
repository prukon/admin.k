<?php

namespace App\Http\Requests\Admin;

use App\Services\SchoolLeadNotificationService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolLeadNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $disabled = (bool) $this->boolean('email_notifications_disabled');
        $maxEmails = SchoolLeadNotificationService::MAX_EMAILS;

        $emailsRules = [
            'nullable',
            'array',
            'max:' . $maxEmails,
        ];

        if (!$disabled) {
            $emailsRules = [
                'required',
                'array',
                'min:1',
                'max:' . $maxEmails,
            ];
        }

        return [
            'email_notifications_disabled' => [
                'required',
                'boolean',
            ],
            'emails' => $emailsRules,
            'emails.*' => [
                'required',
                'string',
                'email:filter',
                'max:255',
                'distinct',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'email_notifications_disabled' => 'Не получать email-уведомления',
            'emails'                       => 'Email для уведомлений',
            'emails.*'                     => 'Email',
        ];
    }

    public function messages(): array
    {
        $maxEmails = SchoolLeadNotificationService::MAX_EMAILS;

        return [
            'email_notifications_disabled.required' => 'Укажите, нужно ли получать email-уведомления.',
            'email_notifications_disabled.boolean'  => 'Поле «Не получать email-уведомления» должно быть да или нет.',
            'emails.required'                       => 'Укажите хотя бы один email для уведомлений.',
            'emails.min'                            => 'Укажите хотя бы один email для уведомлений.',
            'emails.max'                            => 'Можно указать не более ' . $maxEmails . ' email.',
            'emails.array'                          => 'Email для уведомлений должны быть списком адресов.',
            'emails.*.required'                     => 'Укажите email.',
            'emails.*.email'                        => 'Укажите корректный email.',
            'emails.*.max'                          => 'Email не должен быть длиннее :max символов.',
            'emails.*.distinct'                     => 'Email в списке не должны повторяться.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $disabled = filter_var(
            $this->input('email_notifications_disabled'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;

        $emails = $this->input('emails', []);
        if (!is_array($emails)) {
            $emails = ($emails === null || $emails === '') ? [] : [$emails];
        }

        $normalized = app(SchoolLeadNotificationService::class)
            ->normalizeEmailList($emails)
            ->all();

        $this->merge([
            'email_notifications_disabled' => $disabled,
            'emails'                       => $normalized,
        ]);
    }
}
