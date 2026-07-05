<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\User\ImportCommitRequest;
use App\Http\Requests\User\ImportPreviewRequest;
use App\Services\PartnerContext;
use App\Services\Users\Import\UsersImportService;
use App\Services\Users\Import\UsersImportTemplateBuilder;
use Illuminate\Http\JsonResponse;

class UserImportController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly UsersImportTemplateBuilder $templateBuilder,
        private readonly UsersImportService $importService,
    ) {
        parent::__construct($partnerContext);
    }

    public function template()
    {
        return $this->templateBuilder->downloadResponse();
    }

    public function preview(ImportPreviewRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $actorId = (int) ($this->currentUser()?->id ?? 0);

        $payload = $this->importService->previewAndStoreToken(
            $request->file('file'),
            $partnerId,
            $actorId,
        );

        $result = $payload['result'];
        $body = $result->toArray();

        if (! $result->valid) {
            return response()->json([
                'message' => 'Импорт не выполнен: найдены ошибки в файле.',
                ...$body,
            ], 422);
        }

        return response()->json([
            'message' => 'Файл проверен успешно. Подтвердите импорт.',
            'import_token' => $payload['token'],
            ...$body,
        ]);
    }

    public function commit(ImportCommitRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $actorId = (int) ($this->currentUser()?->id ?? 0);

        try {
            $stats = $this->importService->commit(
                (string) $request->validated('import_token'),
                $partnerId,
                $actorId,
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => sprintf(
                'Импорт завершён: создано %d, обновлено %d.',
                $stats['created'],
                $stats['updated'],
            ),
            'created' => $stats['created'],
            'updated' => $stats['updated'],
        ]);
    }
}
