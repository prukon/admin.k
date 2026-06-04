<?php

namespace App\Http\Requests\User\Concerns;

use App\Services\PartnerContext;
use Illuminate\Validation\Rule;

trait ValidatesStudentParent
{
    protected function prepareStudentParentForValidation(): void
    {
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }

        foreach (['parent_lastname', 'parent_firstname', 'parent_middlename', 'parent_passport', 'parent_passport_issued', 'parent_address', 'parent_phone', 'parent_email'] as $key) {
            if (!$this->has($key)) {
                continue;
            }
            $value = $this->input($key);
            if (!is_string($value)) {
                continue;
            }
            $trimmed = trim(preg_replace('/\s+/', ' ', $value));
            $this->merge([$key => $trimmed !== '' ? $trimmed : null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentParentRules(): array
    {
        $partnerId = app(PartnerContext::class)->partnerId();

        $rules = [
            'parent_id' => ['nullable', 'integer'],
            'parent_lastname'   => ['nullable', 'string', 'max:100'],
            'parent_firstname'  => ['nullable', 'string', 'max:100'],
            'parent_middlename' => ['nullable', 'string', 'max:100'],
            'parent_passport'   => ['nullable', 'string', 'max:100'],
            'parent_passport_issued' => ['nullable', 'string', 'max:500'],
            'parent_address'    => ['nullable', 'string', 'max:1000'],
            'parent_phone'      => ['nullable', 'string', 'max:32'],
            'parent_email'      => ['nullable', 'email', 'max:255'],
        ];

        if ($partnerId) {
            $rules['parent_id'][] = Rule::exists('parents', 'id')->where(
                fn ($query) => $query->where('partner_id', $partnerId)
            );
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function studentParentAttributes(): array
    {
        return [
            'parent_id'         => 'Родитель',
            'parent_lastname'   => 'Фамилия родителя',
            'parent_firstname'  => 'Имя родителя',
            'parent_middlename' => 'Отчество родителя',
            'parent_passport'   => 'Паспорт родителя',
            'parent_passport_issued' => 'Паспорт родителя, кем и когда выдан',
            'parent_address'    => 'Адрес родителя',
            'parent_phone'      => 'Телефон родителя',
            'parent_email'      => 'Email родителя',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function studentParentMessages(): array
    {
        return [
            'parent_id.integer' => 'Некорректный идентификатор родителя.',
            'parent_id.exists'  => 'Выбранный родитель не найден или недоступен.',
            'parent_lastname.string'   => 'Поле «:attribute» должно быть строкой.',
            'parent_lastname.max'      => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_firstname.string'  => 'Поле «:attribute» должно быть строкой.',
            'parent_firstname.max'     => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_middlename.string' => 'Поле «:attribute» должно быть строкой.',
            'parent_middlename.max'    => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_passport.string'   => 'Поле «:attribute» должно быть строкой.',
            'parent_passport.max'      => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_passport_issued.string' => 'Поле «:attribute» должно быть строкой.',
            'parent_passport_issued.max'    => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_address.string'    => 'Поле «:attribute» должно быть строкой.',
            'parent_address.max'       => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_phone.string'      => 'Поле «:attribute» должно быть строкой.',
            'parent_phone.max'         => 'Поле «:attribute» не должно превышать :max символов.',
            'parent_email.email'       => 'Поле «:attribute» должно быть действительным email.',
            'parent_email.max'         => 'Поле «:attribute» не должно превышать :max символов.',
        ];
    }
}
