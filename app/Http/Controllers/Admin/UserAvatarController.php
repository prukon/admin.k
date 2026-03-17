<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MyLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class UserAvatarController extends Controller
{
    /**
     * Удаление аватарки пользователя в разделе "Пользователи".
     *
     * URL и сигнатуру метода сохраняем такими же, как были в UserController:
     * public function destroyUserAvatar($id)
     */
    public function destroyUserAvatar($id)
    {
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

            // Удаляем файлы, если есть
            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            // Чистим поля
            $user->update([
                'image'      => null,
                'image_crop' => null,
            ]);

            MyLog::create([
                'type'        => 2, // Лог для обновления юзеров
                'action'      => 299, // Лог для обновления учетной записи
                'target_type' => \App\Models\User::class,
                'user_id'     => $user->id,
                'target_id'   => $user->id,
                'target_label'=> $targetLabel,
                'description' => "Пользователю {$targetLabel} удален аватар.",
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Аватар удалён',
        ]);
    }

    /**
     * Загрузка/обновление аватарки пользователя в разделе "Пользователи".
     *
     * URL и сигнатуру метода сохраняем такими же, как были в UserController:
     * public function uploadUserAvatar(Request $request, $id)
     */
    public function uploadUserAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Безопасная валидация: только реальные изображения (по MIME),
        // без SVG/HTML/GIF, с лимитами размера
        $request->validate([
            'image_big'  => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],  // 5MB
            'image_crop' => ['required', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp'], // 4MB
        ]);

        $bigFile  = $request->file('image_big');
        $cropFile = $request->file('image_crop');

        // Перекодируем в JPEG и ограничим размеры, чтобы не хранить "опасные" форматы и огромные картинки
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

            // удаляем старые файлы
            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            // сохраняем новые (только перекодированные байты)
            Storage::disk('public')->put('avatars/' . $bigName, $bigBytes);
            Storage::disk('public')->put('avatars/' . $cropName, $cropBytes);

            // обновляем БД
            $user->update([
                'image'      => $bigName,
                'image_crop' => $cropName,
            ]);

            MyLog::create([
                'type'        => 2, // Лог для обновления юзеров
                'action'      => 27, // Лог для обновления учетной записи
                'user_id'     => $user->id,
                'target_type' => \App\Models\User::class,
                'target_id'   => $user->id,
                'target_label'=> $targetLabel,
                'description' => "Пользователю {$targetLabel} изменён аватар.",
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'success'        => true,
            'message'        => 'Аватар обновлён',
            'image_url'      => asset('storage/avatars/' . $bigName),
            'image_crop_url' => asset('storage/avatars/' . $cropName),
        ]);
    }
}