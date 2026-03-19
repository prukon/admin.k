# Экстренный фикс на сервере (test + prod)

## Что сделано в коде

- В проект возвращён `app/helpers.php` (функции `roubles`, `pretty_json`).
- В `composer.json` снова подключён автозагруз этого файла.
- Из шаблонов T‑Bank убраны объявления этих функций в `@php` (чтобы не было «Cannot redeclare»).

После деплоя этих изменений на сервер нужно выполнить шаги ниже.

---

## 1. Деплой и пересборка autoload

**Тест (test.kidscrm.online):**
```bash
cd /home/prukon/web/test.kidscrm.online/public_html
# Залей сюда обновлённые файлы: app/helpers.php, composer.json, оба blade в resources/views/tinkoff/
composer dump-autoload --no-scripts
```

**Прод (kidscrm.online), если там тот же код и та же ошибка с helpers.php:**
```bash
cd /home/prukon/web/kidscrm.online/public_html
# Залей те же app/helpers.php, composer.json, blade
composer dump-autoload --no-scripts
```

`--no-scripts` чтобы не запускать post-install (package:discover и т.д.), пока MySQL может быть недоступен.

---

## 2. MySQL (Connection refused)

Ошибка «Connection refused» — к БД не подключается. Проверь и при необходимости запусти MySQL:

```bash
sudo systemctl status mysql
# или
sudo systemctl status mariadb
```

Если сервис не active:
```bash
sudo systemctl start mysql
# или
sudo systemctl start mariadb
```

Проверка, что порт слушается:
```bash
ss -tlnp | grep 3306
```

---

## 3. Порядок действий

1. Залить на сервер: `app/helpers.php`, `composer.json`, `resources/views/tinkoff/payments/show.blade.php`, `resources/views/tinkoff/payouts/show.blade.php`.
2. В каталоге каждого сайта (test и при необходимости prod) выполнить: `composer dump-autoload --no-scripts`.
3. Запустить MySQL, если он был остановлен.
4. Проверить сайты в браузере.
