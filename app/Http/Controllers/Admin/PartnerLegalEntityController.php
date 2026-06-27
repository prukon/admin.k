<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Enums\PartnerLegalEntityBusinessType;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StorePartnerLegalEntityRequest;
use App\Http\Requests\Admin\UpdatePartnerLegalEntityRequest;
use App\Http\Requests\PartnerLegalEntity\FilterRequest;
use App\Http\Requests\PartnerLegalEntity\SmPatchPartnerLegalEntityRequest;
use App\Http\Requests\PartnerLegalEntity\SmRegisterPartnerLegalEntityRequest;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use App\Services\Tinkoff\PartnerLegalEntitySmRegisterService;
use App\Support\BuildsLogTable;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PartnerLegalEntityController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly PartnerLegalEntitySmRegisterService $smRegisterService,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $this->requirePartnerId();

        return view('admin.legal-entities.index');
    }

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'draw' => 'nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $baseQuery = PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->withCount('teams');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search === '' && $request->filled('search.value')) {
            $search = trim((string) $request->input('search.value'));
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseQuery->where(function ($q) use ($like, $search) {
                $q->where('title', 'like', $like)
                    ->orWhere('organization_name', 'like', $like)
                    ->orWhere('tax_id', 'like', $like)
                    ->orWhere('tinkoff_shop_code', 'like', $like);

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('is_enabled', 0);
            }
        }

        $totalRecords = PartnerLegalEntity::where('partner_id', $partnerId)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef = $request->input('columns', []);
        $orderColumnName = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'business_type_label':
                $baseQuery->orderBy('business_type', $orderDir)->orderBy('title', 'asc');
                break;
            case 'tax_id':
                $baseQuery->orderBy('tax_id', $orderDir)->orderBy('title', 'asc');
                break;
            case 'teams_count':
                $baseQuery->orderBy('teams_count', $orderDir)->orderBy('title', 'asc');
                break;
            case 'is_default_label':
                $baseQuery->orderBy('is_default', $orderDir)->orderBy('title', 'asc');
                break;
            case 'is_enabled_label':
                $baseQuery->orderBy('is_enabled', $orderDir)->orderBy('title', 'asc');
                break;
            case 'title':
            default:
                $baseQuery->orderBy('title', $orderDir)->orderBy('id', 'asc');
                break;
        }

        $start = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $entities = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $entities->map(function (PartnerLegalEntity $entity) {
            $businessType = $entity->business_type instanceof PartnerLegalEntityBusinessType
                ? $entity->business_type
                : PartnerLegalEntityBusinessType::tryFrom((string) $entity->business_type);

            return [
                'id' => $entity->id,
                'title' => $entity->title,
                'business_type' => $businessType?->value ?? (string) $entity->business_type,
                'business_type_label' => $businessType?->label() ?? '—',
                'tax_id' => $entity->tax_id ?? '',
                'tinkoff_shop_code' => $entity->tinkoff_shop_code ?? '',
                'is_registered' => $entity->is_registered ? 1 : 0,
                'is_registered_label' => $entity->is_registered ? 'Да' : 'Нет',
                'is_default' => (int) $entity->is_default,
                'is_default_label' => $entity->is_default ? 'Да' : 'Нет',
                'is_enabled' => (int) $entity->is_enabled,
                'is_enabled_label' => $entity->is_enabled ? 'Да' : 'Нет',
                'teams_count' => (int) $entity->teams_count,
                'show_url' => route('admin.legal-entities.show', $entity),
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(StorePartnerLegalEntityRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $this->normalizeEntityPayload($request->validated());
        $data['partner_id'] = $partnerId;

        if (! PartnerLegalEntity::where('partner_id', $partnerId)->exists()) {
            $data['is_default'] = true;
        }

        try {
            $entity = PartnerLegalEntity::create($data);
            $this->syncDefaultFlag($partnerId, $entity, (bool) $entity->is_default);

            $this->auditLogger->record(
                AuditEvent::LegalEntityCreated,
                AuditContext::make($this->formatEntitySnapshotDescription($entity))
                    ->withTarget($entity, $entity->title)
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );
        } catch (QueryException $e) {
            return $this->handleUniqueViolation($e);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Юр. лицо создано',
                'legal_entity' => $entity,
            ]);
        }

        return redirect()->route('admin.legal-entities.index');
    }

    public function show(PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        if (request()->ajax() || request()->expectsJson()) {
            return response()->json($this->entityJsonPayload($legalEntity));
        }

        $partner = Partner::findOrFail($partnerId);

        return view('admin.legal-entities.show', [
            'entity' => $legalEntity,
            'partner' => $partner,
        ]);
    }

    public function update(UpdatePartnerLegalEntityRequest $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        $beforeSnapshot = $this->entityAuditSnapshot($legalEntity);

        $data = $this->normalizeEntityPayload($request->validated());

        if ($guardResponse = $this->rejectDisableWhenTeamsLinked($legalEntity, $data, $request)) {
            return $guardResponse;
        }

        if ($guardResponse = $this->rejectRegisteredSmFieldChangesViaCrud($legalEntity, $data, $request)) {
            return $guardResponse;
        }

        try {
            $legalEntity->update($data);
            $legalEntity->refresh();
            $this->syncDefaultFlag($partnerId, $legalEntity, (bool) $legalEntity->is_default);

            $changes = $this->diffEntityAuditSnapshots(
                $beforeSnapshot,
                $this->entityAuditSnapshot($legalEntity),
            );

            if ($changes !== []) {
                $this->auditLogger->record(
                    AuditEvent::LegalEntityUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withTarget($legalEntity, $legalEntity->title)
                        ->withCreatedAt(now())
                );
            }
        } catch (QueryException $e) {
            return $this->handleUniqueViolation($e);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => 'Юр. лицо обновлено']);
        }

        return redirect()
            ->route('admin.legal-entities.show', $legalEntity)
            ->with('ok', 'Юр. лицо обновлено');
    }

    public function destroy(Request $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        if ($legalEntity->teams()->exists()) {
            return response()->json([
                'message' => 'Нельзя удалить юр. лицо, привязанное к группам',
                'errors' => [
                    'legal_entity' => ['Сначала отвяжите группы от этого юр. лица'],
                ],
            ], 422);
        }

        $this->auditLogger->record(
            AuditEvent::LegalEntityDeleted,
            AuditContext::make("Юр. лицо удалено: {$legalEntity->title}. ID: {$legalEntity->id}.")
                ->withTarget($legalEntity, $legalEntity->title)
                ->withCreatedAt(now())
        );

        $legalEntity->delete();

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Юр. лицо удалено',
                'success' => true,
            ]);
        }

        return redirect()->route('admin.legal-entities.index');
    }

    public function smRegister(SmRegisterPartnerLegalEntityRequest $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        if (trim((string) ($legalEntity->tinkoff_shop_code ?? '')) !== '') {
            return $this->smErrorResponse($request, 'Юр. лицо уже зарегистрировано (есть ShopCode). Используйте обновление.', 422);
        }

        $partner = Partner::findOrFail($partnerId);

        try {
            $result = $this->smRegisterService->register($legalEntity, $partner, $request->validated());
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][legal_entity] ' . $e->getMessage());

            return $this->smErrorResponse($request, 'Ошибка регистрации: ' . $e->getMessage(), 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'shopCode' => $result['shopCode'],
                'status' => $result['status'],
            ]);
        }

        return redirect()
            ->route('admin.legal-entities.show', $legalEntity)
            ->with('ok', 'Юр. лицо зарегистрировано в sm-register');
    }

    public function smPatch(SmPatchPartnerLegalEntityRequest $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        $partner = Partner::findOrFail($partnerId);

        try {
            $this->smRegisterService->patch($legalEntity, $partner, $request->validated());
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][legal_entity][patch] ' . $e->getMessage());

            return $this->smErrorResponse($request, $e->getMessage(), 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('admin.legal-entities.show', $legalEntity)
            ->with('ok', 'Данные обновлены в sm-register');
    }

    public function smRefresh(Request $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        try {
            $result = $this->smRegisterService->refreshStatus($legalEntity);
        } catch (\Throwable $e) {
            return $this->smErrorResponse($request, $e->getMessage(), 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $result['status']]);
        }

        return back()->with('ok', 'Статус обновлён: ' . ($result['status'] ?? '—'));
    }

    public function smPull(Request $request, PartnerLegalEntity $legalEntity)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertEntityBelongsToPartner($legalEntity, $partnerId);

        try {
            $result = $this->smRegisterService->pullFromRemote($legalEntity);
        } catch (\Throwable $e) {
            return $this->smErrorResponse($request, $e->getMessage(), 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['ok' => true, 'changed' => $result['changed']]);
        }

        return back()->with('ok', 'Реквизиты подтянуты из sm-register');
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('legal_entity');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeEntityPayload(array $data): array
    {
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);

        if (array_key_exists('organization_name', $data) && $data['organization_name'] === '') {
            $data['organization_name'] = null;
        }

        if (array_key_exists('ceo', $data) && is_array($data['ceo'])) {
            $ceo = $data['ceo'];
            $data['ceo'] = [
                'lastName' => (string) ($ceo['lastName'] ?? ''),
                'firstName' => (string) ($ceo['firstName'] ?? ''),
                'middleName' => (string) ($ceo['middleName'] ?? ''),
                'phone' => (string) ($ceo['phone'] ?? ''),
            ];
        }

        return $data;
    }

    private function syncDefaultFlag(int $partnerId, PartnerLegalEntity $entity, bool $isDefault): void
    {
        if (! $isDefault) {
            return;
        }

        PartnerLegalEntity::query()
            ->where('partner_id', $partnerId)
            ->where('id', '!=', $entity->id)
            ->update(['is_default' => false]);
    }

    private function assertEntityBelongsToPartner(PartnerLegalEntity $entity, int $partnerId): void
    {
        if ((int) $entity->partner_id !== $partnerId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entityJsonPayload(PartnerLegalEntity $entity): array
    {
        $businessType = $entity->business_type instanceof PartnerLegalEntityBusinessType
            ? $entity->business_type->value
            : (string) $entity->business_type;

        return [
            'id' => $entity->id,
            'business_type' => $businessType,
            'title' => $entity->title,
            'organization_name' => $entity->organization_name,
            'tax_id' => $entity->tax_id,
            'kpp' => $entity->kpp,
            'registration_number' => $entity->registration_number,
            'city' => $entity->city,
            'zip' => $entity->zip,
            'address' => $entity->address,
            'bank_name' => $entity->bank_name,
            'bank_bik' => $entity->bank_bik,
            'bank_account' => $entity->bank_account,
            'sm_details_template' => $entity->sm_details_template,
            'ceo' => $this->normalizeCeoForJson($entity->ceo),
            'taxation_system' => $entity->taxation_system,
            'vat' => $entity->vat,
            'sms_name' => $entity->sms_name,
            'is_registered' => $entity->is_registered ? 1 : 0,
            'tinkoff_shop_code' => $entity->tinkoff_shop_code,
            'sm_register_status' => $entity->sm_register_status,
            'is_default' => (int) $entity->is_default,
            'is_enabled' => (int) $entity->is_enabled,
        ];
    }

    private function handleUniqueViolation(QueryException $e)
    {
        $code = $e->errorInfo[1] ?? null;
        if ((int) $code === 1062) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => [
                    'tax_id' => ['Юр. лицо с таким ИНН уже существует'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Ошибка сохранения',
            'error' => $e->getMessage(),
        ], 422);
    }

    private function smErrorResponse(Request $request, string $message, int $status)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => ['sm' => [$message]],
            ], $status);
        }

        return back()->withErrors(['sm' => $message]);
    }

    /**
     * @return array<string, string>
     */
    private function entityAuditSnapshot(PartnerLegalEntity $entity): array
    {
        $businessType = $entity->business_type instanceof PartnerLegalEntityBusinessType
            ? $entity->business_type->label()
            : PartnerLegalEntityBusinessType::labelFor((string) $entity->business_type);

        return [
            'title' => (string) ($entity->title ?? ''),
            'business_type' => $businessType,
            'organization_name' => $this->auditTextValue($entity->organization_name, 'не указано'),
            'tax_id' => $this->auditTextValue($entity->tax_id, 'не указано'),
            'is_default' => $entity->is_default ? 'Да' : 'Нет',
            'is_enabled' => $entity->is_enabled ? 'Да' : 'Нет',
        ];
    }

    private function formatEntitySnapshotDescription(PartnerLegalEntity $entity): string
    {
        $snapshot = $this->entityAuditSnapshot($entity);

        return implode("\n", [
            "Наименование: {$snapshot['title']}",
            "Форма: {$snapshot['business_type']}",
            "Организация: {$snapshot['organization_name']}",
            "ИНН: {$snapshot['tax_id']}",
            "Основное: {$snapshot['is_default']}",
            "Активность: {$snapshot['is_enabled']}",
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffEntityAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'title' => 'Наименование',
            'business_type' => 'Форма',
            'organization_name' => 'Организация',
            'tax_id' => 'ИНН',
            'is_default' => 'Основное',
            'is_enabled' => 'Активность',
        ];

        $changes = [];

        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    private function auditTextValue(mixed $value, string $emptyLabel): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $emptyLabel;
    }

    /**
     * Поля, которые после sm-register меняются только через sm-patch (не CRM CRUD).
     *
     * @var list<string>
     */
    private const REGISTERED_CRUD_LOCKED_FIELDS = [
        'business_type',
        'title',
        'organization_name',
        'tax_id',
        'kpp',
        'registration_number',
        'city',
        'zip',
        'address',
        'bank_name',
        'bank_bik',
        'bank_account',
        'sm_details_template',
        'sms_name',
        'ceo',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function rejectDisableWhenTeamsLinked(
        PartnerLegalEntity $entity,
        array $data,
        Request $request,
    ): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null {
        $disabling = array_key_exists('is_enabled', $data)
            && ! $data['is_enabled']
            && $entity->is_enabled;

        if (! $disabling || ! $entity->teams()->exists()) {
            return null;
        }

        return $this->guardrailResponse($request, [
            'is_enabled' => [
                'Нельзя отключить юр. лицо, привязанное к группам. Сначала отвяжите группы или назначьте им другое юр. лицо.',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rejectRegisteredSmFieldChangesViaCrud(
        PartnerLegalEntity $entity,
        array $data,
        Request $request,
    ): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|null {
        if (! $entity->is_registered) {
            return null;
        }

        foreach (self::REGISTERED_CRUD_LOCKED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($this->registeredFieldValueChanged($entity, $field, $data[$field])) {
                return $this->guardrailResponse($request, [
                    $field => [
                        'После регистрации в T‑Bank это поле меняется через форму «Обновить в sm-register», а не через карточку CRM.',
                    ],
                ]);
            }
        }

        return null;
    }

    private function registeredFieldValueChanged(PartnerLegalEntity $entity, string $field, mixed $newValue): bool
    {
        $oldValue = $entity->{$field};

        if ($field === 'business_type') {
            $old = $oldValue instanceof PartnerLegalEntityBusinessType
                ? $oldValue->value
                : (string) $oldValue;
            $new = $newValue instanceof PartnerLegalEntityBusinessType
                ? $newValue->value
                : (string) $newValue;

            return $old !== $new;
        }

        if ($field === 'ceo') {
            return $this->ceoPayloadChanged(
                is_array($oldValue) ? $oldValue : null,
                is_array($newValue) ? $newValue : null,
            );
        }

        return trim((string) ($oldValue ?? '')) !== trim((string) ($newValue ?? ''));
    }

    /**
     * @return array{lastName: string, firstName: string, middleName: string, phone: string}
     */
    private function normalizeCeoForJson(mixed $ceo): array
    {
        $src = is_array($ceo) ? $ceo : [];

        return [
            'lastName' => (string) ($src['lastName'] ?? $src['last_name'] ?? ''),
            'firstName' => (string) ($src['firstName'] ?? $src['first_name'] ?? ''),
            'middleName' => (string) ($src['middleName'] ?? $src['middle_name'] ?? ''),
            'phone' => (string) ($src['phone'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function ceoPayloadChanged(?array $before, ?array $after): bool
    {
        $normalizedBefore = $this->normalizeCeoForJson($before);
        $normalizedAfter = $this->normalizeCeoForJson($after);

        foreach (['lastName', 'firstName', 'middleName', 'phone'] as $key) {
            if ($normalizedBefore[$key] !== $normalizedAfter[$key]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function guardrailResponse(
        Request $request,
        array $errors,
    ): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => $errors,
            ], 422);
        }

        return back()->withErrors($errors);
    }
}
