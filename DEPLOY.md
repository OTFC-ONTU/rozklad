# Деплой «Розклад» у Plesk

Схема та сама, що в `OTFC-ONTU/otfk`:

```
push у main → GitHub Actions (.github/workflows/deploy.yml)
  1. tests: composer audit + php artisan test
  2. deploy-plesk: npm run build → коміт «дерево main + public/build» у гілку plesk-build
  3. POST на вебхук Plesk (секрет PLESK_DEPLOY_WEBHOOK)
→ Plesk Laravel Toolkit: git pull plesk-build → composer install → сценарій розгортання
```

На сервері немає Node, а SSH підписки — chroot із PHP 7.2, тому фронтенд **і `vendor/`**
збирає CI (обидва лежать у `plesk-build`), а команди artisan запускаються через повний шлях
до PHP 8.3. Тому підходить і звичайне «Git»-сховище Plesk без Laravel Toolkit: шлях сервера
`/rozklad.otfk.od.ua`, кореневий каталог документів домену — `/rozklad.otfk.od.ua/public`,
`.env` — файлом `/rozklad.otfk.od.ua/.env` через «Файли». **Не комітьте в `plesk-build` вручну.**

## Одноразове налаштування

0. **Спершу** зробіть push у `main`, щоб CI створив гілку `plesk-build` (без секрету
   вебхука крок «Trigger» просто пропуститься).
1. **Домен/піддомен** у Plesk → «Сертифікати SSL/TLS» → Let's Encrypt; «Хостинг та DNS» →
   перенаправлення HTTP → HTTPS.
2. **PHP**: 8.3, «FPM-застосунок обслуговується Apache» (потрібен `.htaccess`).
3. **Бази даних**: створити окрему БД + користувача.
4. **Laravel Toolkit** («Почніть роботу» → Laravel) → з Git.
   Репозиторій **приватний**, тому URL — SSH: `git@github.com:OTFC-ONTU/rozklad.git`.
   Plesk покаже публічний ключ розгортання → GitHub → репозиторій → Settings →
   Deploy keys → Add (лише читання). Кореневий каталог залиште кореневою текою домену —
   Toolkit сам перенесе docroot у `…/public`.
5. Після встановлення: «Git» → гілка **`plesk-build`** → «Розгорнути зараз».
6. **Змінні середовища** (.env): замінити повністю вмістом `.env.production.example`,
   заповнити `APP_URL`, `DB_*`. `APP_KEY` — у «Виконавці»: `key:generate`.
7. **Розгортання** → вимкнути крок `package.json` (збірка вже в гілці), сценарій:
   ```
   /opt/plesk/php/8.3/bin/php artisan migrate --seed --force
   /opt/plesk/php/8.3/bin/php artisan filament:assets
   /opt/plesk/php/8.3/bin/php artisan optimize:clear
   /opt/plesk/php/8.3/bin/php artisan config:cache
   /opt/plesk/php/8.3/bin/php artisan route:cache
   /opt/plesk/php/8.3/bin/php artisan view:cache
   ```
8. **Виконавець**: `storage:link`. Першого адміна створює `migrate --seed` із `ADMIN_EMAIL`/`ADMIN_PASSWORD` у `.env`
   (лише якщо такого email ще немає).
9. **Автоматичне розгортання**: «Розгортання» → режим **«Автоматичний»**, скопіювати URL
   вебхука (`https://…:8443/modules/git/public/web-hook.php?uuid=…`) → GitHub → Settings →
   Secrets and variables → Actions → New repository secret `PLESK_DEPLOY_WEBHOOK`.
   У ручному режимі вебхук лише тягне код, не розгортаючи.
10. (за потреби) «Заплановані завдання» у Toolkit — `schedule:run`.

Після цього кожен push у `main` викладається сам (~3 хв після вебхука).

## Перевірка

- `https://ДОМЕН/` — головна, `https://ДОМЕН/admin` — вхід.
- `https://ДОМЕН/build/commit.txt` = `git rev-parse origin/main`.
- 404 на кроці вебхука = репозиторій у Plesk перестворено → оновити `PLESK_DEPLOY_WEBHOOK`.
- Після зміни .env у Toolkit: «Виконавець» → `optimize:clear`, потім `config:cache`.
- 500 на `/` і `/admin`, але `/up` = 200 → зазвичай закешований конфіг без `.env`
  (розгортання пройшло до створення `.env`) або невірні `DB_*`. Виправити `.env` → «Розгорнути зараз».
