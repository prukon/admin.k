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
use App\Mail\NewPartnerLeadSubmission;
use App\Models\PartnerLead;
use Yajra\DataTables\Facades\DataTables;
use App\Enums\PartnerLeadStatus;
// use Throwable;                               // <---- ДОБАВЛЕНО

use Illuminate\Support\Facades\Http;


class LandingPageController extends Controller
{
    public function index()
    {
        return view('landing.index');
    }

    public function partnerLeadsIndex()
    {
        $partnerLeads = PartnerLead::latest()->paginate(20);

        return view('admin.partner-leads', compact('partnerLeads'));
    }

    public function partnerLeadsDataTable(Request $request)
    {
        $baseQuery = PartnerLead::query()->whereNull('deleted_at');

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
                        ? PartnerLeadStatus::label($item->status->value)
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
            $partnerLead = PartnerLead::create($data);

            $this->notifyTelegram($partnerLead);

            Mail::to('prukon@gmail.com')->send(new NewPartnerLeadSubmission($partnerLead));

            return response()->json([
                'message' => 'Заявка отправлена!',
                'id'      => $partnerLead->id,
            ], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'На сервере произошла ошибка. Попробуйте позже.',
            ], 500);
        }
    }

    public function updatePartnerLead(Request $request, PartnerLead $partnerLead)
    {
        $validator = Validator::make($request->all(), [
            'status' => [
                'nullable',
                'string',
                'in:' . implode(',', PartnerLeadStatus::values()),
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
            $partnerLead->status = $data['status']
                ? PartnerLeadStatus::from($data['status'])
                : null;
        }

        if (array_key_exists('comment', $data)) {
            $partnerLead->comment = $data['comment'];
        }

        $partnerLead->save();

        return response()->json([
            'message'      => 'Изменения сохранены.',
            'status'       => $partnerLead->status?->value,
            'status_label' => $partnerLead->status
                ? PartnerLeadStatus::label($partnerLead->status->value)
                : null,
            'comment'      => $partnerLead->comment,
        ]);
    }

    public function destroyPartnerLead(PartnerLead $partnerLead)
    {
        $partnerLead->delete();

        return response()->json([
            'message' => 'Заявка удалена.',
        ]);
    }

    protected function notifyTelegram(PartnerLead $partnerLead): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $lines = [
            "📩 Новая заявка с сайта",
            "",
            "👤 Имя: {$partnerLead->name}",
            "📞 Телефон: {$partnerLead->phone}",
        ];

        if ($partnerLead->email) {
            $lines[] = "✉ Email: {$partnerLead->email}";
        }
        if ($partnerLead->website) {
            $lines[] = "🌐 Сайт: {$partnerLead->website}";
        }
        if ($partnerLead->message) {
            $lines[] = "";
            $lines[] = "💬 Сообщение:";
            $lines[] = mb_substr($partnerLead->message, 0, 1000);
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
