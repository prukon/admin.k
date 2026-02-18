# План исправлений: мультирасчёты Т-Банк

По результатам ревью реализации и документации (vyplaty-multisplit, api_reg_upd_multisplit, oplata_multisplit, tbank.html).

---

## 1. Права и сидер

- **1.1** Право `manage_payment_method_T-Bank` преднамеренно **не выдаётся** обычным админам — его имеет только суперадмин. В `AdminRoleBasePermissionsSeeder` не добавлять и не раскомментировать это право. План прав не менять.

---

## 1a. Критичные дыры безопасности (исправить в первую очередь)

### 1a.1 Подмена partner_id в QR-init (СБП)

- **Проблема:** `POST /payments/tinkoff/qr-init` принимает `partner_id` из тела запроса (в т.ч. из hidden-поля формы) и в `TinkoffQrController::init()` использует его напрямую. SetPartner фиксирует текущего партнёра по пользователю, но не запрещает отправить другой `partner_id`. Итог: пользователь с правом `payment-method-T-Bank` может инициировать платёж/QR **на чужого партнёра** (чужими ключами).
- **Исправление:**
  - В `TinkoffQrController::init()` брать `partnerId` **только** из `app('current_partner')->id`, не из запроса.
  - Убрать `partner_id` из правил валидации в `QrInitRequest` и из формы (hidden-поле не отправлять).
  - В представлении, которое рендерит форму QR-init, не передавать и не выводить `partner_id` в форму.

### 1a.2 Перебор paymentId на QR endpoints (enumeration)

- **Проблема:** `GET /tinkoff/qr/{paymentId}/json` и `GET /tinkoff/qr/{paymentId}/state` (и отображение страницы `show`) ищут платёж только по `tinkoff_payment_id` и **не проверяют**, что платёж относится к текущему партнёру/пользователю. При переборе/угадывании `paymentId` можно получать статусы и QR **чужих** платежей (в рамках того же права доступа).
- **Исправление:**
  - После получения записи `TinkoffPayment` по `paymentId` проверять: `$tp->partner_id === (int) app('current_partner')->id`.
  - При несовпадении возвращать 404 (или 403), не отдавать данные и не вызывать API банка от имени чужого партнёра.
  - Применить проверку во всех трёх экшенах: `show`, `getQr`, `state`.

---

## 2. Дефолт комиссии платформы 2%

- **2.1** В `TinkoffPayoutsService::calcNetAmountForPayout()` (строка 247) при отсутствии правила из БД используется fallback:
  - сейчас: `$rule->platform_percent ?? $rule->percent ?? 0.00` — последний дефолт **0%**.
  - требование: приоритет — данные из БД; если в правиле нет `platform_percent`/`percent` — использовать **2%**.
- **2.2** Заменить дефолт с `0.00` на `2.00` в этом месте. В `breakdownForPayment()` (строка 39) уже стоит `2.00` — оставить как есть для единообразия.

---

## 3. Учредители (founders) и руководитель (ceo) в sm-register

- **3.1** По документации Т-Банка (`api_reg_upd_multisplit.md`):
  - **ceo** (руководитель) — обязателен: firstName, lastName, phone, country.
  - **founders.individuals** — массив учредителей обязателен: firstName, lastName, citizenship, address.
- **3.2** Для **ИП**: учредитель по сути один — сам ИП. Рекомендация: передавать данные руководителя (гендира) и в `ceo`, и в `founders.individuals` как единственного учредителя (один элемент массива с теми же ФИО + citizenship, address).
- **3.3** Сейчас на странице партнёра есть данные гендира; в `SmRegisterRequest` и в контроллере они не все уходят в sm-register (например, отдельные поля для ceo/founders). Нужно:
  - в форме/реквесте иметь поля руководителя (имя, фамилия, телефон, страна) и при регистрации передавать их в объект `ceo`;
  - для ИП формировать `founders.individuals` из данных руководителя (плюс citizenship и address из формы или дефолты по документации);
  - для ООО/других типов — когда появится форма учредителей, подставлять их в `founders.individuals`; до этого можно оставить один элемент из ceo или явно описать в коде/документации ограничение.

---

## 4. Статус выплаты после проведения

- **4.1** В документации Т-Банка по мультирасчётам **отдельного webhook по статусу выплаты нет**. Статус выплаты получают через **GetState** (опрос API).
- **4.2** В коде уже есть:
  - `TinkoffPayoutsService::runPayout()` — обновляет `payout->status` из ответа банка после вызова Payment;
  - `TinkoffPayoutsService::pollState()` — обновляет статус по GetState;
  - `TinkoffPollPayoutStatesJob` — джоба для опроса выплат в статусах, отличных от COMPLETED/REJECTED.
- **4.3** Исправления:
  - Убедиться, что **TinkoffPollPayoutStatesJob** запускается по расписанию в `app/Console/Kernel.php` (или через scheduler) с достаточной частотой (например, каждые 5–15 минут).
  - После ручного запуска выплаты (`payNow`) при необходимости один раз вызвать `pollState` для немедленного обновления статуса в БД (или оставить обновление только по крону, но тогда на карточке выплаты показывать пояснение «статус обновится по расписанию»).
  - На странице карточки выплаты/партнёра при отображении статуса учитывать, что актуальный статус — в БД после опроса; при открытии карточки можно опционально один раз дернуть `pollState` для свежего статуса (без перегрузки банка).

---

## 5. FormRequest’ы и перенос валидации

- **5.1** Уже есть: `SmRegisterRequest`, `SmPatchRequest`, `PayoutDelayRequest` (Tinkoff).
- **5.2** **TinkoffCommissionController**: валидация в `store()` и `update()` через `$r->validate()`. Вынести в два FormRequest’а, например:
  - `StoreTinkoffCommissionRuleRequest` (rules для create),
  - `UpdateTinkoffCommissionRuleRequest` (rules для update),
  и подключать их в `store()` и `update()`.
- **5.3** **TinkoffPartnerAdminController** (если ещё используется): там своя валидация в `smRegister` и `smPatch`. По возможности использовать те же `SmRegisterRequest`/`SmPatchRequest`, что и в `TinkoffAdminPartnerController`, чтобы не дублировать правила.
- **5.4** Остальные экшены Tinkoff (payment create, QR init, debug) при наличии входящих параметров — проверить и при необходимости вынести валидацию в FormRequest’ы.

---

## 6. Безопасность и проверка доступа по deal/partner

- **6.1** Роуты выплат и сделок под middleware `can:manage-payment-method-T-Bank`, но не проверяют принадлежность `deal`/`payment` партнёру, с которым работает пользователь.
- **6.2** Сейчас любой пользователь с правом `manage_payment_method_T-Bank` может вызвать:
  - `POST /tinkoff/payouts/{deal}/pay-now`,
  - `POST /tinkoff/payouts/{deal}/delay`,
  - `POST /tinkoff/deals/{deal}/close`,
  подставив произвольный `deal_id`. По `deal_id` находится `TinkoffPayment` и выполняется действие.
- **6.3** Добавить проверку доступа:
  - после получения `$payment = TinkoffPayment::where('deal_id', $deal)->firstOrFail()` проверить, что `$payment->partner_id` разрешён текущему пользователю (например, совпадает с `session('current_partner')` или входит в список партнёров, доступных пользователю/роли). Если в системе админ всегда работает в контексте одного выбранного партнёра — достаточно сравнения с `session('current_partner')`.
  - Аналогично в `TinkoffDealController::close` и при отображении карточек выплат/платежей: не показывать и не выполнять действия по чужим партнёрам (если политика доступа это запрещает).
- **6.4** Webhook `/webhooks/tinkoff/payments`: проверка подписи уже есть в `TinkoffPaymentsService::handleWebhook()`. Убедиться, что роут webhook не защищён middleware, требующим авторизацию пользователя (вызов идёт от банка).

---

## 7. Дубликаты и лишний код

- **7.1** Дубликат роута `Route::get('/admin/tinkoff/payments/{id}')` уже убран (один раз в группе `manage-payment-method-T-Bank`).
- **7.2** Отдельного сервиса `TinkoffSmRegisterService` в проекте нет, используется `SmRegisterClient`. Удалять нечего. Если где-то остались упоминания несуществующего `TinkoffSmRegisterService` — удалить ссылки.

---

## 8. Прочее по документации

- **8.1** Назначение платежа (`details`): по документации оно обязательно для выплат. Перед автовыплатой уже делается PATCH в sm-register с `details` через `TinkoffDetailsHelper::makeDetailsForPeriod($payment)` — проверить, что шаблон и данные соответствуют требованиям банка.
- **8.2** Закрытие сделки (CloseSpDeal): вызывается из `TinkoffDealController::close` ключами партнёра — соответствует документации.
- **8.3** Комиссии: расчёт net по правилам из `tinkoff_commission_rules` с дефолтами (в т.ч. 2% платформа после исправления п.2) — оставить единым местом в `TinkoffPayoutsService` (breakdown + calcNetAmountForPayout).

---

## Порядок внедрения (рекомендуемый)

1. **П.1a** — критичные дыры: QR-init (partner_id только из current_partner) и проверка принадлежности платежа на QR endpoints.  
2. П.2 — дефолт 2% в `calcNetAmountForPayout`.  
3. П.6 — проверка доступа по partner_id в Payout/Deal контроллерах (для суперадмина с manage_payment_method_T-Bank).  
4. П.4 — расписание для `TinkoffPollPayoutStatesJob` и при необходимости разовый poll при открытии карточки выплаты.  
5. П.3 — передача ceo и founders в sm-register (форма/реквест + логика для ИП).  
6. П.5 — FormRequest’ы для комиссий и унификация валидации sm-register/sm-patch.  
7. П.7–8 — проверка дубликатов/ссылок и соответствия документации.
