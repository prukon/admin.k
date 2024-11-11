<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;


class RobokassaController extends Controller
{
    //Расшифровка ссылки
    public function result(Request $request)
    {
        \Log::info('Request data:', $request->all());

        DB::transaction(function () use ($request, ) {

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

// success
            echo "OK$invId\n";
            UserPrice::updateOrCreate([
                'user_id' => $Shp_userId,
//            'month' => $Shp_paymentDate
                'new_month' => $Shp_paymentDate
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

            Log::create([
                'type' => 5, //лог платежей
                'action' => 50, // лог платежей
                'author_id' => $Shp_userId,
                'description' => "Платеж на сумму: " . intval($outSum) . " руб от $user->name. ID: {$Shp_userId}. Группа: {$teamName}. Период: {$Shp_paymentDate}.",
                'created_at' => now(),
            ]);
        });
    }
}
 












