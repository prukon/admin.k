<?php

namespace App\Services\Users\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class UsersImportService
{
    private const CACHE_PREFIX = 'users_import:';
    private const CACHE_TTL_SECONDS = 900;

    public function __construct(
        private readonly UsersExcelReader $reader,
        private readonly UsersImportValidator $validator,
        private readonly UsersImportCommitService $commitService,
    ) {
    }

    public function preview(UploadedFile $file, int $partnerId, int $actorId): UsersImportValidationResult
    {
        $parsed = $this->reader->read($file);

        return $this->validator->validate(
            $parsed['rows'],
            $parsed['errors'],
            $partnerId,
        );
    }

    /**
     * @return array{token: string, result: UsersImportValidationResult}
     */
    public function previewAndStoreToken(UploadedFile $file, int $partnerId, int $actorId): array
    {
        $result = $this->preview($file, $partnerId, $actorId);

        if (! $result->valid) {
            return [
                'token' => '',
                'result' => $result,
            ];
        }

        $token = (string) Str::uuid();
        Cache::put(
            self::CACHE_PREFIX . $token,
            [
                'partner_id' => $partnerId,
                'actor_id' => $actorId,
                'rows' => array_map(static fn (UsersImportRow $row) => $row->toCacheArray(), $result->rows),
            ],
            self::CACHE_TTL_SECONDS,
        );

        return [
            'token' => $token,
            'result' => $result,
        ];
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function commit(string $token, int $partnerId, int $actorId): array
    {
        $payload = Cache::get(self::CACHE_PREFIX . $token);

        if (! is_array($payload)) {
            throw new \RuntimeException('Сессия импорта истекла или не найдена. Загрузите файл повторно.');
        }

        if ((int) ($payload['partner_id'] ?? 0) !== $partnerId || (int) ($payload['actor_id'] ?? 0) !== $actorId) {
            throw new \RuntimeException('Сессия импорта недоступна для текущего пользователя.');
        }

        $rows = array_map(
            static fn (array $row) => UsersImportRow::fromCacheArray($row),
            (array) ($payload['rows'] ?? []),
        );

        if ($rows === []) {
            throw new \RuntimeException('Нет данных для импорта.');
        }

        $result = $this->commitService->commit($rows, $partnerId, $actorId);
        Cache::forget(self::CACHE_PREFIX . $token);

        return $result;
    }
}
