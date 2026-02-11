<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\UserField;
use App\Models\MyLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserFieldController extends Controller
{
    /**
     * Создание/обновление/удаление доп. полей пользователя для текущего партнёра.
     *
     * Ожидает payload вида:
     * {
     *   "fields": [
     *     {
     *       "id": 1|null,
     *       "name": "Рост",
     *       "field_type": "string|text|select",
     *       "roles": [1, 2, 3]
     *     },
     *     ...
     *   ]
     * }
     */
    public function storeFields(Request $request)
    {
        $data = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'nullable|integer|exists:user_fields,id',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.field_type' => 'required|in:string,text,select',
            'fields.*.roles' => 'nullable|array',
            'fields.*.roles.*' => 'integer|exists:roles,id',
        ]);

        $partnerId = app('current_partner')->id;

        // ХЕЛПЕР для генерации уникального slug
        $makeUniqueSlug = function (string $baseName, int $partnerId, ?int $ignoreId = null): string {
            $base = Str::slug($baseName . '-' . $partnerId);
            $slug = $base;
            $i = 1;

            while (
            UserField::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
            ) {
                $slug = $base . '-' . $i;
                $i++;
            }

            return $slug;
        };

        DB::transaction(function () use ($data, $partnerId, $makeUniqueSlug) {
            $submittedIds = collect($data['fields'])
                ->pluck('id')
                ->filter()
                ->all();

            // Удаляем поля, которых нет в запросе (для данного партнёра)
            $toDelete = UserField::where('partner_id', $partnerId)
                ->pluck('id')
                ->diff($submittedIds)
                ->all();

            if ($toDelete) {
                // Получаем удаляемые поля заранее (до удаления)
                $fieldsToDelete = UserField::whereIn('id', $toDelete)->get(['id', 'name']);

                // Удаляем поля
                UserField::whereIn('id', $toDelete)->delete();

                // Логируем каждое удалённое поле
                foreach ($fieldsToDelete as $field) {
                    // 🧾 УДАЛЕНИЕ ДОП. ПОЛЯ
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id' => $field->id,
                        'target_label' => $field->name,
                        'description' => "Удалено поле '{$field->name}' (ID: {$field->id})",
                        'created_at' => now(),
                    ]);
                }
            }

            // Обрабатываем новые и существующие поля
            foreach ($data['fields'] as $item) {
                $fieldId = $item['id'] ?? null;
                $name    = $item['name'];
                $type    = $item['field_type'];
                $roles   = $item['roles'] ?? [];

                // Генерируем уникальный slug
                $slug = $makeUniqueSlug($name, $partnerId, $fieldId);

                if ($fieldId) {
                    // === Обновление существующего поля ===
                    $field = UserField::where('partner_id', $partnerId)
                        ->findOrFail($fieldId);

                    $changes = [];

                    if ($field->name !== $name) {
                        $changes[] = "Название: '{$field->name}' → '{$name}'";
                    }
                    if ($field->field_type !== $type) {
                        $changes[] = "Тип: '{$field->field_type}' → '{$type}'";
                    }

                    // Обновляем основные поля, если есть изменения
                    if ($changes) {
                        $field->update([
                            'name'       => $name,
                            'slug'       => $slug,
                            'field_type' => $type,
                        ]);
                    }

                    // --- Сравниваем и логируем изменения ролей ---
                    $oldRoleIds = $field->roles()->pluck('roles.id')->all();
                    $field->roles()->sync($roles);

                    $allIds  = array_values(array_unique(array_merge($oldRoleIds, $roles)));
                    $nameMap = Role::whereIn('id', $allIds)->pluck('label', 'id')->toArray();

                    $oldNames = collect($oldRoleIds)
                        ->map(fn($id) => $nameMap[$id] ?? (string)$id)
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    $newNames = collect($roles)
                        ->map(fn($id) => $nameMap[$id] ?? (string)$id)
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    if ($oldNames !== $newNames) {
                        $changes[] = "Роли: [" . (implode(', ', $oldNames) ?: '-') . "] → [" . (implode(', ', $newNames) ?: '-') . "]";
                    }

                    $description = !empty($changes)
                        ? implode(";\n", $changes) . "\n"
                        : '';

                    // ИЗМЕНЕНИЯ ДОП. ПОЛЯ
                    MyLog::create([
                        'type'        => 2,
                        'action'      => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id'   => $field->id,
                        'target_label'=> $field->name,
                        'description' => $description,
                        'created_at'  => now(),
                    ]);
                } else {
                    // === Создание нового поля ===
                    $field = UserField::create([
                        'name'       => $name,
                        'slug'       => $slug,
                        'field_type' => $type,
                        'partner_id' => $partnerId,
                    ]);

                    $field->roles()->sync($roles);

                    $newNames = Role::whereIn('id', $roles)
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all();

                    // СОЗДАНИЕ ДОП. ПОЛЯ
                    MyLog::create([
                        'type'        => 2,
                        'action'      => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id'   => $field->id,
                        'target_label'=> $field->name,
                        'description' =>
                            "Создано поле '{$field->name}' (ID: {$field->id})\n" .
                            "Роли: [-] → [" . (implode(', ', $newNames) ?: '-') . "]",
                        'created_at'  => now(),
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Поля успешно сохранены']);
    }
}