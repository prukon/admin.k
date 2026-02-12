<?php

namespace App\Servises;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MyLog;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Services\PartnerContext; // ✅ НОВОЕ

class TeamService
{
    /** @var PartnerContext */
    protected PartnerContext $partnerContext; // ✅ НОВОЕ

    // ✅ НОВОЕ: внедряем PartnerContext через DI
    public function __construct(PartnerContext $partnerContext)
    {
        $this->partnerContext = $partnerContext;
    }

    public function store($data)
    {
        // ✅ ЗАМЕНА app('current_partner') и хардкода partner_id=1
        $partnerId = $this->partnerContext->partnerId();

        if (!$partnerId) {
            // Здесь лучше упасть, чем создавать группу "в никуда"
            throw new \RuntimeException('Текущий партнёр не определён');
        }

        $data['order_by']  = $data['order_by'] ?? 10;
        $data['partner_id'] = $partnerId;

        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        $team = Team::create($data);

        if (!empty($weekdays) && $team->id) {
            $team->weekdays()->sync($weekdays);
        }

        return $team; // Возвращаем созданную команду
    }

    public function update($team, $data)
    {
        $weekdays = $data['weekdays'] ?? [];
        unset($data['weekdays']);

        // Обновляем данные команды, проверяем, что команда действительно сохранена
        if ($team->update($data) && $team->exists && $team->id) {
            $team->weekdays()->sync($weekdays);
        } else {
            throw new \Exception("Ошибка: команда не обновлена или team_id не существует.");
        }
    }

    public function delete($team)
    {
        $team->delete();
    }

    //    Создание группы
    public function storeWithLogging(array $data, int $authorId): Team
    {
        $team = DB::transaction(function () use ($data, $authorId) {
            // ✅ НОВОЕ: централизованный доступ к партнёру
            $partnerId = $this->partnerContext->partnerId();

            if (!$partnerId) {
                throw new \RuntimeException('Текущий партнёр не определён');
            }

            $team = $this->store($data);

            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            $weekdaysFormatted = [];
            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
                }, $data['weekdays']);
            }

            $description = sprintf(
                "Название: %s\nДни недели: %s\nСортировка: %s\nАктивность: %s",
                $team->title ?? '-',
                implode(', ', $weekdaysFormatted) ?: '-',
                $data['order_by'] ?? '-',
                !empty($data['is_enabled']) ? 'Да' : 'Нет'
            );

            MyLog::create([
                'type'         => 3,
                'action'       => 31,
                'author_id'    => $authorId,
                'partner_id'   => $partnerId,
                'target_type'  => \App\Models\Team::class, // ✅
                'target_id'    => $team->id,               // ✅
                'target_label' => $team->title,            // ✅
                'description'  => $description,
                'created_at'   => Carbon::now(),
            ]);

            return $team;
        });

        return $team;
    }
}