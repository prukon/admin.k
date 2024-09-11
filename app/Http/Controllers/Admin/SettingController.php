<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;


class SettingController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function index()
    {


        $setting = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $setting ? $setting->text : null;

        return view("admin/setting", compact(
            "textForUsers"
        ));
    }
//    AJAX Активность регистрации
    public function registrationActivity(Request $request) {
        $isRegistrationActivity = $request->query('isRegistrationActivity');

        // Обновляем цены для групп
        if ($isRegistrationActivity) {
            $isRegistrationActivity = filter_var($isRegistrationActivity, FILTER_VALIDATE_BOOLEAN);
            // Обновляем или создаем запись в таблице team_prices
            Setting::updateOrCreate(
                [
                    'name' => "registrationActivity",
                ],
                [
                    'status' => $isRegistrationActivity
                ]
            );
        }
        return response()->json([
            'success' => true,
            'isRegistrationActivity' => $isRegistrationActivity,

        ]);
    }

//    AJAX Текст для юзеров
    public function textForUsers(Request $request) {
        $textForUsers = $request->query('textForUsers');
     if($textForUsers) {
         Setting::updateOrCreate(
             [
                 'name' => "textForUsers",
             ],
             [
                 'text' => $textForUsers
             ]
         );
     }

        return response()->json([
            'success' => true,
            'textForUsers' => $textForUsers,
        ]);
    }
}