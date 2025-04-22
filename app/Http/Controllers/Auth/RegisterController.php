<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;

class RegisterController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */

//    protected $redirectTo = RouteServiceProvider::HOME;

    protected function redirectTo()
    {
        return '/'; // Перенаправление на главную страницу
    }

    /**
     * Create a new controller instance.
     *
     *
     * @return void
     */

    public function __construct()
    {

        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'team_id' => ['string'],
            'partner_id'=> ['required', 'integer', 'exists:partners,id'],

        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {

        $defaultRoleId = Role::where('name', 'user')->value('id') ?? 2;



        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
//            'team_id' => $data['team_id'],
            'partner_id' => $data['partner_id'],
            'role_id'    => $defaultRoleId,

        ]);
    }


    public function showRegistrationForm()
    {
        // 1) Все команды (если вы их ещё используете в форме)
        $allTeams = Team::all();

        // 2) Список партнёров с флагом активности регистрации
        $partners = Partner::all()->map(function($p) {
            $p->isRegistrationActive = Setting::where('name', 'registrationActivity')
                    ->where('partner_id', $p->id)
                    ->value('status') ?? false;
            return $p;
        });

        // 3) Какой партнёр был выбран при предыдущей попытке (old) или пусто
        $selectedPartnerId = old('partner_id', '');

        // 4) Флаг активности для выбранного партнёра (null, если не выбран)
        $isRegistrationActivity = null;
        if ($selectedPartnerId) {
            $isRegistrationActivity = Setting::where('name', 'registrationActivity')
                    ->where('partner_id', $selectedPartnerId)
                    ->value('status') ?? false;
        }

        // 5) Собираем конфиг для передачи в форму
        $registrationConfig = [
            'partner_id'             => $selectedPartnerId,
            'isRegistrationActivity' => $isRegistrationActivity,
        ];

        // 6) Отдаём всё в шаблон
        return view('auth.register', compact(
            'allTeams',
            'partners',
            'registrationConfig'
        ));
    }
}
