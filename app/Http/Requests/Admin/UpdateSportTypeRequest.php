<?php

namespace App\Http\Requests\Admin;

use App\Models\SportType;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSportTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sport_types.manage');
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        $sportType = $this->route('sportType');
        $sportTypeId = $sportType instanceof SportType ? (int) $sportType->id : (int) $sportType;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sport_types', 'name')
                    ->where(function ($query) use ($partnerId) {
                        if ($partnerId > 0) {
                            $query->where('partner_id', $partnerId);
                        }
                    })
                    ->ignore($sportTypeId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'sort' => 'сортировка',
            'is_enabled' => 'активность',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите название',
            'name.unique' => 'Вид спорта с таким названием уже существует',
        ];
    }
}
