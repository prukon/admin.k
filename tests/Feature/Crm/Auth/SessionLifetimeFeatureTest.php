<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Tests\Feature\Crm\CrmTestCase;

/**
 * SESSION_LIFETIME=43200 (30 суток idle), дефолт config/session.php тот же.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SessionLifetimeFeatureTest extends CrmTestCase
{
    public function test_expire_on_close_is_off(): void
    {
        $this->assertFalse((bool) config('session.expire_on_close'));
        $this->assertGreaterThan(0, (int) config('session.lifetime'));
    }

    public function test_session_config_file_defaults_to_thirty_days(): void
    {
        $source = (string) file_get_contents(base_path('config/session.php'));
        $this->assertStringContainsString("env('SESSION_LIFETIME', 43200)", $source);
        $this->assertStringNotContainsString("env('SESSION_LIFETIME', 120)", $source);
        $phpunit = (string) file_get_contents(base_path('phpunit.xml'));
        $this->assertStringContainsString('SESSION_LIFETIME" value="43200"', $phpunit);
    }
}
