<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerContext;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use App\Models\MyLog;

class PartnerSettingController extends AdminBaseController
{
    protected UserService $service;

    public function __construct(UserService $service, PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
        $this->service = $service;
    }

    public function partner()
    {
        $partner = $this->requirePartner();
        $user = $this->currentUser();
        $partners = collect([$partner]);

        // Вкладка «Организация» не использует группы; переменная нужна шаблону account.index.
        $allTeams = collect();

        return view('account.index', ['activeTab' => 'partner'], compact(
            'user',
            'partners',
            'partner',
            'allTeams',
        ));
    }

    public function updatePartner(UpdateRequest $request, Partner $partner)
    {
        if ((int) $partner->id !== $this->requirePartnerId()) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещён.',
            ], 403);
        }

        $authorId = auth()->id();

        $oldData = $partner->toArray();
        $data = $request->validated();

        if (array_key_exists('organization_name', $data)) {
            $data['organization_name'] = trim((string) $data['organization_name']);
            $data['organization_name'] = $data['organization_name'] === '' ? null : $data['organization_name'];
        }

        DB::transaction(function () use ($partner, $authorId, $oldData, $data) {
            $changedFields = [];
            foreach ($data as $key => $newValue) {
                $oldValue = $oldData[$key] ?? null;

                $oldValueNormalized = ($oldValue === '' ? null : $oldValue);
                $newValueNormalized = ($newValue === '' ? null : $newValue);

                if ($oldValueNormalized != $newValueNormalized) {
                    $changedFields[] = $key;
                }
            }

            $oldTitle = $oldData['title'] ?? null;
            $oldId = $oldData['id'] ?? null;

            if (empty($changedFields)) {
                return;
            }

            $partner->update($data);

            $businessTypeTranslate = [
                'company'                     => 'ООО',
                'individual_entrepreneur'     => 'ИП',
                'physical_person'             => 'Физ. лицо',
                'non_commercial_organization' => 'НКО',
            ];

            $oldString = '(' . implode(', ', array_map(function ($key) use ($oldData) {
                return $oldData[$key] ?? '';
            }, $changedFields)) . ')';

            $newString = '(' . implode(', ', array_map(function ($key) use ($data, $businessTypeTranslate) {
                $value = $data[$key] ?? '';
                if ($key === 'business_type' && isset($businessTypeTranslate[$value])) {
                    $value = $businessTypeTranslate[$value];
                }

                return $value;
            }, $changedFields)) . ')';

            $description = "Название: {$oldTitle}. ID: {$oldId}.\n"
                . "Старые:\n{$oldString}.\n"
                . "Новые:\n{$newString}.";

            MyLog::create([
                'type'       => 2,
                'action'     => 80,
                'author_id'  => $authorId,
                'description'=> $description,
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Данные партнёра успешно обновлены.',
        ]);
    }
}
