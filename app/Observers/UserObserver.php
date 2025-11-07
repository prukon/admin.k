<?php
//
//namespace App\Observers;
//
//use App\Models\User;
//use App\Models\Team;
//use App\Models\Role;
//use App\Models\MyLog;
//use Illuminate\Support\Facades\Auth;
//use Illuminate\Support\Facades\DB;
//use Illuminate\Support\Facades\Schema;
//use Carbon\Carbon;
//
//class UserObserver
//{
//    /** Пишем лог после успешного COMMIT */
//    public bool $afterCommit = true;
//
//    /** Игнорируемые поля */
//    private array $ignore = [
//        'updated_at',
//        'remember_token',
//        'password',
//        'two_factor_secret',
//        'two_factor_recovery_codes',
//    ];
//
//    /** Подписи базовых полей */
//    private array $labels = [
//        'name'              => 'Имя',
//        'lastname'          => 'Фамилия',
//        'email'             => 'Email',
//        'phone'             => 'Телефон',
//        'birthday'          => 'Дата рождения',
//        'start_date'        => 'Дата начала занятий',
//        'is_enabled'        => 'Активность',
//        'team_id'           => 'Группа',
//        'role_id'           => 'Роль',
//        'image'             => 'Аватар (ориг.)',
//        'image_crop'        => 'Аватар (кроп)',
//        'phone_verified_at' => 'Подтверждение телефона',
//    ];
//
//    /**
//     * Сохраняем снапшоты ДО изменения
//     */
//    public function updating(User $user): void
//    {
//        // Базовые атрибуты
//        $user->__orig_attrs = $user->getOriginal();
//
//        // Кастом-поля
//        $user->__orig_custom = $this->fetchCustomMap($user->id);
//    }
//
//    /**
//     * Пишем лог ПОСЛЕ изменения
//     */
//    public function updated(User $user): void
//    {
//        $lines = [];
//
//        // --- 1) Базовые поля (diff без wasChanged) ---
//        $orig   = is_array($user->__orig_attrs ?? null) ? $user->__orig_attrs : [];
//        $actual = $user->getAttributes();
//
//        foreach ($this->labels as $key => $title) {
//            if (in_array($key, $this->ignore, true)) continue;
//
//            $old = $orig[$key]   ?? null;
//            $new = $actual[$key] ?? null;
//
//            if ($this->rawEqual($old, $new)) continue;
//
//            if ($key === 'team_id') {
//                $lines[] = $this->line('Группа', $this->teamTitle($old), $this->teamTitle($new));
//                continue;
//            }
//
//            if ($key === 'role_id') {
//                $lines[] = $this->line('Роль', $this->roleLabel($old), $this->roleLabel($new));
//                continue;
//            }
//
//            if ($key === 'is_enabled') {
//                $lines[] = $this->line($title, $this->yesNo($old), $this->yesNo($new));
//                continue;
//            }
//
//            if (in_array($key, ['birthday','start_date','phone_verified_at'], true)) {
//                $lines[] = $this->line($title, $this->prettyDate($old), $this->prettyDate($new));
//                continue;
//            }
//
//            if ($key === 'phone') {
//                // Пишем номер полностью
//                $lines[] = $this->line($title, $old ?? 'null', $new ?? 'null');
//                continue;
//            }
//
//            $lines[] = $this->line($title, $this->scalar($old), $this->scalar($new));
//        }
//
//        // --- 2) Кастом-поля ---
//        $before = is_array($user->__orig_custom ?? null) ? $user->__orig_custom : [];
//        $after  = $this->fetchCustomMap($user->id);
//
//        $slugs = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
//        $nameBySlug = $this->fetchFieldNamesBySlug($slugs); // ['slug' => 'Name']
//
//        foreach ($slugs as $slug) {
//            $old = $before[$slug] ?? '';
//            $new = $after[$slug]  ?? '';
//            if ($this->textEqual($old, $new)) continue;
//
//            $label = $nameBySlug[$slug] ?? $slug;
//            $lines[] = $this->line($label, $this->scalar($old), $this->scalar($new));
//        }
//
//        if (!$lines) return;
//
//        // --- 3) Запись в MyLog ---
//        $partner   = app()->bound('current_partner') ? app('current_partner') : null;
//        $partnerId = $partner?->id;
//        $authorId  = Auth::id();
//
//        $targetLabel = trim(($user->lastname ?? '').' '.($user->name ?? '')) ?: ('ID '.$user->id);
//
//        $payload = [
//            'type'         => 2,
//            'action'       => 210,
//            'partner_id'   => $partnerId,
//            'author_id'    => $authorId,
//            'target_type'  => User::class,
//            'target_id'    => $user->id,
//            'target_label' => $targetLabel,
//            'description'  => implode("\n", $lines), // каждая правка — с новой строки
//            'created_at'   => now(),
//        ];
//
//        // ✅ Добавляем ключ ТОЛЬКО если колонка существует
//        if (Schema::hasColumn('my_logs', 'action_name_ru')) {
//            $payload['action_name_ru'] = 'Изменение данных пользователя';
//        }
//
//        MyLog::create($payload);
//    }
//
//    /* ===================== HELPERS ===================== */
//
//    private function rawEqual($a, $b): bool
//    {
//        // Пустые/NULL считаем равными
//        if (($a === null || $a === '') && ($b === null || $b === '')) return true;
//        return (string)$a === (string)$b;
//    }
//
//    private function textEqual($a, $b): bool
//    {
//        return $this->rawEqual($a, $b);
//    }
//
//    private function line(string $label, $old, $new): string
//    {
//        return sprintf('%s: "%s" → "%s"', (string)$label, (string)$old, (string)$new);
//    }
//
//    private function scalar($v): string
//    {
//        if ($v === null || $v === '') return 'null';
//        if (is_bool($v)) return $this->yesNo($v);
//        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
//        return (string)$v;
//    }
//
//    private function yesNo($v): string
//    {
//        return (bool)$v ? 'Да' : 'Нет';
//    }
//
//    private function prettyDate($v): string
//    {
//        if (!$v) return '—';
//        try { return Carbon::parse($v)->format('d.m.Y H:i'); }
//        catch (\Throwable) { return (string)$v; }
//    }
//
//    private function teamTitle($id): string
//    {
//        if (!$id) return 'Без группы';
//        $team = Team::withTrashed()->find($id);
//        return $team?->title ?: '—';
//    }
//
//    private function roleLabel($id): string
//    {
//        if (!$id) return '—';
//        $role = Role::find($id);
//        return $role?->label ?: '—';
//    }
//
//    /**
//     * ['slug' => 'value'] для кастом-полей пользователя
//     */
//    private function fetchCustomMap(int $userId): array
//    {
//        if (
//            !Schema::hasTable('user_field_values') ||
//            !Schema::hasTable('user_fields') ||
//            !Schema::hasColumn('user_field_values','field_id') ||
//            !Schema::hasColumn('user_field_values','user_id') ||
//            !Schema::hasColumn('user_field_values','value') ||
//            !Schema::hasColumn('user_fields','id') ||
//            !Schema::hasColumn('user_fields','slug')
//        ) {
//            return [];
//        }
//
//        $rows = DB::table('user_field_values')
//            ->join('user_fields','user_fields.id','=','user_field_values.field_id')
//            ->select(['user_fields.slug','user_field_values.value'])
//            ->where('user_field_values.user_id',$userId)
//            ->get();
//
//        $out = [];
//        foreach ($rows as $r) {
//            $out[(string)$r->slug] = ($r->value === null) ? '' : (string)$r->value;
//        }
//        return $out;
//    }
//
//    /**
//     * Карта названий кастом-полей: ['slug' => user_fields.name]
//     */
//    private function fetchFieldNamesBySlug(array $slugs): array
//    {
//        if (!$slugs) return [];
//
//        if (
//            !Schema::hasTable('user_fields') ||
//            !Schema::hasColumn('user_fields','slug') ||
//            !Schema::hasColumn('user_fields','name')
//        ) {
//            return array_combine($slugs, $slugs);
//        }
//
//        $rows = DB::table('user_fields')
//            ->select(['slug','name'])
//            ->whereIn('slug',$slugs)
//            ->get();
//
//        $map = [];
//        foreach ($rows as $r) {
//            $slug = (string)$r->slug;
//            $map[$slug] = $r->name ?: $slug;
//        }
//        // фолбэк для отсутствующих
//        foreach ($slugs as $s) {
//            if (!array_key_exists($s, $map)) $map[$s] = $s;
//        }
//        return $map;
//    }
//}
