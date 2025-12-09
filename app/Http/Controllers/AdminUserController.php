<?php
//
//namespace App\Http\Controllers;
//
//
//use App\Models\UserTableSetting;
//use Illuminate\Support\Facades\Auth;
//use Illuminate\Http\Request;
//
//
//use App\Models\UserTableSetting;
//
//
//class AdminUserController extends Controller
//{
//
//    public function getColumnsSettings()
//    {
//        $user = Auth::user();
//
//        $settings = UserTableSetting::firstOrCreate(
//            [
//                'user_id' => $user->id,
//                'table_key' => 'users_index',
//            ],
//            [
//                // дефолтное состояние — все включены
//                'columns' => [
//                    'avatar' => true,
//                    'name' => true,
//                    'teams' => true,
//                    'birthday' => true,
//                    'email' => true,
//                    'phone' => true,
//                    'status_label' => true,
//                    'actions' => true,
//                ],
//            ]
//        );
//
//        return response()->json($settings->columns);
//    }
//
//    public function saveColumnsSettings(Request $request)
//    {
//        $user = Auth::user();
//
//        $validated = $request->validate([
//            'columns' => 'required|array',
//        ]);
//
//        $settings = UserTableSetting::updateOrCreate(
//            [
//                'user_id' => $user->id,
//                'table_key' => 'users_index',
//            ],
//            [
//                'columns' => $validated['columns'],
//            ]
//        );
//
//        return response()->json([
//            'success' => true,
//        ]);
//    }
//}
