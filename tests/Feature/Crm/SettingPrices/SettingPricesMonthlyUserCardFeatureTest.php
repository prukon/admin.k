<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Карточка ученика по клику на имя на вкладке «По месяцам».
 *
 * @see /docs/documentation/setting-prices-monthly-users.html#monthly-user-card
 */
final class SettingPricesMonthlyUserCardFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_monthly_page_includes_the_schedule_user_card_modal(): void
    {
        $this->asAdmin();

        $html = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="paymentUserCardModal"', $html);
        $this->assertSame(1, substr_count($html, 'id="paymentUserCardModal"'));
        $this->assertStringContainsString('data-user-card-url="'.url('/admin/setting-prices/user-cards').'"', $html);
        $this->assertStringContainsString('#right_bar .user-name', $html);
        $this->assertStringContainsString("e.target.closest('.js-user-card, .js-payment-user-card')", $html);
        $modalPos = strpos($html, 'id="paymentUserCardModal"');
        $this->assertNotFalse($modalPos);
        $modalChunk = substr($html, (int) $modalPos, 500);
        $this->assertStringContainsString('modal-dialog', $modalChunk);
        $this->assertStringNotContainsString('modal-lg', $modalChunk);
        $this->assertStringNotContainsString('modal-fullscreen', $modalChunk);

        $usersHtml = $this->get(route('admin.settingPrices.users'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="paymentUserCardModal"', $usersHtml);

        $js = (string) file_get_contents(resource_path('js/settings-prices.js'));
        $this->assertStringContainsString('KidsCrmUserCard.renderName(userNameFormatted, uid)', $js);
    }

    public function test_user_card_returns_student_for_set_prices_view(): void
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Ценов',
            'name' => 'Илья',
            'is_enabled' => true,
            'comment' => 'Скрытый комментарий',
        ]);
        $actor = $this->createUserWithOnlyPermissions(['setPrices.view']);
        $this->actingAs($actor);

        $this->getJson(route('setting-prices.users.card', $student))
            ->assertOk()
            ->assertJsonPath('id', (int) $student->id)
            ->assertJsonPath('full_name', 'Ценов Илья')
            ->assertJsonPath('can_edit_comment', false)
            ->assertJsonPath('comment', '');

        $this->getJson(route('reports.payments.users.show', $student))->assertForbidden();
        $this->getJson(route('schedule.users.show', $student))->assertForbidden();
    }

    public function test_soft_deleted_student_card_is_still_available(): void
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Архив',
            'name' => 'Пётр',
            'is_enabled' => true,
        ]);
        $student->delete();
        $this->asAdmin();

        $this->getJson(route('setting-prices.users.card', $student->id))
            ->assertOk()
            ->assertJsonPath('id', (int) $student->id)
            ->assertJsonPath('full_name', 'Архив Пётр');
    }

    public function test_another_partner_returns_russian_error(): void
    {
        $this->asAdmin();

        $response = $this->getJson(route('setting-prices.users.card', $this->foreignUser));

        $response->assertForbidden();
        $response->assertJsonPath('errors.user.0', 'Нет доступа к карточке этого пользователя.');
        $response->assertJsonPath('message', 'Нет доступа к карточке этого пользователя.');
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $response->getContent());
    }

    public function test_missing_user_returns_not_found_error(): void
    {
        $this->asAdmin();

        $this->getJson(route('setting-prices.users.card', 999999))
            ->assertNotFound()
            ->assertJsonPath('errors.user.0', 'Пользователь не найден.');
    }

    public function test_guest_cannot_open_card_or_monthly_page(): void
    {
        Auth::logout();

        $card = $this->getJson(route('setting-prices.users.card', 1));
        $this->assertContains($card->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $card->getStatusCode());

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $this->assertContains($page->getStatusCode(), [302, 401, 403]);
        $this->assertStringNotContainsString('id="paymentUserCardModal"', $page->getContent());
    }

    public function test_without_set_prices_view_returns_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('admin.settingPrices.indexMenu'))->assertForbidden();
        $this->getJson(route('setting-prices.users.card', $this->user))->assertForbidden();
    }

    public function test_post_patch_delete_on_user_card_are_method_not_allowed(): void
    {
        $this->asAdmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'is_enabled' => true,
        ]);
        $url = route('setting-prices.users.card', $student);

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->json($method, $url);
            $this->assertContains($response->getStatusCode(), [404, 405], $method);
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function createUserWithOnlyPermissions(array $permissionNames): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'test_prices_card_'.Str::lower(Str::random(8)),
            'label' => 'Test Setting Prices User Card',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissionNames as $permissionName) {
            DB::table('permission_role')->insert([
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($permissionName),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
        ]);
    }
}
