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


        $outSum = $request->query("OutSum");
//        UserPrice::updateOrCreate(['id' => 2,], ['month' => $outSum]);
        $invId = $request->query('InvId');
//        UserPrice::updateOrCreate(['id' => 3,], ['month' => $invId]);
        $signature = strtoupper($request->query("SignatureValue"));
//        UserPrice::updateOrCreate(['id' => 4,], ['month' => $signature]);

        $receipt = $request->input("Receipt");
        $paymentDate = $request->input("Shp_paymentDate");
        $userId = $request->input("Shp_userId");

        $mySignature = strtoupper(md5("$outSum:$invId:$password2:Shp_paymentDate=$paymentDate:Shp_userId=$userId"));
//        $mySignature = strtoupper(md5("$outSum:$invId:$password2"));
        UserPrice::updateOrCreate(['id' => 5,], ['month' => $mySignature]);

        // проверка корректности подписи
// check signature
        if ($signature != $mySignature) {
            echo "bad sign\n";
            exit();
        }

// success
        echo "OK$invId\n";
        UserPrice::updateOrCreate([
            'user_id' => $Shp_userId,
            'month' => $paymentDate],
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
            'payment_month' => $paymentDate,
            'summ' => $outSum,
            'payment_number' => $invId,
        ]);
    }
}













