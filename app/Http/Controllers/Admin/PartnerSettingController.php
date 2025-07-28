<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Servises\UserService;
use Carbon\Carbon;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

//use App\Models\Log;
use App\Models\MyLog;
//use Illuminate\Support\Facades\Log;


//Контроллер для админа

class PartnerSettingController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function user()
    {
        $allTeams = Team::All();
        $user = Auth::user();
        $partners = $user->partners;



        return view('admin.editCurUser',  ['activeTab' => 'user'], compact(
            'user',
            'partners',
            'allTeams'
        ));
    }

    public function partner()
    {
        $allTeams = Team::All();
        $user = Auth::user();

        $partners = $user->partners;



        return view('account.index',  ['activeTab' => 'partner'], compact(
            'user',
            'partners',
            'allTeams'
        ));
    }

    public function updatePartner(UpdateRequest $request, Partner $partner)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $authorName = User::where('id', $authorId)->value('name');

        // Сохраняем старые данные до обновления
        $oldData = $partner->toArray();



        // Данные, валидированные в Request
        $data = $request->validated();

        DB::transaction(function () use ($partner, $authorId, $authorName, $oldData, $data ) {

            // Определим, есть ли вообще изменения
            $changedFields = [];
            foreach ($data as $key => $newValue) {
                // Проверяем, было ли поле в старых данных и отличается ли оно
                $oldValue = $oldData[$key] ?? null;
                if ($oldValue != $newValue) {
                    $changedFields[] = $key;
                }
            }

        $oldTitle = $oldData['title'] ?? null;
        $oldId = $oldData['id'] ?? null;

            // Если изменений нет, просто выходим из транзакции без записи лога
            if (empty($changedFields)) {
                return;
            }

            // Обновление партнёра
            $partner->update($data);

            // Словарь переводов для поля business_type
            $businessTypeTranslate = [
                'company'                     => 'ООО',
                'individual_entrepreneur'     => 'ИП',
                'physical_person'             => 'Физ. лицо',
                'non_commercial_organization' => 'НКО',
            ];


            // Формируем строку старых значений (только изменяемых полей,
            // но по требованию можно выводить абсолютно все поля $data)
            $oldString = '(' . implode(', ', array_map(function ($key) use ($oldData) {
                    return $oldData[$key] ?? '';
                }, array_keys($data))) . ')';

            // Формируем строку новых значений
//            $newString = '(' . implode(', ', array_map(function ($key) use ($data) {
//                    return $data[$key] ?? '';
//                }, array_keys($data))) . ')';


            // Формируем строку новых значений
            $newString = '(' . implode(', ', array_map(function ($key) use ($data, $businessTypeTranslate) {
                    $value = $data[$key] ?? '';
                    if ($key === 'business_type' && isset($businessTypeTranslate[$value])) {
                        $value = $businessTypeTranslate[$value];
                    }
                    return $value;
                }, array_keys($data))) . ')';

            // Собираем строку для описания лога
            // Пример вывода:
            // Имя: Админ. ID: 1.
            // Старые:
            // (11.06.1989, admin@admin.ru).
            // Новые:
            // (11.06.1989, admin@admin.ru1).
            $description = "Название: {$oldTitle}. ID: {$oldId}.\n"
                . "Старые:\n{$oldString}.\n"
                . "Новые:\n{$newString}.";

            // Записываем лог
            MyLog::create([
                'type'       => 2,   // или ваш тип для обновления
                'action'     => 80,  // или ваш action для обновления партнера
                'author_id'  => $authorId,
                'description'=> $description,
                'created_at' => now(),
            ]);
        });
        // Редирект с сообщением об успешном обновлении
//        return redirect()->route('admin.cur.company.edit', $partner->id)
//            ->with('success', 'Данные партнёра успешно обновлены.');

        return response()->json([
            'success' => true,
            'message' => 'Данные партнёра успешно обновлены.',
        ]);

    }

}
