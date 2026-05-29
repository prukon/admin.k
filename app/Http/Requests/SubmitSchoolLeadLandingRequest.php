<?php

namespace App\Http\Requests;

use App\Models\PartnerWidget;
use App\Models\Team;
use App\Services\TeamLocationAvailabilityService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SubmitSchoolLeadLandingRequest extends FormRequest
{
    private ?PartnerWidget $resolvedWidget = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'parent_lastname',
            'parent_firstname',
            'parent_middlename',
            'child_lastname',
            'child_firstname',
            'child_middlename',
        ] as $key) {
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

        $this->merge([
            'is_individual_traits'   => $this->boolean('is_individual_traits'),
            'is_on_medical_register' => $this->boolean('is_on_medical_register'),
            'is_with_disability'     => $this->boolean('is_with_disability'),
            'needs_contact_help'     => $this->boolean('needs_contact_help'),
        ]);

        if ($this->boolean('needs_contact_help')) {
            $this->merge(['team_id' => null]);
        }

        if ($this->has('team_id') && $this->input('team_id') === '') {
            $this->merge(['team_id' => null]);
        }

        if ($this->has('location_id') && $this->input('location_id') === '') {
            $this->merge(['location_id' => null]);
        }

        if ($this->has('sport_type_id') && $this->input('sport_type_id') === '') {
            $this->merge(['sport_type_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = $this->partnerId();

        $locationRule = ['required', 'integer'];
        $sportTypeRule = ['nullable', 'integer'];
        $teamRule = ['nullable', 'integer'];

        if ($partnerId) {
            $locationRule[] = Rule::exists('locations', 'id')->where(function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId)
                    ->where('is_enabled', true);
            });
            $sportTypeRule[] = Rule::exists('sport_types', 'id')->where(function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId)
                    ->where('is_enabled', true);
            });
            $teamRule[] = Rule::exists('teams', 'id')->where(function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId)
                    ->where('is_enabled', true);
            });
        }

        return [
            'parent_lastname'        => ['required', 'string', 'max:100'],
            'parent_firstname'       => ['required', 'string', 'max:100'],
            'parent_middlename'      => ['required', 'string', 'max:100'],
            'parent_phone'           => ['required', 'string', 'max:50', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'parent_email'           => ['required', 'email', 'max:255'],

            'child_lastname'         => ['required', 'string', 'max:100'],
            'child_firstname'        => ['required', 'string', 'max:100'],
            'child_middlename'       => ['required', 'string', 'max:100'],
            'child_birthday'         => ['required', 'date', 'before:today'],

            'is_individual_traits'   => ['boolean'],
            'is_on_medical_register' => ['boolean'],
            'is_with_disability'     => ['boolean'],

            'location_id'            => $locationRule,
            'sport_type_id'          => $sportTypeRule,
            'team_id'                => $teamRule,
            'needs_contact_help'     => ['boolean'],

            'comment'                => ['nullable', 'string', 'max:5000'],
            'consent_accepted'       => ['required', 'accepted'],
            'recaptcha_token'        => ['required', 'string'],

            'utm_source'             => ['nullable', 'string', 'max:255'],
            'utm_medium'             => ['nullable', 'string', 'max:255'],
            'utm_campaign'           => ['nullable', 'string', 'max:255'],
            'utm_content'            => ['nullable', 'string', 'max:255'],
            'utm_term'               => ['nullable', 'string', 'max:255'],
            'page_url'               => ['nullable', 'string', 'max:2048'],
            'referrer'               => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_lastname'        => 'Фамилия законного представителя',
            'parent_firstname'       => 'Имя законного представителя',
            'parent_middlename'      => 'Отчество законного представителя',
            'parent_phone'           => 'Телефон законного представителя',
            'parent_email'           => 'Email законного представителя',
            'child_lastname'         => 'Фамилия ребёнка',
            'child_firstname'        => 'Имя ребёнка',
            'child_middlename'       => 'Отчество ребёнка',
            'child_birthday'         => 'Дата рождения ребёнка',
            'is_individual_traits'   => 'Индивидуальные особенности воспитанника',
            'is_on_medical_register' => 'Учёт у медицинских специалистов',
            'is_with_disability'     => 'Наличие инвалидности',
            'location_id'            => 'Район',
            'sport_type_id'          => 'Вид спорта',
            'team_id'                => 'Услуга',
            'needs_contact_help'     => 'Связаться для выбора секции',
            'comment'                => 'Комментарий',
            'consent_accepted'       => 'Согласие на обработку персональных данных',
            'recaptcha_token'        => 'Проверка от спама',
        ];
    }

    public function messages(): array
    {
        return [
            'parent_lastname.required'   => 'Укажите фамилию законного представителя.',
            'parent_firstname.required'  => 'Укажите имя законного представителя.',
            'parent_middlename.required' => 'Укажите отчество законного представителя.',
            'parent_phone.required'      => 'Укажите телефон законного представителя.',
            'parent_phone.regex'         => 'Телефон может содержать только цифры, +, -, пробелы и скобки.',
            'parent_email.required'      => 'Укажите email законного представителя.',
            'parent_email.email'         => 'Укажите корректный email законного представителя.',
            'child_lastname.required'    => 'Укажите фамилию ребёнка.',
            'child_firstname.required'   => 'Укажите имя ребёнка.',
            'child_middlename.required'  => 'Укажите отчество ребёнка.',
            'child_birthday.required'    => 'Укажите дату рождения ребёнка.',
            'child_birthday.date'        => 'Некорректная дата рождения ребёнка.',
            'child_birthday.before'      => 'Дата рождения ребёнка должна быть в прошлом.',
            'location_id.required'       => 'Выберите район.',
            'location_id.exists'         => 'Выбранный район недоступен.',
            'sport_type_id.exists'       => 'Выбранный вид спорта недоступен.',
            'team_id.exists'             => 'Выбранная услуга недоступна.',
            'consent_accepted.required'  => 'Необходимо согласие на обработку персональных данных.',
            'consent_accepted.accepted'  => 'Необходимо согласие на обработку персональных данных.',
            'recaptcha_token.required'   => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            if ($this->filled('parent_phone')) {
                $digits = preg_replace('/\D+/', '', (string) $this->input('parent_phone'));
                if (strlen($digits) < 6) {
                    $v->errors()->add('parent_phone', 'Укажите корректный телефон (минимум 6 цифр).');
                }
            }

            $partnerId = $this->partnerId();
            if (!$partnerId || !$this->filled('team_id') || !$this->filled('location_id')) {
                return;
            }

            $team = Team::query()
                ->where('partner_id', $partnerId)
                ->whereKey((int) $this->input('team_id'))
                ->first();

            if (!$team) {
                return;
            }

            $message = app(TeamLocationAvailabilityService::class)
                ->assertTeamAllowedAtLocation($team, (int) $this->input('location_id'));

            if ($message !== null) {
                $v->errors()->add('team_id', $message);
            }

            if (!$this->filled('team_id') || !$this->filled('sport_type_id')) {
                return;
            }

            $teamForSportType = Team::query()
                ->where('partner_id', $partnerId)
                ->whereKey((int) $this->input('team_id'))
                ->first();

            if (!$teamForSportType || $teamForSportType->sport_type_id === null) {
                return;
            }

            if ((int) $teamForSportType->sport_type_id !== (int) $this->input('sport_type_id')) {
                $v->errors()->add('team_id', 'Выбранная услуга не относится к указанному виду спорта.');
            }
        });
    }

    public function partnerId(): ?int
    {
        return $this->resolvedWidget()?->partner_id;
    }

    public function resolvedWidget(): ?PartnerWidget
    {
        if ($this->resolvedWidget !== null) {
            return $this->resolvedWidget;
        }

        $landingKey = (string) $this->route('landingKey');

        $this->resolvedWidget = PartnerWidget::query()
            ->where('landing_key', $landingKey)
            ->where('is_landing_active', true)
            ->first();

        return $this->resolvedWidget;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
