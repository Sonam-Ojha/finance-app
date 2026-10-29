# Expense Tracker — website and API

The server side of Expense Tracker, an Indian personal-expense tracker (amounts in ₹ by default, with other currencies).
It is two things in one Laravel 11 app, on one database:

- **The website** — [finance.risodev.com](https://finance.risodev.com). Signed-in users get **My account**: dashboard, transactions, analytics, categories and budgets, recurring entries, savings goals, groups (shared expenses), exports, notifications and settings — the same data and features as the phone app. Users with the `admin` role also get **Admin**: users, activity, notifications (send now or on a schedule) and settings.
- **The JSON API `/api/v1`** — used by the Expo phone app ([expensec-tracking-app](https://github.com/Rishidubey0/expensec-tracking-app)). Sign-in is a Sanctum bearer token. The endpoints are listed in [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) and in `routes/api.php`.

The site began as "Smart Paisa", with its own tables (`expenses`, `incomes`, `bank_accounts`, `credit_cards`, `loans`, …). Those pages and their first API (`/api/auth/*`, `/api/income`, …) are retired: the old pages redirect (301) to the matching new page, and the old API answers `410 Gone` with `code: api_retired`. Their tables and rows are kept; their expenses and incomes were copied into the app's tables by the migration `2026_10_28_000001_copy_website_entries_into_app_tables`.

## Where things are

| Path | What |
|------|------|
| `app/Http/Controllers/Tracker/` | The website's My account pages |
| `app/Http/Controllers/Api/V1/` | The app's API |
| `app/Http/Controllers/Admin/` | Admin pages |
| `app/Http/Controllers/Auth/` | Sign-in, sign-up, password reset, email codes |
| `app/Http/Controllers/RetiredController.php` | The redirects and `410 Gone` for the retired Smart Paisa pages and API |
| `app/Support/` | The rules both the website and the API use, so they always agree: reports, budgets, money formatting (`DisplayCurrency`), groups (`Groups`, `GroupLedger`), goals, receipts, accounts, pushes (`Fcm`, `UserPush`), admin notifications (`AdminNotifier`) |
| `app/Jobs/` | Queued work: pushes (`SendPush`), budget alerts (`CheckBudgetAlerts`), admin notification batches (`SendAdminNotification`) |
| `app/Notifications/` | The emails; the ones sent while someone waits are queued (`ShouldQueue`) |
| `app/Console/Commands/` | Scheduled commands (reminders, birthdays, monthly summary, recurring entries, scheduled notifications) |
| `resources/views/tracker/`, `admin/`, `mail/` | Website pages, admin pages, emails |
| `routes/web.php`, `routes/api.php`, `routes/console.php` | Website routes, API routes, the schedule |
| `tests/Feature/` | Feature tests (website, API, admin, scheduled work) |

Uploads are not in git: profile photos in `public/avatars/`, receipt photos in `storage/app/receipts/` (private), support attachments in `storage/app/support/`.

## Run it locally (SQLite)

Needs PHP 8.2+ with `pdo_sqlite`, Composer and Node 18+.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

In `.env` set `DB_CONNECTION=sqlite` and remove the other `DB_*` lines (the database is then `database/database.sqlite`), and keep `MAIL_MAILER=log` so emails go to `storage/logs/laravel.log`. Then:

```bash
touch database/database.sqlite
php artisan migrate
npm ci
npm run dev            # or: npx vite build
php artisan serve      # http://127.0.0.1:8000
php artisan schedule:work   # optional: runs the schedule, and with it the queue, every minute
php artisan user:admin you@example.com   # make your account an admin
```

Optional settings in `.env`:

- **Push notifications:** the Firebase service-account key (Firebase console › Project settings › Service accounts) at `storage/app/firebase-credentials.json`, or wherever `FIREBASE_CREDENTIALS` points. Without it nothing is pushed, and admin notifications reach the app's inbox only.
- **Sign in with Google / Apple:** `GOOGLE_CLIENT_IDS`, `APPLE_CLIENT_IDS` (comma-separated client IDs; empty turns it off).
- **Activity emails** ("You added an expense"): `MAIL_ACTIVITY_NOTIFICATIONS=true|false`.

## Tests

The tests run on an in-memory SQLite database and send no real email:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array MAIL_MAILER=array vendor/bin/phpunit
```

On Windows (PowerShell, Laragon's PHP):

```powershell
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:CACHE_STORE='array'; $env:MAIL_MAILER='array'
& "C:\laragon\bin\php\php-8.2.15-Win32-vs16-x64\php.exe" -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit
```

Add `--filter GroupsTest` (a class or test name) to run part of it. GitHub Actions runs the tests and the asset build on every push (`.github/workflows/tests.yml`).

## The schedule and the queue

The server runs one cron line, every minute:

```
* * * * * /usr/bin/php /path/to/finance-app/artisan schedule:run
```

`routes/console.php` lists what it runs: scheduled admin notifications, the daily "logged today's spending?" reminder, birthday wishes, the monthly summary, recurring entries, token clean-up — and the queue.

Shared hosting allows no long-running processes, so there is no `queue:work` daemon. Instead the scheduler starts one each minute:

```php
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(2);
```

It works until the queue is empty (at most about 50 seconds) and stops. The queue (`QUEUE_CONNECTION=database`, the `jobs` table) carries what used to slow requests down:

- activity emails, and the group emails ("added you to a group"; group news for members with no phone signed in);
- pushes for group news and reached goals (`SendPush`), budget alerts after an expense (`CheckBudgetAlerts`);
- admin notifications: the message is saved at once (it is in every app inbox straight away); its pushes and emails go out in small batches (`SendAdminNotification`) and fill in the counts on Admin › Notifications › Sent, which says "sending…" until they are done.

So these arrive up to about a minute after the action. A job that fails three times is kept in `failed_jobs` (`php artisan queue:failed`, `queue:retry all`). Queued emails are built by the worker, outside any web request, so `APP_URL` must be the site's real address for the links in them to work. Tests use the `sync` queue (`phpunit.xml`), so jobs run inline there.

## Deploying to Hostinger

The site runs on Hostinger shared hosting (PHP 8.3, MySQL 8). The layout:

- `public_html/finance/` — the web root of finance.risodev.com; its `index.php` loads the app from `finance-app/`.
- `public_html/finance/finance-app/` — this repository, a git clone on `main`, with the server's `.env`. It sits inside the web root, so it has an `.htaccess` with `Require all denied`; keep it.
- The server has no Node, so assets are built on your machine.

Steps:

1. **Back up the database** (`mysqldump --single-transaction`, into a folder outside the web root) before any migration.
2. On the server, in `finance/finance-app/`:
   ```bash
   git pull
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   ```
3. If CSS or JS changed: run `npx vite build` locally, then upload `public/build/` to **both** `finance/build/` (the files the browser loads) and `finance/finance-app/public/build/` (the `manifest.json` Laravel reads).
4. Profile photos are saved in `finance-app/public/avatars/` and served through a symlink in the web root. Make it once (and again after any server move), from `finance/`:
   ```bash
   ln -s finance-app/public/avatars avatars
   ```
5. The cron line above must be set in hPanel › Advanced › Cron Jobs (once).
6. There is no config cache on the server, so `.env` changes take effect at once. Keep `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database` and `APP_URL=https://finance.risodev.com`.

Never commit `.env`, the Firebase key or anything uploaded by users.
