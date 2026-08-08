<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\PartnerLegalEntityMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserLessonPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('auto_prolong_enabled')) {
            $this->merge([
                'auto_prolong_enabled' => $this->boolean('auto_prolong_enabled'),
            ]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);
        $multiLegalEntity = PartnerLegalEntityMode::isMultiEntity($partnerId);
        $userRoleId = (int) Role::query()
            ->where('name', 'user')
            ->where('is_sistem', true)
            ->value('id');

        return [
            'user_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('users', 'id')->where(function ($q) use ($partnerId, $userRoleId) {
                    $q->where('partner_id', $partnerId)
                        ->where('is_enabled', 1);
                    if ($userRoleId > 0) {
                        $q->where('role_id', $userRoleId);
                    }
                }),
            ],
            'lesson_package_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id'),
            ],
            'team_id' => array_values(array_filter([
                $multiLegalEntity ? 'required' : 'nullable',
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')),
            ])),
            'fee_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'auto_prolong_enabled' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $partnerId = (int) (app('current_partner')->id ?? 0);
            $userId = (int) $this->input('user_id');
            $teamId = (int) $this->input('team_id');
            $packageId = (int) $this->input('lesson_package_id');
            $autoProlong = $this->boolean('auto_prolong_enabled');

            if ($userId > 0) {
                $guard = app(\App\Services\LessonPackages\UserLessonPackageAutoProlongGuard::class);
                if ($guard->isBlocked($userId)) {
                    $validator->errors()->add('user_id', \App\Services\LessonPackages\UserLessonPackageAutoProlongGuard::BLOCK_REASON);
                }
            }

            if ($packageId > 0) {
                $package = \App\Models\LessonPackage::query()->find($packageId);
                if ($package && $package->isPostpay()) {
                    $validator->errors()->add(
                        'lesson_package_id',
                        'Абонемент «Постоплата» назначается только в разделе «Установка цен», без записи в назначениях.'
                    );
                }

                if ($autoProlong) {
                    if (! $package || (int) $package->partner_id !== $partnerId) {
                        $validator->errors()->add('auto_prolong_enabled', 'Нельзя включить автопролонгацию для выбранного абонемента.');
                    } elseif ((string) $package->schedule_type !== 'fixed') {
                        $validator->errors()->add(
                            'auto_prolong_enabled',
                            'Автопролонгация доступна только для фиксированных абонементов.'
                        );
                    } elseif ($userId > 0) {
                        $other = \App\Models\UserLessonPackage::query()
                            ->where('user_id', $userId)
                            ->where('auto_prolong_enabled', true)
                            ->exists();
                        if ($other) {
                            $validator->errors()->add(
                                'auto_prolong_enabled',
                                'У ученика уже есть абонемент с автопролонгацией. Сначала отключите его.'
                            );
                        }
                    }
                }
            }

            if ($teamId <= 0 || $userId <= 0 || $partnerId <= 0) {
                return;
            }

            $user = \App\Models\User::query()->find($userId);
            if (! $user) {
                return;
            }

            if (! \App\Support\UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                $validator->errors()->add('team_id', 'Ученик не состоит в выбранной группе.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'lesson_package_id' => 'абонемент',
            'team_id' => 'группа',
            'fee_amount' => 'стоимость для ученика',
            'auto_prolong_enabled' => 'автопролонгация',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Выберите ученика.',
            'user_id.exists' => 'Выберите активного ученика из списка.',

            'lesson_package_id.required' => 'Выберите абонемент.',
            'lesson_package_id.exists' => 'Абонемент не найден.',

            'team_id.required' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена или недоступна в контексте текущего партнёра.',

            'fee_amount.required' => 'Укажите стоимость абонемента для этого ученика.',
            'fee_amount.numeric' => 'Стоимость должна быть числом.',
            'fee_amount.min' => 'Стоимость не может быть отрицательной.',
            'fee_amount.max' => 'Слишком большая сумма.',
        ];
    }
}
