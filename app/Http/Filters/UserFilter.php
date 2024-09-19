<?php


namespace App\Http\Filters;


use Illuminate\Database\Eloquent\Builder;

class UserFilter extends AbstractFilter
{
    public const NAME = 'name';
    public const TEAM_ID = 'team_id';


    protected function getCallbacks(): array
    {
        return [
            self::NAME => [$this, 'name'],
            self::TEAM_ID => [$this, 'teamId'],
        ];
    }

    public function name(Builder $builder, $value)
    {
        $builder->where('name', 'like', "%{$value}%");
    }


//    public function teamId(Builder $builder, $value)
//    {
//        $builder->where('team_id', $value);
//    }

    public function teamId(Builder $builder, $value)
    {
        if ($value === 'none') {
            // Если передан 'none', ищем пользователей с team_id = null
            $builder->whereNull('team_id');
        } else {
            // Иначе фильтруем по конкретному значению team_id
            $builder->where('team_id', $value);
        }
    }


}