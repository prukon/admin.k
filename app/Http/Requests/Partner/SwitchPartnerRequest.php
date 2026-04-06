<?php

namespace App\Http\Requests\Partner;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SwitchPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PartnerContext::class)->isSuperAdmin($this->user());
    }

    public function rules(): array
    {
        return [
            'partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where(function ($query) {
                    $query->where('is_enabled', true)->whereNull('deleted_at');
                }),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'Партнёр',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_id.required' => 'Выберите партнёра.',
            'partner_id.integer'  => 'Некорректный идентификатор партнёра.',
            'partner_id.exists'   => 'Выбранный партнёр недоступен.',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            redirect()
                ->back()
                ->withErrors(['partner_id' => 'Недостаточно прав для переключения партнёра.'])
        );
    }
}

