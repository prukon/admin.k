<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PartnerWidget;
use App\Support\PartnerLandingSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolLeadLandingSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $widget = $this->resolvedWidget();

        return [
            'landing_slug' => [
                'required',
                'string',
                'min:' . PartnerLandingSlug::MIN_LENGTH,
                'max:' . PartnerLandingSlug::MAX_LENGTH,
                'regex:' . PartnerLandingSlug::validationRegex(),
                Rule::notIn(PartnerLandingSlug::RESERVED),
                Rule::unique('partner_widgets', 'landing_slug')->ignore($widget?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'landing_slug.required' => 'Укажите адрес страницы.',
            'landing_slug.min'      => 'Адрес страницы должен содержать не менее ' . PartnerLandingSlug::MIN_LENGTH . ' символов.',
            'landing_slug.max'      => 'Адрес страницы не должен быть длиннее ' . PartnerLandingSlug::MAX_LENGTH . ' символов.',
            'landing_slug.regex'    => 'Используйте только латинские буквы, цифры и дефис (например: fk-dinamo).',
            'landing_slug.not_in'   => 'Этот адрес зарезервирован системой, выберите другой.',
            'landing_slug.unique'   => 'Такой адрес уже занят другой организацией.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = PartnerLandingSlug::normalize(
            is_string($this->input('landing_slug')) ? $this->input('landing_slug') : null
        );

        $this->merge([
            'landing_slug' => $normalized,
        ]);
    }

    private function resolvedWidget(): ?PartnerWidget
    {
        $partnerId = app(\App\Services\PartnerContext::class)->partnerId();

        if (!$partnerId) {
            return null;
        }

        return PartnerWidget::query()
            ->where('partner_id', $partnerId)
            ->first();
    }
}
