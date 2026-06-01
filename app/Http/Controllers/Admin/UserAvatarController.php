<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class UserAvatarController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Удаление аватарки пользователя в разделе "Пользователи".
     */
    public function destroyUserAvatar($id)
    {
        $user = $this->userForCurrentPartner((int) $id);

        DB::transaction(function () use ($user) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            $user->update([
                'image'      => null,
                'image_crop' => null,
            ]);

            $this->auditLogger->record(
                AuditEvent::UserAvatarDeletedByAdmin,
                AuditContext::make("Пользователю {$targetLabel} удален аватар.")
                    ->withUser($user)
                    ->withTarget($user, $targetLabel)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Аватар удалён',
        ]);
    }

    /**
     * Загрузка/обновление аватарки пользователя в разделе "Пользователи".
     */
    public function uploadUserAvatar(Request $request, $id)
    {
        $user = $this->userForCurrentPartner((int) $id);

        $request->validate([
            'image_big'  => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],
            'image_crop' => ['required', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);

        $bigFile  = $request->file('image_big');
        $cropFile = $request->file('image_crop');

        try {
            $manager   = ImageManager::gd();
            $bigImage  = $manager->read($bigFile->getRealPath())->scaleDown(1600, 1600);
            $cropImage = $manager->read($cropFile->getRealPath())->coverDown(300, 300);

            $bigBytes  = (string) $bigImage->toJpeg(85);
            $cropBytes = (string) $cropImage->toJpeg(90);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обработать изображение.',
            ], 422);
        }

        $bigName  = Str::uuid()->toString() . '.jpg';
        $cropName = Str::uuid()->toString() . '.jpg';

        DB::transaction(function () use ($user, $bigName, $cropName, $bigBytes, $cropBytes) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            Storage::disk('public')->put('avatars/' . $bigName, $bigBytes);
            Storage::disk('public')->put('avatars/' . $cropName, $cropBytes);

            $user->update([
                'image'      => $bigName,
                'image_crop' => $cropName,
            ]);

            $this->auditLogger->record(
                AuditEvent::UserAvatarUpdatedByAdmin,
                AuditContext::make("Пользователю {$targetLabel} изменён аватар.")
                    ->withUser($user)
                    ->withTarget($user, $targetLabel)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'success'        => true,
            'message'        => 'Аватар обновлён',
            'image_url'      => asset('storage/avatars/' . $bigName),
            'image_crop_url' => asset('storage/avatars/' . $cropName),
        ]);
    }

    private function userForCurrentPartner(int $userId): User
    {
        /** @var User $user */
        $user = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->whereKey($userId)
            ->firstOrFail();

        return $user;
    }
}
