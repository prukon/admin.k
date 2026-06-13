<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Location;
use App\Services\PartnerContext;
use App\Support\PartnerAdminUserOptions;
use Illuminate\Validation\Rule;

trait LocationFieldRules
{
    protected function prepareLocationDistrictId(): void
    {
        if ($this->has('district_id') && $this->input('district_id') === '') {
            $this->merge(['district_id' => null]);
        }

        if ($this->has('admin_user_id') && $this->input('admin_user_id') === '') {
            $this->merge(['admin_user_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function locationFieldRules(?int $ignoreLocationId = null): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        $districtId = $this->input('district_id');
        if ($districtId === null || $districtId === '') {
            $normalizedDistrictId = null;
        } else {
            $normalizedDistrictId = (int) $districtId;
        }

        $nameUnique = Rule::unique('locations', 'name')
            ->where(function ($query) use ($partnerId, $normalizedDistrictId) {
                $query->where('partner_id', $partnerId);

                if ($normalizedDistrictId === null) {
                    $query->whereNull('district_id');
                } else {
                    $query->where('district_id', $normalizedDistrictId);
                }
            });

        if ($ignoreLocationId !== null) {
            $nameUnique->ignore($ignoreLocationId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $nameUnique],
            'district_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('districts', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'admin_user_id' => [
                'nullable',
                'integer',
                'min:1',
                $this->partnerSystemAdminUserExistsRule($partnerId),
            ],
        ];

        if ($this->user()?->can('locations.view')) {
            $rules['team_ids'] = ['nullable', 'array'];
            $rules['team_ids.*'] = [
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(function ($query) use ($partnerId) {
                    if ($partnerId > 0) {
                        $query->where('partner_id', $partnerId);
                    }
                }),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function locationFieldAttributes(): array
    {
        return [
            'name' => 'название',
            'district_id' => 'район',
            'admin_user_id' => 'администратор',
            'team_ids' => 'группы',
            'team_ids.*' => 'группа',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function locationFieldMessages(): array
    {
        return [
            'name.required' => 'Введите название',
            'name.unique' => 'Объект с таким названием уже существует в выбранном районе',
            'district_id.exists' => 'Выберите район из списка текущего партнёра',
            'admin_user_id.exists' => 'Выберите администратора из списка текущего партнёра',
            'team_ids.array' => 'Некорректный список групп',
            'team_ids.*.exists' => 'Выберите группу из списка текущего партнёра',
        ];
    }

    protected function resolveLocationIdForUpdate(): ?int
    {
        $location = $this->route('location');

        if ($location instanceof Location) {
            return (int) $location->id;
        }

        if (is_numeric($location)) {
            return (int) $location;
        }

        return null;
    }

    private function partnerSystemAdminUserExistsRule(int $partnerId): \Illuminate\Validation\Rules\Exists
    {
        $adminRoleId = PartnerAdminUserOptions::systemAdminRoleId();

        return Rule::exists('users', 'id')->where(function ($query) use ($partnerId, $adminRoleId) {
            $query->where('partner_id', $partnerId)
                ->where('is_enabled', 1);

            if ($adminRoleId !== null) {
                $query->where('role_id', $adminRoleId);
            } else {
                $query->whereRaw('1 = 0');
            }
        });
    }
}
