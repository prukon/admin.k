<?php

namespace Tests\Unit\Services\Audit;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use App\Models\MyLog;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Crm\CrmTestCase;

class AuditLoggerTest extends CrmTestCase
{
    #[Test]
    public function it_persists_event_and_level_without_legacy_type_and_action(): void
    {
        $author = $this->user;
        $subject = User::factory()->create(['partner_id' => $this->partner->id]);

        $this->actingAs($author);
        app()->instance('current_partner', $this->partner);

        $log = app(AuditLogger::class)->record(
            AuditEvent::UserUpdated,
            AuditContext::make('Email: old → new')
                ->withUser($subject)
                ->withTarget($subject, $subject->name)
        );

        $this->assertInstanceOf(MyLog::class, $log);
        $this->assertSame('user.updated', $log->event);
        $this->assertSame(AuditLevel::Info, $log->level);
        $this->assertNull($log->type);
        $this->assertNull($log->action);
        $this->assertSame($author->id, $log->author_id);
        $this->assertSame($this->partner->id, $log->partner_id);
        $this->assertSame($subject->id, $log->user_id);
        $this->assertSame($subject->getMorphClass(), $log->target_type);
        $this->assertSame($subject->id, $log->target_id);
        $this->assertSame($subject->name, $log->target_label);
        $this->assertSame('Email: old → new', $log->description);
    }

    #[Test]
    public function it_resolves_event_label_on_model_after_persist(): void
    {
        $log = app(AuditLogger::class)->record(
            AuditEvent::RolePermissionGranted,
            AuditContext::make('test')
                ->withPartnerId((int) $this->partner->id)
                ->withAuthorId((int) $this->user->id)
        );

        $this->assertSame(AuditEvent::RolePermissionGranted, $log->resolvedEvent());
        $this->assertSame('Назначение права роли', $log->eventLabel());
    }

    #[Test]
    public function legacy_my_log_rows_without_event_still_resolve_label(): void
    {
        $log = MyLog::query()->create([
            'type' => 700,
            'action' => 741,
            'author_id' => null,
            'description' => 'legacy row',
            'created_at' => now(),
        ]);

        $this->assertNull($log->event);
        $this->assertSame(AuditEvent::RolePermissionGranted, $log->resolvedEvent());
        $this->assertSame('Назначение права роли', $log->eventLabel());
    }
}
