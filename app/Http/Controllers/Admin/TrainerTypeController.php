<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreTrainerTypeRequest;
use App\Http\Requests\Admin\UpdateTrainerTypeRequest;
use App\Models\TrainerType;
use App\Services\PartnerContext;
use App\Services\Trainers\TrainerTypeCatalog;
use App\Support\TrainerTypeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerTypeController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TrainerTypeCatalog $catalog,
    ) {
        parent::__construct($partnerContext);
    }

    public function index(): JsonResponse
    {
        $this->assertCanView();
        $partnerId = $this->requirePartnerId();

        $types = $this->catalog->typesForPartner($partnerId)
            ->map(fn (TrainerType $type) => $this->catalog->payload($type))
            ->values()
            ->all();

        return response()->json([
            'types' => $types,
            'can_manage' => TrainerTypeAccess::canManageCatalog(),
        ]);
    }

    public function store(StoreTrainerTypeRequest $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $type = $this->catalog->create($partnerId, $request->validated());

        return response()->json([
            'message' => 'Тип тренера создан',
            'trainer_type' => $this->payloadWithCount($type),
        ]);
    }

    public function show(TrainerType $trainerType): JsonResponse
    {
        $this->assertCanView();
        $this->assertPartnerType($trainerType);

        return response()->json($this->payloadWithCount($trainerType));
    }

    public function update(UpdateTrainerTypeRequest $request, TrainerType $trainerType): JsonResponse
    {
        $this->assertPartnerType($trainerType);
        $type = $this->catalog->update($trainerType, $request->validated());

        return response()->json([
            'message' => 'Тип тренера обновлён',
            'trainer_type' => $this->payloadWithCount($type),
        ]);
    }

    public function destroy(Request $request, TrainerType $trainerType): JsonResponse
    {
        if (! TrainerTypeAccess::canManageCatalog($request->user())) {
            abort(403);
        }

        $this->assertPartnerType($trainerType);
        $this->catalog->delete($trainerType);

        return response()->json([
            'message' => 'Тип тренера удалён',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadWithCount(TrainerType $type): array
    {
        $type->loadCount([
            'trainerProfiles as trainer_profiles_count' => static function ($query): void {
                $query->withTrashed();
            },
        ]);

        return $this->catalog->payload($type);
    }

    private function assertCanView(): void
    {
        if (! TrainerTypeAccess::canViewCatalog()) {
            abort(403);
        }
    }

    private function assertPartnerType(TrainerType $type): void
    {
        $partnerId = $this->requirePartnerId();
        if ((int) $type->partner_id !== $partnerId) {
            abort(404);
        }
    }
}
