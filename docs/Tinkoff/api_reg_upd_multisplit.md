# Регистрация и обновление партнёров (sm-register). Выжимка PDF 1.11

Источник: `docs/Tinkoff/api_reg_upd_multisplit.pdf`  
Документ Т-Банка «Регистрация и обновление партнеров», версия **1.11** от **16.06.2026**.

Старая выжимка в этом файле покрывала только §1.3 по версии 1.8. Ниже — все три метода, которые нужны CRM.

Боевой host: `https://acqapi.tinkoff.ru`  
Тестовый host: `https://acqapi-test.tinkoff.ru`  
Авторизация: OAuth `POST /oauth/token` (Basic `partner:partner` + login/password банка в теле). Дальше `Authorization: Bearer {access_token}`. Боевой контур — mTLS + IP whitelist (`acq_help@tbank.ru`).

---

## 1. Регистрация точки — `POST /sm-register/register`

Обязательные поля (таблица 1.3.1): `billingDescriptor`, `fullName`, `name` (кириллица + ОПФ), `inn`, `kpp` (если нет — `000000000`), `ogrn`, `addresses[]` (`type`, `zip`, `country` ISO Alpha-3, `city`, `street`; в адресе только `.` и `,`), `email`, `siteUrl`, `ceo` (`firstName`, `lastName`, `phone`, `country`), `bankAccount` (`account`, `bankName`, `bik`, `details`).

Необязательные, но есть в API:

- `bankAccount.korAccount` — корреспондентский счёт (обязательность менялась в 1.6/1.7; в 1.11 — **Нет**).
- `founders` — объект необязателен; если передан, `founders.individuals[]` обязателен.
- `mcc`, `shopArticleId`, `okved`, `phones`, `licenses`, `comment`, `nonResident` (всегда `false`).

Успех: `shopCode` (это PartnerId для e2c-выплаты), `code`, `terminals` (для мультирасчётов пустой).

Ошибки:

1. Бизнес: `{ status, message, path }` — одна строка, например «указаны неверные банковские реквизиты. БИК : …; р/с : …».
2. Валидация: `{ errors: [{ field, defaultMessage, rejectedValue, code }], message }`.

---

## 2. Получение точки — `GET /sm-register/register/shop/{shopCode}`

Таблица 1.4.2. Поля `status` / `REGISTERED` в ответе **нет**.

Важное для выплат:

- `bankAccount.disableReimbursement` (Boolean, **обязателен**) — «Возмещения заблокированы у торговой точки».
- `bankAccount.account`, `korAccount`, `bankName`, `bik`, `details`.
- `name`, `inn`, `kpp`, `email`.
- `merchantIds` / `terminalIds` / `userDefinedFees` — для мультирасчётов пустые или не нужны CRM.

Причины блокировки («неактуальные реквизиты») API **не отдаёт** — только boolean. Расшифровку пишет поддержка банка.

---

## 3. Обновление точки — `PATCH /sm-register/register/{shopCode}`

Таблица 2.2.1. PATCH принимает **только `bankAccount`**, не inn/адреса/CEO.

Если объект `bankAccount` передан, обязательны и не пусты:

- `account`
- `bankName`
- `bik`
- `details`

Необязательно: `korAccount`, `kbk`, `oktmo`, `disableReimbursement`.

`disableReimbursement`:

- `true` — заблокировать выплаты;
- `false` — разблокировать; ранее удержанные холды помечаются к выплате.

В CRM снятие блокировки — **только отдельной кнопкой**, не при каждом PATCH реквизитов.

Ошибки PATCH: те же `message` / `errors[]`. Примеры: «Точка не найдена», «Указаны неверные банковские реквизиты», КБК/ОКТМО.

---

## 4. Что из этого уже делает CRM и что нет

Сделано: `POST register` (включая `korAccount`, если заполнен), ручной PATCH только `bankAccount` (р/с+БИК+банк+назначение, опционально к/с), GET (кнопки «Обновить статус» / «Подтянуть из банка»), авто‑PATCH полного `bankAccount` перед выплатой, отдельная кнопка `disableReimbursement=false`.

Видимость (2026-09): GET сохраняет `disableReimbursement` и снимок реквизитов точки; e2c `ErrorCode`/`Message`/`Details` показываются на карточке выплаты и в timeline платежа.

Не сделано: смена inn/адреса/CEO через PATCH (банк этот метод для них не принимает).
