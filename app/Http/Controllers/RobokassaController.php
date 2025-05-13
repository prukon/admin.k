<?php

namespace App\Http\Controllers;

//use App\Models\Log;
use App\Models\MyLog;
use App\Models\Payment;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;


class RobokassaController extends Controller
{
    //Расшифровка ссылки
    public function result2(Request $request)
    {
        \Log::info('Request data:', $request->all());

        DB::transaction(function () use ($request) {

            // Проверка подписи и обработка данных от Robokassa
            $password2 = config('robokassa.password2');
            $Shp_paymentDate = strtoupper($request->query("Shp_paymentDate"));
            $Shp_userId = strtoupper($request->query("Shp_userId"));
            $signature = strtoupper($request->query("SignatureValue"));
            $outSum = $request->query("OutSum");
            $invId = $request->query('InvId');

            $mySignature = strtoupper(md5("$outSum:$invId:$password2:Shp_paymentDate=$Shp_paymentDate:Shp_userId=$Shp_userId"));
//        UserPrice::updateOrCreate(['id' => 5,], ['month' => $mySignature]);

            // проверка корректности подписи
            if ($signature != $mySignature) {
                echo "bad sign\n";
                exit();
            }


        // Проверка $Shp_paymentDate на формат даты
        $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $Shp_paymentDate) && strtotime($Shp_paymentDate);
        $newMonthValue = $isValidDate ? $Shp_paymentDate : null;



// success
            echo "OK$invId\n";
            UserPrice::updateOrCreate([
                'user_id' => $Shp_userId,
                'new_month' => $newMonthValue
            ],
                [
                    'is_paid' => 1
                ]);
            $user = User::where('id', $Shp_userId)->first();
            $teamName = Team::where('id', $user->team_id)->first()->title;
            $currentDateTime = date('Y-m-d H:i:s');


//        преобразовать $Shp_paymentDate в строковый фотмат "Сентябрь 2024"

            Payment::create([
                'user_id' => $Shp_userId,
                'user_name' => $user->name,
                'team_title' => $teamName,
                'operation_date' => $currentDateTime,
                'payment_month' => $Shp_paymentDate,
                'summ' => $outSum,
                'payment_number' => $invId,
            ]);

        MyLog::create([
                'type' => 5, //лог платежей
                'action' => 50, // лог платежей
                'author_id' => $Shp_userId,
                'description' => "Платеж на сумму: " . intval($outSum) . " руб от $user->name. ID: {$Shp_userId}. Группа: {$teamName}. Период: {$Shp_paymentDate}.",
                'created_at' => now(),
            ]);
        });
    }

    public function result(Request $request)
    {
//        $partnerId = app('current_partner')->id;
        $partnerId =1;

        \Log::info('Request data:', $request->all());

        DB::transaction(function () use ($request, $partnerId) {
            // Получаем настройки Робокассы из БД
            $paymentSystem = PaymentSystem::where('name', 'robokassa')->first();

            if (!$paymentSystem || !$paymentSystem->is_connected) {
                \Log::error('Попытка обработки платежа, но Робокасса не подключена или не настроена');
                echo "bad config\n";
                exit();
            }

            $settings = $paymentSystem->settings;
            $password2 = $settings['password2'] ?? null;

            if (!$password2) {
                \Log::error('Ошибка: отсутствует password2 для Robokassa');
                echo "bad config\n";
                exit();
            }

            // Получаем параметры из запроса
            $Shp_paymentDate = strtoupper($request->query("Shp_paymentDate"));
            $Shp_userId = strtoupper($request->query("Shp_userId"));
            $signature = strtoupper($request->query("SignatureValue"));
            $outSum = $request->query("OutSum");
            $invId = $request->query('InvId');

            // Генерация подписи
            $mySignature = strtoupper(md5("$outSum:$invId:$password2:Shp_paymentDate=$Shp_paymentDate:Shp_userId=$Shp_userId"));

            // Проверка подписи
            if ($signature !== $mySignature) {
                \Log::warning("Подпись не совпадает: получена $signature, ожидается $mySignature");
                echo "bad sign\n";
                exit();
            }

            // Проверка формата даты
            $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $Shp_paymentDate) && strtotime($Shp_paymentDate);
            $newMonthValue = $isValidDate ? $Shp_paymentDate : null;

            // Создание/обновление статуса оплаты
            UserPrice::updateOrCreate(
                [
                    'user_id' => $Shp_userId,
                    'new_month' => $newMonthValue
                ],
                [
                    'is_paid' => 1
                ]
            );

            // Получение пользователя и команды
            $user = User::find($Shp_userId);
            $teamName = $user?->team?->title ?? 'Без команды';
        $currentDateTime = now()->format('Y-m-d H:i:s');

        // Запись платежа
        Payment::create([
            'user_id' => $Shp_userId,
            'partner_id'  => $partnerId,
            'user_name' => $user?->name ?? 'Неизвестно',
            'team_title' => $teamName,
            'operation_date' => $currentDateTime,
            'payment_month' => $Shp_paymentDate,
            'summ' => $outSum,
            'payment_number' => $invId,
        ]);

        // Логирование
        MyLog::create([
            'type' => 5,
            'action' => 50,
            'author_id' => $Shp_userId,
            'partner_id'  => $partnerId,
            'description' => "Платеж на сумму: " . intval($outSum) . " руб от {$user?->name}. ID: {$Shp_userId}. Группа: {$teamName}. Период: {$Shp_paymentDate}.",
            'created_at' => now(),
        ]);

        // Ответ Robokassa
        echo "OK$invId\n";
    });
    }

}
 












