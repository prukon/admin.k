<?php

namespace App\Models;

use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    use HasFactory;
    use Filterable;

    protected $table = 'settings'; //явное указание к какой таблице в БД привязана модель


    protected $guarded = []; //разрешение на изменение данных в таблице

    public static function getBool(string $name, bool $default = false, $partnerId = null): bool
    {
        $q = static::query()->where('name', $name);
        $partnerId === null ? $q->whereNull('partner_id') : $q->where('partner_id', $partnerId);

        $row = $q->first(['id','name','status','partner_id']);

        if (!$row || $row->status === null) {
            return $default;
        }

        $bool = (bool)(is_string($row->status) ? (int)$row->status : $row->status);

        return $bool;
    }

    public static function setBool(string $name, bool $value, $partnerId = null): bool
    {
        try {
            $ok = DB::table('settings')->updateOrInsert(
                ['name' => $name, 'partner_id' => $partnerId],
                ['status' => $value ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Setting::setBool() FAILED', [
                'name'       => $name,
                'partner_id' => $partnerId,
                'value'      => $value,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить целочисленную настройку (колонка text). Глобальная при partner_id = null.
     */
    public static function getInt(string $name, int $default = 0, $partnerId = null): int
    {
        $q = static::query()->where('name', $name);
        $partnerId === null ? $q->whereNull('partner_id') : $q->where('partner_id', $partnerId);

        $row = $q->first(['id', 'name', 'text']);

        if (!$row || $row->text === null || $row->text === '') {
            return $default;
        }

        return (int) $row->text;
    }

    /**
     * Записать целочисленную настройку (колонка text). Глобальная при partner_id = null.
     */
    public static function setInt(string $name, int $value, $partnerId = null): bool
    {
        try {
            $s = static::firstOrCreate(
                ['name' => $name, 'partner_id' => $partnerId],
                ['text' => (string) $value]
            );
            if ($s->text !== (string) $value) {
                $s->text = (string) $value;
                $s->updated_at = now();
                $s->save();
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('Setting::setInt() FAILED', [
                'name'       => $name,
                'partner_id' => $partnerId,
                'value'      => $value,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /** Задержка автовыплаты после CONFIRMED (часы). 0 = сразу. БД с fallback на config. */
    public static function getTinkoffPayoutAutoDelayHours(): int
    {
        return static::getInt(
            'tinkoff_payout_auto_delay_hours',
            (int) config('tinkoff.payouts.auto_payout_delay_hours', 48),
            null
        );
    }

    public static function setTinkoffPayoutAutoDelayHours(int $hours): bool
    {
        return static::setInt('tinkoff_payout_auto_delay_hours', $hours, null);
    }

    /** Интервал запуска джобы отложенных выплат (минуты). БД с fallback на config. */
    public static function getTinkoffPayoutScheduledIntervalMinutes(): int
    {
        return static::getInt(
            'tinkoff_payout_scheduled_interval_minutes',
            (int) config('tinkoff.payouts.scheduled_interval_minutes', 10),
            null
        );
    }

    public static function setTinkoffPayoutScheduledIntervalMinutes(int $minutes): bool
    {
        return static::setInt('tinkoff_payout_scheduled_interval_minutes', $minutes, null);
    }
}
