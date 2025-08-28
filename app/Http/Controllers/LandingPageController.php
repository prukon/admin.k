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
use Illuminate\Support\Facades\Validator;   // <---- вот это добавь


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



    public function contactSend2(Request $request)
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
    public function contactSend3(Request $request)
    {
        // 1) Нормализуем website: разрешаем "голые" домены
        if ($request->filled('website') && !preg_match('/^https?:\/\//i', $request->website)) {
            $request->merge(['website' => 'https://' . trim($request->website)]);
        }

        // 2) Валидация через Validator, чтобы вернуть единый JSON
        $rules = [
            'name'            => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => ['required','string','max:50','regex:/^[0-9\s\-\+\(\)]+$/'],
            'website'         => ['nullable','url','max:255'],
            'message'         => ['nullable','string','max:5000','not_regex:/https?:\/\/|www\./i'],
            'website_hp'      => 'nullable|size:0',
            'form_started_at' => 'required|date',
        ];

        $messages = [
            'website.url'     => 'Укажите корректный URL (например, https://example.com).',
            'website_hp.size' => 'Похоже, вы бот 🤖.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Доп. проверка: минимум 6 цифр в телефоне
        $validator->after(function($v) use ($request) {
            if ($request->filled('phone')) {
                $digits = preg_replace('/\D+/', '', $request->phone);
                if (strlen($digits) < 6) {
                    $v->errors()->add('phone', 'Укажите корректный телефон (минимум 6 цифр).');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'message'=> 'Исправьте ошибки и отправьте снова.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 3) Анти-бот по времени заполнения
        $minFillSeconds = 3;
        $startedAt = Carbon::parse($request->input('form_started_at'));
        if (now()->diffInSeconds($startedAt) < $minFillSeconds) {
            return response()->json([
                'ok'     => false,
                'message'=> 'Слишком быстрое отправление формы. Попробуйте ещё раз.',
                'errors' => ['name' => ['Слишком быстрое отправление формы. Попробуйте ещё раз.']],
            ], 422);
        }

        // 4) Тестовая ошибка (для проверки фронта): передай ?test_error=1 или поле test_error=1
        if ($request->boolean('test_error')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Тестовая ошибка сервера (инициирована параметром test_error).',
            ], 400);
        }

        // 5) Основная логика с защитой от 500-ок
        try {
            $data = $request->only(['name','email','phone','website','message']);
            $submission = ContactSubmission::create($data);

            // если не нужна синхронная отправка — лучше queue()
            Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));

            return response()->json([
                'ok'      => true,
                'message' => 'Заявка отправлена!',
                'id'      => $submission->id,
            ], 200);

        } catch (Throwable $e) {
            // Логуем, но пользователю — аккуратный ответ
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
                // В разработке можно добавить диагностическое поле:
                // 'debug' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
            ], 500);
        }
    }
    public function contactSend4(Request $request)
    {
        // Нормализуем website: добавляем https:// если нужно
        if ($request->filled('website') && !preg_match('/^https?:\/\//i', $request->website)) {
            $request->merge(['website' => 'https://' . trim($request->website)]);
        }

        // Валидация
        $rules = [
            'name'            => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => ['required','string','max:50','regex:/^[0-9\s\-\+\(\)]+$/'],
            'website'         => ['nullable','url','max:255'],
            'message'         => ['nullable','string','max:5000','not_regex:/https?:\/\/|www\./i'],
            'website_hp'      => 'nullable|size:0',
            'form_started_at' => 'required|date',
        ];

        $messages = [
            'website.url'     => 'Укажите корректный URL (например, https://example.com).',
            'website_hp.size' => 'Похоже, вы бот 🤖.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Доп. проверка телефона (хотя regex уже отсекает "13")
        $validator->after(function($v) use ($request) {
            if ($request->filled('phone')) {
                $digits = preg_replace('/\D+/', '', $request->phone);
                if (strlen($digits) < 6) {
                    $v->errors()->add('phone', 'Укажите корректный телефон (минимум 6 цифр).');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => $validator->errors()->first(), // <-- одна строка с первой ошибкой
                'errors'  => $validator->errors(),          // <-- полный набор ошибок (если нужно)
            ], 422);
        }

        // Анти-бот проверка
        $minFillSeconds = 3;
        $startedAt = Carbon::parse($request->input('form_started_at'));
        if (now()->diffInSeconds($startedAt) < $minFillSeconds) {
            return response()->json([
                'ok'      => false,
                'message' => 'Слишком быстрое отправление формы. Попробуйте ещё раз.',
            ], 422);
        }

        // Тестовая ошибка (по параметру)
        if ($request->boolean('test_error')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Тестовая ошибка сервера.',
            ], 400);
        }

        try {
            $data = $request->only(['name','email','phone','website','message']);
            $submission = ContactSubmission::create($data);

            Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));

            return response()->json([
                'ok'      => true,
                'message' => 'Заявка отправлена!',
                'id'      => $submission->id,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok'      => false,
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }
    public function contactSend(Request $request)
    {
        // Нормализуем website: разрешаем голые домены
        if ($request->filled('website') && !preg_match('/^https?:\/\//i', $request->website)) {
            $request->merge(['website' => 'https://' . trim($request->website)]);
        }

        // Валидация (только сервер)
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => ['required','string','max:50','regex:/^[0-9\s\-\+\(\)]+$/'],
            'website' => ['nullable','url','max:255'],
//            'message' => ['nullable','string','max:5000'],

            'message' => [
                'nullable',
                'string',
                'max:5000',
                'not_regex:/https?:\/\/|www\./i', // 🚫 запрещаем ссылки
            ],

        ],[
            'name.required'   => 'Укажите ваше имя.',
            'email.email'     => 'Укажите корректный email.',
            'phone.required'  => 'Укажите телефон.',
            'phone.regex'     => 'Телефон может содержать только цифры, +, -, пробелы и скобки.',
            'website.url'     => 'Укажите корректный URL (например, https://example.com).',
            'message.max'     => 'Сообщение слишком длинное.',
        ]);

        // Доп. проверка: минимум 6 цифр в телефоне
        $validator->after(function($v) use ($request) {
            if ($request->filled('phone')) {
                $digits = preg_replace('/\D+/', '', $request->phone);
                if (strlen($digits) < 6) {
                    $v->errors()->add('phone', 'Укажите корректный телефон (минимум 6 цифр).');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(), // короткое общее
                'errors'  => $validator->errors(),          // по полям
            ], 422);
        }

        try {
            $data = $request->only(['name','email','phone','website','message']);
            $submission = ContactSubmission::create($data);

            // Можно заменить на ->queue() при наличии очереди
            Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));

            return response()->json([
                'message' => 'Заявка отправлена!',
                'id'      => $submission->id,
            ], 200);

        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }



}
