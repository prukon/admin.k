<?php

namespace Database\Factories;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerLegalEntity>
 */
class PartnerLegalEntityFactory extends Factory
{
    protected $model = PartnerLegalEntity::class;

    public function configure(): static
    {
        return $this->afterMaking(function (PartnerLegalEntity $entity): void {
            $title = trim((string) ($entity->title ?? ''));
            $organizationName = trim((string) ($entity->organization_name ?? ''));

            if ($title !== '' && $organizationName === '') {
                $entity->organization_name = $title;
            }
        });
    }

    public function definition(): array
    {
        $title = 'ООО «Тест ' . $this->faker->numberBetween(100000, 999999999) . '»';

        return [
            'partner_id' => Partner::factory(),
            'business_type' => PartnerLegalEntityBusinessType::OOO,
            'title' => $title,
            'organization_name' => $title,
            'tax_id' => (string) $this->faker->numerify('##########'),
            'kpp' => (string) $this->faker->numerify('#########'),
            'registration_number' => (string) $this->faker->numerify('#############'),
            'city' => $this->faker->city(),
            'zip' => (string) $this->faker->numerify('######'),
            'address' => $this->faker->streetAddress(),
            'ceo' => null,
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
            'sm_details_template' => null,
            'tinkoff_shop_code' => null,
            'sm_register_status' => null,
            'registered_at' => null,
            'bank_details_version' => null,
            'bank_details_last_updated_at' => null,
            'registration_verified_at' => null,
            'vat' => null,
            'sms_name' => null,
            'is_default' => true,
            'is_enabled' => true,
        ];
    }

    public function individualEntrepreneur(): static
    {
        return $this->state(function () {
            $title = 'ИП Тест ' . $this->faker->numberBetween(100000, 999999999);

            return [
                'business_type' => PartnerLegalEntityBusinessType::IP,
                'kpp' => null,
                'title' => $title,
                'organization_name' => $title,
            ];
        });
    }

    public function registered(string $shopCode = 'SHOP-TEST-001'): static
    {
        return $this->state(fn () => [
            'tinkoff_shop_code' => $shopCode,
            'sm_register_status' => 'REGISTERED',
            'registered_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
