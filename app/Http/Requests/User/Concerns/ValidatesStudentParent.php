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

        foreach (['parent_lastname', 'parent_firstname', 'parent_middlename'] as $key) {
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
        ];
    }
}
