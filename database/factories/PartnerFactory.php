<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Partner>
 */
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

        return [
            'order_by'   => 0,
            'is_enabled' => 1,

            'business_type' => $this->faker->randomElement($businessTypes),

            'title'            => $this->faker->company(),           // NOT NULL
            'organization_name'=> $this->faker->optional()->company(),

            // UNIQUE, пусть всегда будут заполнены
            'tax_id'             => $this->faker->unique()->numerify('##############'),
            'kpp'                => $this->faker->numerify('#########'),
            'registration_number'=> $this->faker->unique()->numerify('##############'),

            'address'        => $this->faker->optional()->address(),
            'phone'          => $this->faker->optional()->phoneNumber(),
            'email'          => $this->faker->unique()->safeEmail(), // NOT NULL + UNIQUE

            'city'           => $this->faker->optional()->city(),
            'zip'            => $this->faker->optional()->postcode(),

            'ceo'            => $this->faker->optional()->name(),

            'wallet_balance' => 0.00, // NOT NULL, default 0.00

            'website'        => $this->faker->optional()->url(),
            'sms_name'       => $this->faker->optional()->lexify('????????'),

            'bank_name'      => $this->faker->optional()->company(),
            'bank_bik'       => $this->faker->optional()->numerify('#########'),
            'bank_account'   => $this->faker->optional()->numerify('####################'), // до 20 символов

            'activity_start_date' => $this->faker->optional()->date(),

            'tinkoff_partner_id'           => null, // UNIQUE, но пусть будет пусто в демо
            'sm_register_status'           => null,
            'bank_details_version'         => null,
            'bank_details_last_updated_at' => null,
            'sm_details_template'          => null,
        ];
    }
}
