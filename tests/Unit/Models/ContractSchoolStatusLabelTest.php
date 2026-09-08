<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use Tests\TestCase;

/**
 * Школьные подписи sent/opened не меняют словарь $STATUS_RU (аудит, вебхуки, кабинет).
 */
final class ContractSchoolStatusLabelTest extends TestCase
{
    public function test_school_label_overrides_only_sent_and_opened(): void
    {
        $this->assertSame('Отправлено', Contract::$STATUS_RU[Contract::STATUS_SENT]);
        $this->assertSame('Открыто', Contract::$STATUS_RU[Contract::STATUS_OPENED]);
        $this->assertSame('Отправлено СМС', Contract::schoolStatusLabel(Contract::STATUS_SENT));
        $this->assertSame('Открыто СМС', Contract::schoolStatusLabel(Contract::STATUS_OPENED));
        $this->assertSame('Черновик', Contract::schoolStatusLabel(Contract::STATUS_DRAFT));
        $this->assertSame('Подписано', Contract::schoolStatusLabel(Contract::STATUS_SIGNED));
        $this->assertSame('Отозвано', Contract::schoolStatusLabel(Contract::STATUS_REVOKED));
        $this->assertSame('Ожидает заполнения клиентом', Contract::schoolStatusLabel(Contract::STATUS_AWAITING_CLIENT_FILL));
    }

    public function test_accessors_keep_status_ru_and_school_status_ru_apart(): void
    {
        $sent = new Contract(['status' => Contract::STATUS_SENT]);
        $opened = new Contract(['status' => Contract::STATUS_OPENED]);
        $draft = new Contract(['status' => Contract::STATUS_DRAFT]);

        $this->assertSame('Отправлено', $sent->status_ru);
        $this->assertSame('Отправлено СМС', $sent->school_status_ru);
        $this->assertSame('Открыто', $opened->status_ru);
        $this->assertSame('Открыто СМС', $opened->school_status_ru);
        $this->assertSame('Черновик', $draft->status_ru);
        $this->assertSame('Черновик', $draft->school_status_ru);
    }
}
