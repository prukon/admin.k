<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\DemoRequest;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;


use App\Mail\NewContactSubmission;

use App\Models\ContactSubmission;


class LandingPageController extends Controller
{
    public function index()
    {
        return view('landing.index');
    }

    public function submission()
    {
        $submissions = ContactSubmission::latest()->paginate(20);
        return view('admin.leads', compact('submissions'));

    }



    public function contactSend(Request $request)
    {
        // Минимальное время "на заполнение" формы (анти-бот), сек.
        $minFillSeconds = 3;

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
//            'phone'           => 'required|string|max:50',
            'phone' => ['required','string','max:50','regex:/^[0-9\s\-\+\(\)]+$/'],
//            'website'         => ['nullable','url','max:255'], // поле "сайт"
            'website' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(https?:\/\/)?([\w.-]+\.[a-z]{2,})(\/.*)?$/i'
            ],


//            'message'         => 'nullable|string',
            'message' => ['nullable','string','max:5000','not_regex:/https?:\/\/|www\./i'],
            // honeypot: должно быть пустым
            'website_hp'      => 'nullable|size:0',
            // timestamp из формы
            'form_started_at' => 'required|date',
        ],[
            'website.url'     => 'Укажите корректный URL (например, https://example.com).',
            'website_hp.size' => 'Похоже, вы бот 🤖.',
        ]);

        // Анти-бот: проверяем, что форма заполнялась не мгновенно
        $startedAt = Carbon::parse($validated['form_started_at']);
        if (now()->diffInSeconds($startedAt) < $minFillSeconds) {
            return back()
                ->withErrors(['name' => 'Слишком быстрое отправление формы. Попробуйте ещё раз.'])
                ->withInput();
        }

        // Очистка: оставляем только нужные поля
        $data = collect($validated)->only(['name','email','phone','website','message'])->toArray();

        $submission = ContactSubmission::create($data);

        Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));

        if ($request->ajax()) {
            return response()->json(['message' => 'Заявка отправлена!']);
        }

        return back()->with('success', 'Сообщение отправлено!');
    }



}
