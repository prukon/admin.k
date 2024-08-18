<?php
// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use MaddHatter\LaravelFullcalendar\Facades\Calendar;

class EventController extends Controller
{
    public function index()
    {
        $events = [];

        $data = Event::all();

        if($data->count()) {
            foreach ($data as $key => $value) {
                $events[] = Calendar::event(
                    $value->title,
                    false,
                    new \DateTime($value->start_date),
                    new \DateTime($value->end_date ?: $value->start_date)
                );
            }
        }

        $calendar = Calendar::addEvents($events);

        return view('calendar.index', compact('calendar'));
    }


    public function getEvents()
    {
        $events = Event::select('title', 'start_date as start', 'end_date as end')->get();
        return response()->json($events);
    }
}














