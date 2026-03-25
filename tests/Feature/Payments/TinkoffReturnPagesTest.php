<?php

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Публичные страницы возврата T‑Bank (SuccessURL / FailURL), без admin2.
 */
class TinkoffReturnPagesTest extends CrmTestCase
{
    private const ORDER = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_guest_success_page_200_and_home_redirect_hint(): void
    {
        Auth::logout();

        $this->get(route('payments.tinkoff.success', self::ORDER))
            ->assertOk()
            ->assertSee('Оплата принята', false)
            ->assertSee('главную страницу', false);
    }

    public function test_guest_fail_page_200_and_home_redirect_hint(): void
    {
        Auth::logout();

        $this->get(route('payments.tinkoff.fail', self::ORDER))
            ->assertOk()
            ->assertSee('не завершена', false)
            ->assertSee('главную страницу', false);
    }

    public function test_authenticated_user_sees_cabinet_redirect_hint_on_success(): void
    {
        $this->get(route('payments.tinkoff.success', self::ORDER))
            ->assertOk()
            ->assertSee('личный кабинет', false);
    }

    public function test_authenticated_user_sees_cabinet_redirect_hint_on_fail(): void
    {
        $this->get(route('payments.tinkoff.fail', self::ORDER))
            ->assertOk()
            ->assertSee('личный кабинет', false);
    }
}
