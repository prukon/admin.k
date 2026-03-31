<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ConvertRussianMonths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:months';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert Russian month names to dates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!Schema::hasColumn('users_prices', 'month')) {
            $this->info('Column users_prices.month does not exist. Nothing to convert.');
            return 0;
        }

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

        // Получаем все записи из таблицы
        $usersPrices = DB::table('users_prices')
            ->whereNotNull('month')
            ->get();

        foreach ($usersPrices as $price) {
            foreach ($months as $ru => $en) {
                if (strpos($price->month, $ru) !== false) {
                    // Заменяем русский месяц на английский
                    $newMonth = str_replace($ru, $en, $price->month);

                    // Конвертируем в формат даты через Carbon
                    $formattedDate = Carbon::parse($newMonth)->format('Y-m-d');

                    // Обновляем запись в базе данных
                    DB::table('users_prices')
                        ->where('id', $price->id)
                        ->update(['new_month' => $formattedDate]);

                    // Выводим информацию в консоль о преобразованной записи
                    $this->info('Updated record ID ' . $price->id . ' to new month ' . $formattedDate);
                    break;
                }
            }
        }

        $this->info('All Russian months have been converted!');
        return 0;
    }
}
