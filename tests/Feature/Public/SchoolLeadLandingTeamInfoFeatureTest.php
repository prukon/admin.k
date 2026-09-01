<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\SportType;
use App\Models\User;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * Блок «Об услуге» (GET /lead/{slug}/team-info): только адрес / вид спорта / стоимость / период;
 * пустые поля не отдаются, AJAX-контракт, non-AJAX JSON, публичный доступ без CRM-прав.
 *
 * @see \Tests\Feature\Crm\Ui\BladeInlineJsSyntaxTest (landing/partner-lead.blade.php)
 */
final class SchoolLeadLandingTeamInfoFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_guest_team_info_returns_200_not_redirect_or_401(): void
    {
        Auth::logout();

        $this->getJson($this->teamInfoUrl())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'rows',
                ],
            ]);
    }

    public function test_authenticated_user_without_crm_permissions_can_load_team_info(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->landingPartner->id,
        ]);
        $this->actingAs($user);

        $this->getJson($this->teamInfoUrl(), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Плавание')
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'rows' => [
                        '*' => ['label', 'value'],
                    ],
                ],
            ]);
    }

    public function test_team_info_ajax_contract_returns_title_and_typed_rows(): void
    {
        $this->seed(WeekdaysSeeder::class);

        $sportType = SportType::query()->create([
            'partner_id' => $this->landingPartner->id,
            'name'       => 'Плавание вид',
            'sort'       => 1,
            'is_enabled' => true,
        ]);

        $this->landingLocation->update(['address' => 'ул. Тестовая, 1']);
        $this->landingTeam->update([
            'sport_type_id'            => $sportType->id,
            'month_price_cents'        => 300000,
            'default_duration_minutes' => 60,
        ]);
        $this->landingTeam->weekdays()->sync([1, 3]);

        Carbon::setTestNow(Carbon::create(2026, 3, 15));

        $response = $this->getJson($this->teamInfoUrl(), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Плавание')
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'rows' => [
                        '*' => ['label', 'value'],
                    ],
                ],
            ]);

        $rowsByLabel = collect($response->json('data.rows'))->keyBy('label');

        $this->assertSame('ул. Тестовая, 1', $rowsByLabel->get('Адрес')['value'] ?? null);
        $this->assertSame('Плавание вид', $rowsByLabel->get('Вид спорта')['value'] ?? null);
        $this->assertSame('3 000 ₽', $rowsByLabel->get('Стоимость в месяц')['value'] ?? null);
        $this->assertSame('12.01.2026 — 30.06.2026', $rowsByLabel->get('Период занятий')['value'] ?? null);
        $this->assertSame(
            ['Адрес', 'Вид спорта', 'Стоимость в месяц', 'Период занятий'],
            $rowsByLabel->keys()->all()
        );

        Carbon::setTestNow();
    }

    public function test_team_info_non_ajax_get_returns_json_body_not_empty_200(): void
    {
        $response = $this->get($this->teamInfoUrl(), [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'rows',
                ],
            ]);

        $json = $response->json();
        $this->assertNotEmpty($json['data']['title'] ?? null);
        $this->assertIsArray($json['data']['rows'] ?? null);
        $this->assertNotEmpty($json['data']['rows']);
    }

    public function test_team_info_omits_empty_optional_rows_and_keeps_training_period(): void
    {
        $this->landingLocation->update(['address' => null]);
        $this->landingTeam->update([
            'sport_type_id'            => null,
            'month_price_cents'        => null,
            'default_duration_minutes' => 0,
        ]);
        $this->landingTeam->weekdays()->sync([]);

        Carbon::setTestNow(Carbon::create(2026, 3, 15));

        $rows = $this->getJson($this->teamInfoUrl())
            ->assertOk()
            ->json('data.rows');

        $this->assertSame(
            [
                [
                    'label' => 'Период занятий',
                    'value' => '12.01.2026 — 30.06.2026',
                ],
            ],
            $rows
        );

        Carbon::setTestNow();
    }

    public function test_team_info_never_returns_em_dash_placeholder_values(): void
    {
        $this->landingLocation->update(['address' => '   ']);
        $this->landingTeam->update([
            'sport_type_id'            => null,
            'month_price_cents'        => null,
            'default_duration_minutes' => 0,
        ]);
        $this->landingTeam->weekdays()->sync([]);

        $rows = $this->getJson($this->teamInfoUrl())
            ->assertOk()
            ->json('data.rows');

        foreach ($rows as $row) {
            $this->assertNotSame('—', $row['value'] ?? null, $row['label'] ?? 'row');
            $this->assertNotSame('', trim((string) ($row['value'] ?? '')));
        }

        $labels = collect($rows)->pluck('label')->all();
        $this->assertNotContains('Адрес', $labels);
        $this->assertNotContains('Вид спорта', $labels);
        $this->assertNotContains('Стоимость в месяц', $labels);
        $this->assertContains('Период занятий', $labels);
    }

    public function test_team_info_includes_only_filled_optional_fields(): void
    {
        $this->landingLocation->update(['address' => 'Адрес объекта']);
        $this->landingTeam->update([
            'sport_type_id'            => null,
            'month_price_cents'        => 150000,
            'default_duration_minutes' => 0,
        ]);
        $this->landingTeam->weekdays()->sync([]);

        $labels = collect(
            $this->getJson($this->teamInfoUrl())->assertOk()->json('data.rows')
        )->pluck('label')->all();

        $this->assertSame(
            ['Адрес', 'Стоимость в месяц', 'Период занятий'],
            $labels
        );
    }

    public function test_team_info_omits_address_when_blank(): void
    {
        $this->landingLocation->update(['address' => '']);

        $labels = collect(
            $this->getJson($this->teamInfoUrl())->assertOk()->json('data.rows')
        )->pluck('label')->all();

        $this->assertNotContains('Адрес', $labels);
    }

    public function test_team_info_omits_sport_type_when_missing(): void
    {
        $this->landingTeam->update(['sport_type_id' => null]);

        $labels = collect(
            $this->getJson($this->teamInfoUrl())->assertOk()->json('data.rows')
        )->pluck('label')->all();

        $this->assertNotContains('Вид спорта', $labels);
    }

    public function test_team_info_omits_price_when_null(): void
    {
        $this->landingTeam->update(['month_price_cents' => null]);

        $labels = collect(
            $this->getJson($this->teamInfoUrl())->assertOk()->json('data.rows')
        )->pluck('label')->all();

        $this->assertNotContains('Стоимость в месяц', $labels);
    }

    public function test_team_info_never_returns_schedule_duration_or_lesson_counts(): void
    {
        $this->seed(WeekdaysSeeder::class);

        $this->landingTeam->update(['default_duration_minutes' => 60]);
        $this->landingTeam->weekdays()->sync([1, 3]);

        $labels = collect(
            $this->getJson($this->teamInfoUrl())->assertOk()->json('data.rows')
        )->pluck('label')->all();

        $this->assertNotContains('Занятий в неделю', $labels);
        $this->assertNotContains('Занятий в месяц', $labels);
        $this->assertNotContains('Продолжительность занятия', $labels);
        $this->assertNotContains('Расписание занятий', $labels);
        $this->assertContains('Период занятий', $labels);
    }

    public function test_team_info_validation_returns_422_json_not_empty_200(): void
    {
        $slug = (string) $this->landingWidget->landing_slug;

        $this->getJson(route('lead.team-info', [
            'landingSlug' => $slug,
            'team_id'     => $this->landingTeam->id,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['location_id']);

        $this->getJson(route('lead.team-info', [
            'landingSlug'  => $slug,
            'location_id' => $this->landingLocation->id,
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_landing_blade_includes_empty_rows_hide_team_info_js_guard(): void
    {
        $path = resource_path('views/landing/partner-lead.blade.php');
        $this->assertFileExists($path);

        $html = (string) file_get_contents($path);

        $this->assertStringContainsString('function loadTeamInfo()', $html);
        $this->assertStringContainsString('function hideTeamInfo()', $html);
        $this->assertStringContainsString('var rows = result.json.data.rows || [];', $html);
        $this->assertStringContainsString('if (!rows.length) {', $html);
        $this->assertStringContainsString('hideTeamInfo();', $html);
        $this->assertStringContainsString('id="teamInfoBlock"', $html);
        $this->assertStringNotContainsString('Занятий в неделю', $html);
        $this->assertStringNotContainsString('Занятий в месяц', $html);
        $this->assertStringNotContainsString('Продолжительность занятия', $html);
        $this->assertStringNotContainsString('Расписание занятий', $html);
    }

    /**
     * @return string
     */
    private function teamInfoUrl(): string
    {
        return route('lead.team-info', [
            'landingSlug'  => $this->landingWidget->landing_slug,
            'location_id' => $this->landingLocation->id,
            'team_id'     => $this->landingTeam->id,
        ]);
    }
}
