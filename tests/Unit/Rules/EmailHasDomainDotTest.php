<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\EmailHasDomainDot;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class EmailHasDomainDotTest extends TestCase
{
    public function test_rejects_domain_without_dot(): void
    {
        $validator = Validator::make(
            ['email' => 'y.proreshkina@mail'],
            ['email' => ['email', new EmailHasDomainDot]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(EmailHasDomainDot::MESSAGE, $validator->errors()->first('email'));
    }

    public function test_accepts_domain_with_dot(): void
    {
        $validator = Validator::make(
            ['email' => 'name@mail.ru'],
            ['email' => ['email', new EmailHasDomainDot]],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_skips_empty_and_missing_at(): void
    {
        $empty = Validator::make(
            ['email' => ''],
            ['email' => ['nullable', new EmailHasDomainDot]],
        );
        $this->assertFalse($empty->fails());

        $noAt = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => [new EmailHasDomainDot]],
        );
        $this->assertFalse($noAt->fails());
    }
}
