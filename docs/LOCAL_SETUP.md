# Run the real Laravel/MySQL application locally

Installing Composer is step one. The application also needs PHP with MySQL support, a running MySQL server, an application database and migrated tables. **Your Composer installation runs on your machine; it does not install PHP/MySQL inside the Arena preview.**

## 1. Check the tools

In a new PowerShell/terminal window:

```text
php -v
php --ini
composer --version
node --version
npm --version
```

Use PHP 8.2+ and Node 22+. `php --ini` identifies the **CLI** php.ini; it can differ from Apache's configuration in WAMP/XAMPP. Enable `pdo_mysql`, mbstring, curl, fileinfo and XML extensions there.

From the repository root, `php scripts/preflight.php` reports missing prerequisites without modifying anything. Missing `.env` or vendor files at this stage is expected.

## 2. Create the databases in phpMyAdmin

- `website_cms` — your application database.
- `website_cms_test` — **disposable test database**, never put real data here.
- Use `utf8mb4` / `utf8mb4_unicode_ci`.

Set up a database user for the app; do not use an unprotected root account in production. Leave the database itself local/private.

## 3. Configure and install

Copy `backend/.env.example` to `backend/.env`. Configure:

```dotenv
APP_NAME=Atelier
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:5173
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=website_cms
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
CMS_ADMIN_EMAIL=your-admin@example.com
CMS_ADMIN_PASSWORD=your-own-strong-initial-password
```

The empty root password example is only appropriate for a private local WAMP/XAMPP setup. Choose a strong application administrator password and never post it in chat. Remove `CMS_ADMIN_PASSWORD` from `.env` after the initial account is seeded. The seeder does not reset existing passwords.

### Guided installation

PowerShell, from the repository root:

```powershell
.\scripts\setup-local.ps1
```

Linux/macOS:

```bash
bash scripts/setup-local.sh
```

Both scripts preserve existing `.env` and application keys, ask before migrating, and never run `migrate:fresh`. They install Composer dependencies, apply migrations/seeds, link media storage, run installation checks, and build the frontend. If your PowerShell policy blocks scripts, use the manual commands below rather than disabling security policy globally.

### Manual installation

```bash
cd backend
composer install
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan storage:link
php artisan cms:doctor
cd ../frontend
npm ci
npm run build
```

Only generate a key for a **new** installation without a key. Changing an existing key invalidates encrypted data and sessions. On updates, retain the key and run `php artisan migrate`, not `migrate:fresh`.

## 4. Start three processes

Terminal 1, `backend/`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, `frontend/`:

```bash
npm run dev -- --port 5173
```

Terminal 3, `backend/`, for scheduled publication:

```bash
php artisan schedule:work
```

Open **http://localhost:5173** and sign in with your seeded credentials. Stop `dev/npm start` if it occupies port 8000. The footer must show **Laravel / MySQL**, not **Local preview · SQLite**. Browser requests use same-origin `/api`; Vite forwards them to Laravel. Use the same hostname consistently (`localhost` vs `127.0.0.1`) to avoid cookie confusion.

For an Arena-hosted service, bind to `0.0.0.0`; `127.0.0.1` above intentionally limits the native local PHP development server to your computer.

## 5. Verify before using real content

```bash
cd backend
php artisan test --configuration=phpunit.mysql.xml
```

**Warning:** the feature suite uses RefreshDatabase and destroys/rebuilds the configured test database. `phpunit.mysql.xml` forces `website_cms_test`; do not change it to the application database. It inherits your connection credentials from the environment. An empty bootstrap admin password is forced during tests to keep fixtures deterministic.

Then run frontend checks:

```bash
npm test --prefix frontend
npm run build --prefix frontend
```

The GitHub workflow `.github/workflows/cms-validation.yml` runs the frontend/preview tests and Laravel tests against MySQL 8 when pushed. It has been supplied but has **not been executed in this workspace**. Review and commit the Composer lockfile produced by your first successful dependency resolution; the workflow also retains it as an artifact.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `composer`/`php` not recognized | Restart terminal; add the correct PHP/Composer installation to PATH |
| Could not find driver | Enable PDO MySQL in the CLI php.ini; restart PHP |
| Database connection refused | Start MySQL in WAMP/XAMPP and check port 3306 |
| Unknown database | Create it in phpMyAdmin; match DB_DATABASE exactly |
| Login rejected | Seeded admin exists, account active, correct password, not the demo identity |
| Missing application key | `php artisan key:generate` only for a fresh installation |
| 419 CSRF mismatch | Use one hostname, correct cookie settings, clear old cookies; check session table and APP_URL |
| API 500 | Read `backend/storage/logs/laravel.log`; share redacted error messages, never `.env` |
| Preview still shows demo records | The Node adapter, not Laravel, is running on port 8000 |
| Upload returns 413 | Adjust nginx/Apache request limit and PHP upload_max_filesize/post_max_size; app limit is 20 MB/file |
| Uploaded files 404 | `php artisan storage:link`; correct APP_URL/public storage URL and permissions |
| Scheduled page doesn't publish | Keep scheduler running; verify system clock and database timezone |
| 409 editing conflict | Export local changes, reload the latest page, review/recover your working copy, then publish deliberately |

For deployment use the production checklist in README.md, not PHP's built-in server. Completion of the commands above validates installation; it does not replace security, load, accessibility and feature acceptance testing.

### New builder/media migration

For an existing installation, back up MySQL and uploaded files, then run `php artisan migrate` from `backend`. The new migration adds nested media folders, menu versions/item keys and saved appearance presets, and maps legacy media folder labels to folder IDs.

Enable PHP **GD** for image crop/resize/optimization; enable **EXIF** for JPEG camera orientation. PHP's GD build needs WEBP support for WEBP export. Large sources are deliberately bounded (16 MP / 20 MB); edited output is bounded to 4 MP. Restart Apache after editing `php.ini` and confirm `php -m` uses the same PHP configuration as the web server.

### Contact, newsletter and store

Run the additive `php artisan migrate` after updating. Configure SMTP and `FRONTEND_URL` for newsletter emails, and grant `submissions`/`commerce` capabilities as appropriate. See [BUSINESS_MODULES.md](BUSINESS_MODULES.md) for setup, checkout policy and verification instructions. No payment gateway credentials are required for the cash-on-delivery baseline.
