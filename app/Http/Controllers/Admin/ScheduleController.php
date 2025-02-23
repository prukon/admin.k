<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ScheduleUser;

// Модель для таблицы schedule_users

class ScheduleController extends Controller
{
    /**
     * Отображение страницы с расписанием.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Получаем год и месяц из запроса (либо текущие по умолчанию)
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));

        // Определяем, сколько дней в выбранном месяце
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        // Получаем всех пользователей (при необходимости, фильтруем/сортируем)
        $users = User::where('is_enabled', 1)->orderBy('name')->get();

        // Загружаем все записи расписания за выбранный месяц
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = Carbon::create($year, $month, $daysInMonth)->endOfDay();

        $scheduleData = ScheduleUser::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->groupBy(function ($item) {
                // Ключ будет вида: user_id . '_' . date(формат YYYY-MM-DD)
                return $item->user_id . '_' . $item->date->format('Y-m-d');
            });

        return view('schedule.index', compact('year', 'month', 'daysInMonth', 'users', 'scheduleData'));
    }

    /**
     * Обработка обновления расписания (AJAX или обычным POST).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        // Ожидаем получить:
        // user_id, date, status (N, Z, R), is_paid, description
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:N,Z,R',
            'is_paid' => 'required|boolean',
            'description' => 'nullable|string'
        ]);

        // Ищем или создаём запись
        $schedule = ScheduleUser::firstOrNew([
            'user_id' => $request->input('user_id'),
            'date' => $request->input('date'),
        ]);

        $schedule->status = $request->input('status');
        $schedule->is_paid = $request->input('is_paid');
        $schedule->description = $request->input('description', '');
        $schedule->save();

        // Возвращаем ответ (JSON для Ajax)
        return response()->json([
            'message' => 'Запись успешно обновлена',
            'data' => $schedule,
        ]);
    }
}
