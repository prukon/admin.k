<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Документация карточки ученика на вкладке «По месяцам».
 */
final class SettingPricesMonthlyUserCardDocumentationContractTest extends TestCase
{
    public function test_monthly_users_doc_describes_user_card_modal(): void
    {
        $html = $this->docFile('setting-prices-monthly-users.html');

        $this->assertStringContainsString('id="monthly-user-card"', $html);
        $this->assertStringContainsString('setting-prices.users.card', $html);
        $this->assertStringContainsString('/admin/setting-prices/user-cards/{user}', $html);
        $this->assertStringContainsString('paymentUserCardModal', $html);
        $this->assertStringContainsString('KidsCrmUserCard.renderName', $html);
        $this->assertStringContainsString('errors.user', $html);
        $this->assertStringContainsString('Нет доступа к карточке этого пользователя.', $html);
        $this->assertStringContainsString('Пользователь не найден.', $html);
        $this->assertStringContainsString('SettingPricesMonthlyUserCardFeatureTest', $html);
        $this->assertStringContainsString('SettingPricesMonthlyUserCardDocumentationContractTest', $html);
    }

    public function test_doc_index_announces_monthly_user_card(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="setting-prices-monthly-user-card-index"', $html);
        $this->assertStringContainsString('/admin/setting-prices/user-cards/{user}', $html);
        $this->assertStringContainsString('setting-prices-monthly-users#monthly-user-card', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
