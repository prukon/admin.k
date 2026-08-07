<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Services\SchoolLeads\SchoolLeadParentMatcher;
use Tests\Feature\Crm\CrmTestCase;

final class SchoolLeadParentMatcherFeatureTest extends CrmTestCase
{
    private SchoolLeadParentMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(SchoolLeadParentMatcher::class);
    }

    public function test_matches_by_email_case_insensitive(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'Parent@Example.com',
            'lastname' => 'Сидоров',
            'firstname' => 'Пётр',
            'phone' => '79990001122',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            'parent@example.com',
            '79991112233',
            'Другой',
            'Имя',
        );

        $this->assertNotNull($result);
        $this->assertSame($parent->id, $result->parent->id);
        $this->assertSame(SchoolLeadParentMatchReason::Email, $result->reason);
        $this->assertSame(1, $result->count);
    }

    public function test_email_has_priority_over_phone_and_name(): void
    {
        $byEmail = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'match@example.com',
            'lastname' => 'А',
            'firstname' => 'А',
            'phone' => '70000000001',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'other@example.com',
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
            'phone' => '79991112233',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            'match@example.com',
            '79991112233',
            'Иванов',
            'Иван',
        );

        $this->assertNotNull($result);
        $this->assertSame($byEmail->id, $result->parent->id);
        $this->assertSame(SchoolLeadParentMatchReason::Email, $result->reason);
    }

    public function test_matches_phone_by_national_10_digits_without_country_code(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => null,
            'phone' => '79110263811',
            'lastname' => 'Тестов',
            'firstname' => 'Тест',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            null,
            '+7 (911) 026-38-11',
            'Другой',
            'Человек',
        );

        $this->assertNotNull($result);
        $this->assertSame($parent->id, $result->parent->id);
        $this->assertSame(SchoolLeadParentMatchReason::Phone, $result->reason);
        $this->assertSame(
            '9110263811',
            \App\Support\RuPhone::nationalDigits10('+7 (911) 026-38-11')
        );
    }

    public function test_matches_phone_when_stored_with_leading_8(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => null,
            'phone' => '89110263811',
            'lastname' => 'Тестов',
            'firstname' => 'Тест',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            null,
            '79110263811',
            null,
            null,
        );

        $this->assertNotNull($result);
        $this->assertSame($parent->id, $result->parent->id);
        $this->assertSame(SchoolLeadParentMatchReason::Phone, $result->reason);
    }

    public function test_name_match_takes_oldest_when_multiple(): void
    {
        $older = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'Петров',
            'firstname' => 'Иван',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'Петров',
            'firstname' => 'Иван',
        ]);
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => null,
            'phone' => null,
            'lastname' => 'петров',
            'firstname' => 'иван',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            null,
            null,
            'Петров',
            'Иван',
        );

        $this->assertNotNull($result);
        $this->assertSame($older->id, $result->parent->id);
        $this->assertSame(SchoolLeadParentMatchReason::Name, $result->reason);
        $this->assertSame(3, $result->count);
    }

    public function test_does_not_match_other_partner_parents(): void
    {
        $otherPartner = \App\Models\Partner::factory()->create();
        ParentProfile::factory()->create([
            'partner_id' => $otherPartner->id,
            'email' => 'shared@example.com',
            'phone' => '79991112233',
            'lastname' => 'Иванов',
            'firstname' => 'Иван',
        ]);

        $result = $this->matcher->match(
            (int) $this->partner->id,
            'shared@example.com',
            '79991112233',
            'Иванов',
            'Иван',
        );

        $this->assertNull($result);
    }

    public function test_ignores_soft_deleted_parents(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email' => 'deleted@example.com',
        ]);
        $parent->delete();

        $result = $this->matcher->match(
            (int) $this->partner->id,
            'deleted@example.com',
            null,
            null,
            null,
        );

        $this->assertNull($result);
    }
}
