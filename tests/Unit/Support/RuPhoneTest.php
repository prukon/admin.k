<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RuPhone;
use PHPUnit\Framework\TestCase;

final class RuPhoneTest extends TestCase
{
    public function test_format_masked_for_display_keeps_area_and_last_two_digits(): void
    {
        $this->assertSame('+7 (900) ***-**-33', RuPhone::formatMaskedForDisplay('79001112233'));
        $this->assertSame('+7 (906) ***-**-08', RuPhone::formatMaskedForDisplay('+7 (906) 247-55-08'));
        $this->assertSame('+7 (906) ***-**-08', RuPhone::formatMaskedForDisplay('89062475508'));
        $this->assertSame('+7 (906) ***-**-08', RuPhone::formatMaskedForDisplay('9062475508'));
    }

    public function test_format_masked_for_display_empty_and_invalid(): void
    {
        $this->assertSame('', RuPhone::formatMaskedForDisplay(null));
        $this->assertSame('', RuPhone::formatMaskedForDisplay(''));
        $this->assertSame('', RuPhone::formatMaskedForDisplay('123'));
    }
}
