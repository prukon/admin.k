<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        $businessTypes = [
            'company',
            'individual_entrepreneur',
            'physical_person',
            'non_commercial_organization',
        ];

        $title = $this->faker->company();

        return [
            'order_by' => 0,
            'is_enabled' => true,

            'business_type' => $this->faker->randomElement($businessTypes),

            'title' => $title,
            'organization_name' => $title,

            'tax_id' => $this->faker->boolean(60)
                ? $this->faker->unique()->numerify('##########')
                : null,

            'kpp' => $this->faker->boolean(40)
                ? $this->faker->numerify('#########')
                : null,

            'registration_number' => $this->faker->boolean(40)
                ? $this->faker->unique()->numerify('#############')
                : null,

            'address' => $this->faker->boolean(70)
                ? $this->faker->address()
                : null,

            // phone должен проходить валидацию: /^[0-9\(\)\-\+\s]+$/
            'phone' => $this->faker->boolean(70)
                ? ('+7 (' . $this->faker->numerify('###') . ') ' . $this->faker->numerify('###-##-##'))
                : null,

            'email' => $this->faker->unique()->safeEmail(),

            'city' => $this->faker->boolean(60)
                ? $this->faker->city()
                : null,

            'zip' => $this->faker->boolean(60)
                ? $this->faker->postcode()
                : null,

            // В модели ceo кастится как array
            'ceo' => $this->faker->boolean(50)
                ? [
                    'name' => $this->faker->name(),
                    'position' => 'CEO',
                ]
                : null,

            'wallet_balance' => 0.00,

            'website' => $this->faker->boolean(40)
                ? $this->faker->url()
                : null,

            'sms_name' => $this->faker->boolean(30)
                ? Str::upper($this->faker->lexify('??????????????'))
                : null,

            'bank_name' => $this->faker->boolean(30)
                ? $this->faker->company()
                : null,

            'bank_bik' => $this->faker->boolean(30)
                ? $this->faker->numerify('#########')
                : null,

            'bank_account' => $this->faker->boolean(30)
                ? $this->faker->numerify('####################')
                : null,

            'activity_start_date' => $this->faker->boolean(50)
                ? $this->faker->date()
                : null,

            'tinkoff_partner_id' => $this->faker->boolean(20)
                ? $this->faker->unique()->uuid()
                : null,

            'sm_register_status' => null,
            'registered_at' => null,

            'bank_details_version' => 1,
            'bank_details_last_updated_at' => null,

            'sm_details_template' => null,
        ];
    }
}