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
// Для DataTables и enum статусов
use Yajra\DataTables\Facades\DataTables; // Для laravel-datatables, если Yajra DT подключён (иначе вручную)
use App\Enums\ContactSubmissionStatus;
// use Throwable;                               // <---- ДОБАВЛЕНО

use Illuminate\Support\Facades\Http;


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

    public function leadsDataTable3(Request $request)
    {
        // Базовый запрос: только не удалённые
        $baseQuery = ContactSubmission::query()->whereNull('deleted_at');

        // Общее количество записей БЕЗ учёта фильтров/поиска
        $recordsTotal = $baseQuery->count();

        // Клон для фильтрации
        $query = clone $baseQuery;

        // ---- ФИЛЬТР ПО СТАТУСУ ----
        // прилетает из JS как d.status = $('#statusFilter').val()
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // ---- Поиск DataTables ----
        if ($request->has('search') && $request->input('search.value')) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('website', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('comment', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhereRaw('DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") like ?', ["%$search%"]);
            });
        }

        // Количество записей ПОСЛЕ фильтрации (фильтр + поиск)
        $recordsFiltered = $query->count();

        // ---- Сортировка ----
        $order   = $request->input('order', []);
        $columns = $request->input('columns', []);

        if ($order && $columns) {
            foreach ($order as $ord) {
                $columnIdx  = $ord['column'];
                $columnName = $columns[$columnIdx]['data'];
                $dir        = $ord['dir'] === 'asc' ? 'asc' : 'desc';

                if (in_array($columnName, [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'website',
                    'message',
                    'status',
                    'comment',
                    'created_at',
                ])) {
                    $query->orderBy($columnName, $dir);
                }
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ---- Пагинация ----
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);

        $data = $query->skip($start)->take($length)->get();

        // ---- Формирование ответа ----
        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'name'         => $item->name,
                    'phone'        => $item->phone,
                    'email'        => $item->email,
                    'website'      => $item->website,
                    'message'      => $item->message,
                    'status'       => $item->status?->value ?? null, // 'new' / 'processing' / ...
                    'status_label' => $item->status
                        ? ContactSubmissionStatus::label($item->status->value)
                        : null, // 'Новый', 'Обработка' и т.д.
                    'comment'      => $item->comment,
                    'created_at'   => $item->created_at?->format('d.m.Y H:i') ?? null,
                ];
            }),
        ]);
    }






    public function leadsDataTable(Request $request)
    {
        // Базовый запрос: только не удалённые
        $baseQuery = ContactSubmission::query()->whereNull('deleted_at');

        // Общее количество записей БЕЗ учёта фильтров/поиска
        $recordsTotal = $baseQuery->count();

        // Клон для фильтрации
        $query = clone $baseQuery;

        // ---- ФИЛЬТР ПО НЕСКОЛЬКИМ СТАТУСАМ ----
        // из JS прилетает statuses: ['new', 'processing', ...]
        if ($request->has('statuses')) {
            $statuses = (array) $request->input('statuses', []);
            $statuses = array_filter($statuses); // убираем пустые

            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        // ---- Поиск DataTables ----
        if ($request->has('search') && $request->input('search.value')) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('website', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('comment', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhereRaw('DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") like ?', ["%$search%"]);
            });
        }


        // Количество записей ПОСЛЕ фильтрации (фильтр + поиск)
        $recordsFiltered = $query->count();

        // ---- Сортировка ----
        $order   = $request->input('order', []);
        $columns = $request->input('columns', []);

        if ($order && $columns) {
            foreach ($order as $ord) {
                $columnIdx  = $ord['column'];
                $columnName = $columns[$columnIdx]['data'];
                $dir        = $ord['dir'] === 'asc' ? 'asc' : 'desc';

                if (in_array($columnName, [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'website',
                    'message',
                    'status',
                    'comment',
                    'created_at',
                ])) {
                    $query->orderBy($columnName, $dir);
                }
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ---- Пагинация ----
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);

        $data = $query->skip($start)->take($length)->get();

        // ---- Формирование ответа ----
        return response()->json([
            'draw'            => (int) $request->input('draw'),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'name'         => $item->name,
                    'phone'        => $item->phone,
                    'email'        => $item->email,
                    'website'      => $item->website,
                    'message'      => $item->message,
                    'status'       => $item->status?->value ?? null, // 'new', 'processing', ...
                    'status_label' => $item->status
                        ? ContactSubmissionStatus::label($item->status->value)
                        : null,
                    'comment'      => $item->comment,
                    'created_at'   => $item->created_at?->format('d.m.Y H:i') ?? null,
                ];
            }),
        ]);
    }







    public function contactSend(Request $request)
    {
        // Нормализуем website: разрешаем голые домены
        if ($request->filled('website') && !preg_match('/^https?:\/\//i', $request->website)) {
            $request->merge(['website' => 'https://' . trim($request->website)]);
        }

        // Проверка reCAPTCHA v3
        $recaptchaToken = $request->input('recaptcha_token');

        if (!$recaptchaToken) {
            return response()->json([
                'message' => 'Не пройдена защита от спама. Обновите страницу и попробуйте ещё раз.',
            ], 422);
        }

        try {
            $response = Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret'   => config('services.recaptcha.secret'),
                    'response' => $recaptchaToken,
                    'remoteip' => $request->ip(),
                ]
            );

            $result = $response->json();

            // option: проверяем action, если на фронте указали 'contact'
            $minScore = (float) config('services.recaptcha.min_score', 0.5);

            if (
                empty($result['success']) ||
                ($result['score'] ?? 0) < $minScore
                // || ($result['action'] ?? null) !== 'contact'   // если используешь action
            ) {
                return response()->json([
                    'message' => 'Проверка на спам не пройдена.',
                ], 422);
            }
        } catch (\Throwable $e) {
            report($e);
            // на случай падения гугла можно либо заблокировать, либо наоборот пропустить:
            return response()->json([
                'message' => 'Ошибка проверки защиты от спама. Попробуйте позже.',
            ], 500);
        }



        // Валидация (только сервер)
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => ['required', 'string', 'max:50', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'message' => [
                'nullable',
                'string',
                'max:5000',
                'not_regex:/https?:\/\/|www\./i', //  запрещаем ссылки
            ],

        ], [
            'name.required'   => 'Укажите ваше имя.',
            'email.email'     => 'Укажите корректный email.',
            'phone.required'  => 'Укажите телефон.',
            'phone.regex'     => 'Телефон может содержать только цифры, +, -, пробелы и скобки.',
            'website.url'     => 'Укажите корректный URL (например, https://example.com).',
            'message.max'     => 'Сообщение слишком длинное.',
        ]);

        // Доп. проверка: минимум 6 цифр в телефоне
        $validator->after(function ($v) use ($request) {
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
            $data = $request->only(['name', 'email', 'phone', 'website', 'message']);
            $submission = ContactSubmission::create($data);

            // Телега
            $this->notifyTelegram($submission);


            // Можно заменить на ->queue() при наличии очереди
            Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));
            return response()->json([
                'message' => 'Заявка отправлена!',
                'id'      => $submission->id,
            ], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }

    // ОБНОВЛЕНИЕ статуса и комментария лида (AJAX).
    public function updateLead(Request $request, ContactSubmission $submission)
    {
        $validator = Validator::make($request->all(), [
            'status' => [
                'nullable',
                'string',
                'in:' . implode(',', ContactSubmissionStatus::values()),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ], [
            'status.in'      => 'Недопустимый статус.',
            'comment.max'    => 'Комментарий слишком длинный.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('status', $data)) {
            $submission->status = $data['status']
                ? ContactSubmissionStatus::from($data['status'])
                : null;
        }

        if (array_key_exists('comment', $data)) {
            $submission->comment = $data['comment'];
        }

        $submission->save();

        return response()->json([
            'message'      => 'Изменения сохранены.',
            'status'       => $submission->status?->value,
            'status_label' => $submission->status
                ? ContactSubmissionStatus::label($submission->status->value)
                : null,
            'comment'      => $submission->comment,
        ]);
    }

    // БЕЗОПАСНОЕ УДАЛЕНИЕ (soft delete) лида (AJAX).
    public function destroyLead(ContactSubmission $submission)
    {
        $submission->delete();

        return response()->json([
            'message' => 'Заявка удалена.',
        ]);
    }

    protected function notifyTelegram(ContactSubmission $submission): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $lines = [
            "📩 Новая заявка с сайта",
            "",
            "👤 Имя: {$submission->name}",
            "📞 Телефон: {$submission->phone}",
        ];

        if ($submission->email) {
            $lines[] = "✉ Email: {$submission->email}";
        }
        if ($submission->website) {
            $lines[] = "🌐 Сайт: {$submission->website}";
        }
        if ($submission->message) {
            $lines[] = "";
            $lines[] = "💬 Сообщение:";
            $lines[] = mb_substr($submission->message, 0, 1000);
        }

        $text = implode("\n", $lines);

        try {
            Http::asForm()->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text'    => $text,
                    'parse_mode' => 'HTML', // можно убрать, если не нужно
                ]
            );
        } catch (\Throwable $e) {
            report($e); // чтобы падение телеги не ломало заявку
        }
    }
}
