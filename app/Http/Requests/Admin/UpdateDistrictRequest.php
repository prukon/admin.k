<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\DistrictLocationIdsRules;
use App\Models\District;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    use DistrictLocationIdsRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('districts.view');
    }

    protected function prepareForValidation(): void
    {
        $this->prepareDistrictLocationIds();
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        $district = $this->route('district');
        $districtId = $district instanceof District ? (int) $district->id : (int) $district;

        return array_merge([
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
        ], $this->districtLocationIdsRules());
    }

    public function attributes(): array
    {
        return array_merge([
            'name' => 'название',
            'sort_order' => 'сортировка',
            'is_enabled' => 'активность',
        ], $this->districtLocationIdsAttributes());
    }

    public function messages(): array
    {
        return array_merge([
            'name.required' => 'Введите название',
            'name.unique' => 'Район с таким названием уже существует',
        ], $this->districtLocationIdsMessages());
    }
}
