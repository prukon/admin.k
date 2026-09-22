<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\Location;
use App\Models\PartnerSocialLink;
use App\Models\SocialNetwork;
use App\Models\SportType;
use App\Models\Team;
use App\Services\Contracts\ContractContextPlaceholderService;
use Tests\Feature\Crm\CrmTestCase;

class ContractContextPlaceholderFeatureTest extends CrmTestCase
{
    /** @test */
    public function values_come_from_group_location_partner_and_enabled_social_links(): void
    {
        $this->partner->update([
            'email' => 'school-context@example.com',
            'phone' => '9060001122',
        ]);

        $sport = SportType::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Плавание',
        ]);
        $location = Location::factory()->forPartner((int) $this->partner->id)->create([
            'address' => 'ул. Лесная, 1',
        ]);
        $team = Team::factory()->for($this->partner)->create([
            'sport_type_id' => $sport->id,
            'location_id'   => $location->id,
        ]);

        $later = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Бета',
            'name'     => 'Борис',
            'email'    => 'beta-admin@example.com',
            'phone'    => '9064445566',
        ]);
        $earlier = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Альфа',
            'name'     => 'Анна',
            'email'    => 'alpha-admin@example.com',
            'phone'    => '9061112233',
        ]);
        $duplicatePhone = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Гамма',
            'name'     => 'Глеб',
            'email'    => 'gamma-admin@example.com',
            'phone'    => '79061112233',
        ]);

        $location->adminUsers()->attach($later->id, ['partner_id' => $this->partner->id]);
        $location->adminUsers()->attach($earlier->id, ['partner_id' => $this->partner->id]);
        $location->adminUsers()->attach($duplicatePhone->id, ['partner_id' => $this->partner->id]);

        $vk = SocialNetwork::query()->where('code', 'vk')->firstOrFail();
        $telegram = SocialNetwork::query()->where('code', 'telegram')->firstOrFail();
        PartnerSocialLink::query()->create([
            'partner_id'        => $this->partner->id,
            'social_network_id' => $vk->id,
            'url'               => 'https://vk.com/school',
            'is_enabled'        => true,
            'sort'              => 10,
        ]);
        PartnerSocialLink::query()->create([
            'partner_id'        => $this->partner->id,
            'social_network_id' => $telegram->id,
            'url'               => 'https://t.me/school',
            'is_enabled'        => false,
            'sort'              => 20,
        ]);

        $contract = new Contract([
            'school_id' => $this->partner->id,
            'group_id'  => $team->id,
        ]);

        $values = app(ContractContextPlaceholderService::class)->valuesForContract($contract);

        $this->assertSame('Плавание', $values[ContractContextPlaceholderService::SPORT_TYPE_NAME]);
        $this->assertSame('ул. Лесная, 1', $values[ContractContextPlaceholderService::LOCATION_ADDRESS]);
        $this->assertSame(
            'alpha-admin@example.com, beta-admin@example.com, gamma-admin@example.com',
            $values[ContractContextPlaceholderService::LOCATION_ADMIN_EMAILS],
        );
        $this->assertSame(
            '+7 (906) 111-22-33, +7 (906) 444-55-66',
            $values[ContractContextPlaceholderService::LOCATION_ADMIN_PHONES],
        );
        $this->assertSame('school-context@example.com', $values[ContractContextPlaceholderService::PARTNER_EMAIL]);
        $this->assertSame('+7 (906) 000-11-22', $values[ContractContextPlaceholderService::PARTNER_PHONE]);
        $this->assertSame('https://vk.com/school', $values['partner_social_vk']);
        $this->assertSame('', $values['partner_social_telegram']);
        $this->assertArrayHasKey('partner_social_facebook', $values);
        $this->assertSame('', $values['partner_social_facebook']);
    }

    /** @test */
    public function missing_group_sport_and_location_stay_empty(): void
    {
        $contract = new Contract([
            'school_id' => $this->partner->id,
            'group_id'  => null,
        ]);

        $values = app(ContractContextPlaceholderService::class)->valuesForContract($contract);

        $this->assertSame('', $values[ContractContextPlaceholderService::SPORT_TYPE_NAME]);
        $this->assertSame('', $values[ContractContextPlaceholderService::LOCATION_ADDRESS]);
        $this->assertSame('', $values[ContractContextPlaceholderService::LOCATION_ADMIN_EMAILS]);
        $this->assertSame('', $values[ContractContextPlaceholderService::LOCATION_ADMIN_PHONES]);
    }
}
