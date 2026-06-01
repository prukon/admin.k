<?php

namespace App\Services\Audit;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Контекст одной записи аудита для {@see AuditLogger}.
 */
final class AuditContext
{
    public function __construct(
        public string $description,
        public ?Model $target = null,
        public ?User $user = null,
        public ?int $userId = null,
        public ?int $authorId = null,
        public ?int $partnerId = null,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $targetLabel = null,
        public ?DateTimeInterface $createdAt = null,
    ) {}

    public static function make(string $description): self
    {
        return new self(description: $description);
    }

    public function withTarget(Model $target, ?string $label = null): self
    {
        $clone = clone $this;
        $clone->target = $target;
        $clone->targetLabel = $label;

        return $clone;
    }

    public function withTargetReference(string $targetType, int $targetId, ?string $targetLabel = null): self
    {
        $clone = clone $this;
        $clone->target = null;
        $clone->targetType = $targetType;
        $clone->targetId = $targetId;
        $clone->targetLabel = $targetLabel;

        return $clone;
    }

    public function withUser(User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;
        $clone->userId = (int) $user->getKey();

        return $clone;
    }

    public function withUserId(int $userId): self
    {
        $clone = clone $this;
        $clone->userId = $userId;

        return $clone;
    }

    public function withAuthorId(?int $authorId): self
    {
        $clone = clone $this;
        $clone->authorId = $authorId;

        return $clone;
    }

    public function withPartnerId(?int $partnerId): self
    {
        $clone = clone $this;
        $clone->partnerId = $partnerId;

        return $clone;
    }

    public function withCreatedAt(DateTimeInterface $createdAt): self
    {
        $clone = clone $this;
        $clone->createdAt = $createdAt;

        return $clone;
    }
}
