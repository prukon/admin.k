<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Payments;

use App\Support\Payments\EmailNewsletterPaymentSource;
use PHPUnit\Framework\TestCase;

class EmailNewsletterPaymentSourceTest extends TestCase
{
    public function test_is_from_meta_accepts_boolean_and_one(): void
    {
        $this->assertTrue(EmailNewsletterPaymentSource::isFromMeta(['up_public_pay' => true]));
        $this->assertTrue(EmailNewsletterPaymentSource::isFromMeta(['up_public_pay' => 1]));
        $this->assertTrue(EmailNewsletterPaymentSource::isFromMeta(['up_public_pay' => '1']));
        $this->assertTrue(EmailNewsletterPaymentSource::isFromMeta(
            json_encode(['up_public_pay' => true], JSON_UNESCAPED_UNICODE)
        ));
    }

    public function test_is_from_meta_rejects_package_public_pay_and_empty(): void
    {
        $this->assertFalse(EmailNewsletterPaymentSource::isFromMeta(['ulp_public_pay' => true]));
        $this->assertFalse(EmailNewsletterPaymentSource::isFromMeta(['method' => 'sbp']));
        $this->assertFalse(EmailNewsletterPaymentSource::isFromMeta(null));
        $this->assertFalse(EmailNewsletterPaymentSource::isFromMeta(''));
        $this->assertFalse(EmailNewsletterPaymentSource::isFromMeta('{not json'));
    }

    public function test_label_from_meta(): void
    {
        $this->assertSame(
            EmailNewsletterPaymentSource::LABEL,
            EmailNewsletterPaymentSource::labelFromMeta(['up_public_pay' => true])
        );
        $this->assertSame('', EmailNewsletterPaymentSource::labelFromMeta(['ulp_public_pay' => true]));
    }
}
