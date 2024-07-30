<?php


namespace App\Http\Filters;


use Illuminate\Database\Eloquent\Builder;

class TeamFilter extends AbstractFilter
{
    public const TITLE = 'title';

    protected function getCallbacks(): array
    {
        return [
            self::TITLE => [$this, 'title'],
        ];
    }

    public function title(Builder $builder, $value)
    {
        $builder->where('title', 'like', "%{$value}%");
    }

}