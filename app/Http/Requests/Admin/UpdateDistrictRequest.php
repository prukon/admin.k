<?php

namespace App\Http\Requests\Admin;

use App\Models\District;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('districts.view');
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        $district = $this->route('district');
        $districtId = $district instanceof District ? (int) $district->id : (int) $district;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')
                    ->where(function ($query) use ($partnerId) {
                        if ($partnerId > 0) {
                            $query->where('partner_id', $partnerId);
                        }
                    })
                    ->ignore($districtId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'название',
            'sort_order' => 'сортировка',
            'is_enabled' => 'активность',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите название',
            'name.unique' => 'Район с таким названием уже существует',
        ];
    }
}
