<?php

// app/Http/Controllers/CalendarController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event; // Модель Event для получения данных из базы

class CalendarController extends Controller
{
    public function index()
    {
        // Получаем все события из базы данных
        $events = Event::all();

        // Форматируем события для FullCalendar
        $calendarEvents = [];

        foreach ($events as $event) {
            $calendarEvents[] = [
                'title' => $event->title,
                'start' => $event->start_date,
                'end' => $event->end_date,
            ];
        }

        // Передаем события в представление
        return view('calendar', ['events' => $calendarEvents]);
    }
}
