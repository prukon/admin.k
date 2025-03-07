<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConvertTeamPricesMonths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:team_prices_months';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert Russian month names to dates for team_prices table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Массив для замены русских месяцев на английские
        $months = [
            'Январь' => 'January',
            'Февраль' => 'February',
            'Март' => 'March',
            'Апрель' => 'April',
            'Май' => 'May',
            'Июнь' => 'June',
            'Июль' => 'July',
            'Август' => 'August',
            'Сентябрь' => 'September',
            'Октябрь' => 'October',
            'Ноябрь' => 'November',
            'Декабрь' => 'December',
        ];

        // Получаем все записи из таблицы team_prices
        $teamPrices = DB::table('team_prices')->get();

        foreach ($teamPrices as $price) {
            foreach ($months as $ru => $en) {
                if (strpos($price->month, $ru) !== false) {
                    // Заменяем русский месяц на английский
                    $newMonth = str_replace($ru, $en, $price->month);

                    // Конвертируем в формат даты через Carbon
                    $formattedDate = Carbon::parse($newMonth)->format('Y-m-d');

                    // Обновляем запись в базе данных
                    DB::table('team_prices')
                        ->where('id', $price->id)
                        ->update(['new_month' => $formattedDate]);

                    // Выводим информацию в консоль о преобразованной записи
                    $this->info('Updated record ID ' . $price->id . ' to new month ' . $formattedDate);
                    break;
                }
            }
        }

        $this->info('All Russian months have been converted for team_prices table!');
        return 0;
    }
}

