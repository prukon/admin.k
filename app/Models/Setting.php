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

        Log::info('Setting::getBool()', [
            'name'       => $name,
            'partner_id' => $partnerId,
            'found'      => (bool)$row,
            'status'     => $row->status ?? null,
            'row_id'     => $row->id ?? null,
        ]);

        if (!$row || $row->status === null) {
            Log::info('Setting::getBool() -> default used', ['default' => $default]);
            return $default;
        }

        $bool = (bool)(is_string($row->status) ? (int)$row->status : $row->status);
        Log::info('Setting::getBool() -> result', ['bool' => $bool]);

        return $bool;
    }

    public static function setBool(string $name, bool $value, $partnerId = null): bool
    {
        try {
            $ok = DB::table('settings')->updateOrInsert(
                ['name' => $name, 'partner_id' => $partnerId],
                ['status' => $value ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]
            );

            Log::info('Setting::setBool()', [
                'name'       => $name,
                'partner_id' => $partnerId,
                'value'      => $value,
                'ok'         => $ok,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Setting::setBool() FAILED', [
                'name'       => $name,
                'partner_id' => $partnerId,
                'value'      => $value,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

}
