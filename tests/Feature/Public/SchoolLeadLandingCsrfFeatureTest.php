<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Public\Concerns\ProvidesSchoolLeadLandingFixtures;
use Tests\TestCase;

/**
 * CSRF 419 на публичном submit лендинга: UX-сообщение + запись в laravel.log.
 */
final class SchoolLeadLandingCsrfFeatureTest extends TestCase
{
    use ProvidesSchoolLeadLandingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolLeadLandingFixtures();
    }

    public function test_show_page_contains_friendly_419_message_in_js(): void
    {
        $content = (string) file_get_contents(resource_path('views/landing/partner-lead.blade.php'));

        $this->assertStringContainsString('status === 419', $content);
        $this->assertStringContainsString(
            'Сессия устарела. Обновите страницу и отправьте заявку ещё раз.',
            $content
        );
    }

    public function test_submit_csrf_mismatch_returns_419_russian_message_and_is_logged(): void
    {
        $this->fakeRecaptchaSuccess();

        // В environment=testing VerifyCsrfToken пропускает проверку — временно включаем.
        $this->app->instance('env', 'local');

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $slug = (string) $this->landingWidget->landing_slug;

        $response = $this->postJson(
            route('lead.submit', ['landingSlug' => $slug]),
            array_merge($this->validLandingPayload(), [
                '_token' => 'definitely-invalid-csrf-token',
            ])
        );

        $response->assertStatus(419)
            ->assertJson([
                'message' => 'Сессия устарела. Обновите страницу и отправьте заявку ещё раз.',
            ]);

        $csrfLogs = array_values(array_filter(
            $logged,
            static fn (MessageLogged $event): bool => $event->level === 'warning'
                && $event->message === 'CSRF token mismatch on school lead submit'
        ));

        $this->assertCount(1, $csrfLogs);
        $this->assertSame('lead.submit', $csrfLogs[0]->context['route'] ?? null);
        $this->assertArrayHasKey('has_session_cookie', $csrfLogs[0]->context);
        $this->assertArrayHasKey('has_token_input', $csrfLogs[0]->context);
        $this->assertTrue((bool) ($csrfLogs[0]->context['has_token_input'] ?? false));
    }
}
