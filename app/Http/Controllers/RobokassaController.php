<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class RobokassaController extends Controller
{

    public function result(Request $request)
    {
        \Log::info('Request data:', $request->all());
//      \Log::info('Generated signature:', ['signature' => $signature, 'received' => $request->input('SignatureValue')]);

        // Проверка подписи и обработка данных от Robokassa
        $password2 = config('robokassa.password2');
        $Shp_paymentDate = strtoupper($request->query("Shp_paymentDate"));
        $Shp_userId = strtoupper($request->query("Shp_userId"));
        $signature = strtoupper($request->query("SignatureValue"));
        $outSum = $request->query("OutSum");
        $invId = $request->query('InvId');

        $mySignature = strtoupper(md5("$outSum:$invId:$password2:Shp_paymentDate=$Shp_paymentDate:Shp_userId=$Shp_userId"));
        UserPrice::updateOrCreate(['id' => 5,], ['month' => $mySignature]);

        // проверка корректности подписи
        if ($signature != $mySignature) {
            echo "bad sign\n";
            exit();
        }

// success
        echo "OK$invId\n";
        UserPrice::updateOrCreate([
            'user_id' => $Shp_userId,
            'month' => $Shp_paymentDate],
            [
                'is_paid' => 1
            ]);
        $user =  User::where('id', $Shp_userId)->first();
        $teamName = Team::where('id', $user->team_id)->first()->title;
        $currentDateTime = date('Y-m-d H:i:s');

        Payment::create([
            'user_id' => $Shp_userId,
            'user_name' => $user->name,
            'team_title' => $teamName,
            'operation_date' => $currentDateTime,
            'payment_month' => $Shp_paymentDate,
            'summ' => $outSum,
            'payment_number' => $invId,
        ]);
    }
}













