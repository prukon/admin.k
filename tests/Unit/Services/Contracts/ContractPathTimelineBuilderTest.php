<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractPathTimelineBuilder;
use Tests\Feature\Crm\CrmTestCase;

final class ContractPathTimelineBuilderTest extends CrmTestCase
{
    private ContractPathTimelineBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ContractPathTimelineBuilder();
    }

    public function test_schematic_template_starts_from_admin_sent_with_email_and_cabinet(): void
    {
        $steps = $this->builder->schematicTemplateSteps();

        $this->assertSame('admin_sent', $steps[0]['key']);
        $this->assertSame('Админ отправил договор родителю', $steps[0]['label']);
        $this->assertSame('done', $steps[0]['state']);
        $this->assertStringContainsString('70', $steps[0]['hint']);
        $this->assertStringContainsString('письмо', $steps[0]['hint']);
        $this->assertStringContainsString('кабинете', $steps[0]['hint']);
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $steps[1]['key']);
        $this->assertSame('active', $steps[1]['state']);
        $this->assertStringContainsString('Сформировать договор', $steps[1]['hint']);
        $this->assertStringContainsString('Система собирает PDF из шаблона', $steps[1]['hint']);
        $this->assertStringNotContainsString('заполняет форму в кабинете', $steps[1]['hint']);

        $draft = collect($steps)->firstWhere('key', Contract::STATUS_DRAFT);
        $this->assertNotNull($draft);
        $this->assertStringContainsString('прочитать и проверить заполненный договор', $draft['hint']);
        $this->assertStringContainsString('Подписать договор', $draft['hint']);
        $this->assertStringContainsString('будет отправлена SMS на подпись', $draft['hint']);

        $opened = collect($steps)->firstWhere('key', Contract::STATUS_OPENED);
        $this->assertNotNull($opened);
        $this->assertSame('Родитель открыл договор, но ещё не подписал.', $opened['hint']);

        $signed = collect($steps)->firstWhere('key', Contract::STATUS_SIGNED);
        $this->assertNotNull($signed);
        $this->assertSame('Родитель подписал договор. Можно скачать подписанный PDF.', $signed['hint']);
    }

    public function test_template_awaiting_marks_admin_sent_done_and_fill_active(): void
    {
        $contract = $this->makeContract([
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
        ]);

        $steps = $this->builder->build($contract);

        $this->assertSame('Путь с формой клиенту', $this->builder->title($contract));
        $this->assertSame('done', $steps[0]['state']);
        $this->assertSame('active', $steps[1]['state']);
        $this->assertSame('pending', $steps[2]['state']);
        $this->assertSame(Contract::STATUS_SIGNED, $steps[array_key_last($steps)]['key']);
        $this->assertSame('pending', $steps[array_key_last($steps)]['state']);
    }

    public function test_pdf_draft_marks_first_step_active(): void
    {
        $contract = $this->makeContract([
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'status' => Contract::STATUS_DRAFT,
            'source_pdf_path' => 'documents/test.pdf',
        ]);

        $steps = $this->builder->build($contract);

        $this->assertSame('Путь с готовым PDF', $this->builder->title($contract));
        $this->assertCount(4, $steps);
        $this->assertSame('active', $steps[0]['state']);
        $this->assertSame('pending', $steps[1]['state']);
    }

    public function test_signed_marks_all_steps_done(): void
    {
        $contract = $this->makeContract([
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'status' => Contract::STATUS_SIGNED,
            'source_pdf_path' => 'documents/test.pdf',
            'signed_at' => now(),
        ]);

        $states = array_column($this->builder->build($contract), 'state');
        $this->assertSame(['done', 'done', 'done', 'done'], $states);
    }

    public function test_template_revoked_before_pdf_fails_awaiting_step(): void
    {
        $contract = $this->makeContract([
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_REVOKED,
            'source_pdf_path' => null,
        ]);

        $steps = $this->builder->build($contract);
        $awaiting = collect($steps)->firstWhere('key', Contract::STATUS_AWAITING_CLIENT_FILL);

        $this->assertNotNull($awaiting);
        $this->assertSame('failed', $awaiting['state']);
        $this->assertStringContainsString('Отозвано', $awaiting['hint']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeContract(array $overrides): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => $student->team_id,
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));
    }
}
