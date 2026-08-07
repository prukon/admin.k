<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\SchoolLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

final class SchoolLeadLandingParentMatchFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
        Notification::fake();
        $this->fakeRecaptchaSuccess();
    }

    public function test_landing_submit_auto_matches_parent_by_email(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => 'parent@example.com',
            'lastname' => 'Справочников',
            'firstname' => 'Родитель',
            'phone' => '79990000000',
        ]);

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'Parent@Example.com',
                'parent_lastname' => 'Иванов',
                'parent_firstname' => 'Иван',
                'parent_phone' => '+7 999 111-22-33',
            ])
        );

        $response->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($parent->id, (int) $lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchReason::Email, $lead->parent_match_reason);
        $this->assertSame(1, (int) $lead->parent_match_count);
        $this->assertNull($lead->parent_match_confirmed);
        $this->assertSame('Иванов', $lead->parent_lastname);
        $this->assertSame('Иван', $lead->parent_firstname);
        $this->assertStringContainsString('почте', (string) $lead->parentMatchBannerText());
        $this->assertTrue($lead->needsParentMatchDecision());
    }

    public function test_landing_submit_auto_matches_parent_by_name_with_count(): void
    {
        $older = ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
        ]);

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'unique-new@example.com',
                'parent_phone' => '+7 900 000-00-01',
                'parent_lastname' => 'Иванов',
                'parent_firstname' => 'Иван',
            ])
        );

        $response->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($older->id, (int) $lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchReason::Name, $lead->parent_match_reason);
        $this->assertSame(3, (int) $lead->parent_match_count);
        $this->assertStringContainsString('фамилии и имени', (string) $lead->parentMatchBannerText());
        $this->assertStringContainsString('Найдено 3 совпадения', (string) $lead->parentMatchBannerText());
    }

    public function test_landing_submit_auto_matches_parent_by_phone_national_10_digits(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => null,
            'phone' => '79110263811',
            'lastname' => 'Телефонов',
            'firstname' => 'Родитель',
        ]);

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'unique-phone-match@example.com',
                'parent_phone' => '+7 (911) 026-38-11',
                'parent_lastname' => 'Другой',
                'parent_firstname' => 'Человек',
            ])
        );

        $response->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($parent->id, (int) $lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchReason::Phone, $lead->parent_match_reason);
        $this->assertSame(1, (int) $lead->parent_match_count);
        $this->assertNull($lead->parent_match_confirmed);
        $this->assertStringContainsString('номеру телефона', (string) $lead->parentMatchBannerText());
        $this->assertTrue($lead->needsParentMatchDecision());
    }

    public function test_landing_submit_email_beats_phone_when_both_match_different_parents(): void
    {
        $byEmail = ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => 'priority@example.com',
            'phone' => '70000000001',
            'lastname' => 'А',
            'firstname' => 'А',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->landingPartner->id,
            'email' => 'other@example.com',
            'phone' => '79991112233',
            'lastname' => 'Б',
            'firstname' => 'Б',
        ]);

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'priority@example.com',
                'parent_phone' => '+7 999 111-22-33',
                'parent_lastname' => 'Б',
                'parent_firstname' => 'Б',
            ])
        );

        $response->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame($byEmail->id, (int) $lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchReason::Email, $lead->parent_match_reason);
    }

    public function test_landing_submit_without_directory_match_leaves_parent_null(): void
    {
        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $this->landingWidget->landing_slug]),
            $this->validLandingPayload([
                'parent_email' => 'brand-new@example.com',
                'parent_phone' => '+7 900 111-22-33',
                'parent_lastname' => 'Новиков',
                'parent_firstname' => 'Новый',
            ])
        );

        $response->assertOk();

        $lead = SchoolLead::query()->latest('id')->first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->parent_id);
        $this->assertNull($lead->parent_match_reason);
        $this->assertNull($lead->parent_match_count);
        $this->assertNull($lead->parent_match_confirmed);
        $this->assertNull($lead->parentMatchBannerText());
        $this->assertFalse($lead->needsParentMatchDecision());
    }
}
