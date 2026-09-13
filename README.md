# Atelier — multi-website CMS

A working **Vue 3 + Quasar + TypeScript** administration application, with a **Laravel 12 / MySQL** backend implementation and a public, content-driven website renderer.

**Status: functional CMS foundation, not the complete commercial product described in the brief.** See [implementation status](docs/IMPLEMENTATION_STATUS.md) for explicit limitations. The original three HTML files are preserved; the new application lives in `frontend/` and `backend/`.

## Important: two different runtimes

| Runtime | Purpose | Persistence | Authentication |
| --- | --- | --- | --- |
| `backend/` | Deployable Laravel implementation, requires integration validation | MySQL 8+ | Laravel session authentication, CSRF, server-side role checks and website membership |
| `dev/` | **Local interactive preview only; do not deploy** | SQLite in `.runtime/preview.sqlite` | Deliberately anonymous demo administrator with temporary session cookies |

The live Arena preview uses the second runtime because native PHP, Composer, and MySQL are unavailable in this workspace. It is a real persistent local API, **not a validated substitute for Laravel/MySQL**. The footer identifies the active runtime. Node's preview SQLite schema is intentionally different from the production schema. No automatic migration of preview data is supplied.

## New in this iteration

- Contact inbox, consent-based newsletter verification/unsubscribe, products, storefront/cart, COD checkout, stock reservations and order administration. [Setup and policies](docs/BUSINESS_MODULES.md).
- 811 admin translation messages for English, Dari, Pashto and Arabic; translated controls/dialogs and corrected RTL body/sidebar/mobile drawer behavior.
- Nested multilingual menus, editable media/folders, nested rich blocks and reusable appearance presets.

- Normalized permissions, protected built-in roles, custom role editor, account creation/update/suspension, website teams and Laravel password/session controls.
- Private per-user autosaves, recovery, version-conflict protection, export-on-conflict, scheduled-publication controls and side-by-side JSON revision comparison.
- Shared header/footer/announcement/global CTA editor, component translation resolution, canonical/robots/Open Graph fields and published-only sitemap.
- PowerShell/Bash local setup scripts, `php artisan cms:doctor`, and a GitHub MySQL 8 validation workflow.

**For your local Composer installation, start with [LOCAL_SETUP.md](docs/LOCAL_SETUP.md).** The new migration is additive to the earlier installation: run `php artisan migrate`; do not reset your database.

## Explore the preview

Requirements: Node 22.13+ (the local adapter uses `node:sqlite`).

```bash
cd dev
npm ci
npm start
```

In another terminal:

```bash
cd frontend
npm ci
npm run dev -- --port 5173
```

Open port **5173**. The frontend proxies `/api` and `/storage` to port 8000; browser code never calls localhost directly. The development database and uploads are excluded from Git. The original four sample websites, page content, assets, navigation and activity are seeded once.

### Try a complete flow

1. **Create website** → complete the five-step wizard.
2. Customize the automatically provisioned **Theme & Appearance** and save.
3. Open **Pages** → edit the starter Home page or create another page.
4. Add blocks, drag the page structure, edit text, select uploaded media, then **Publish**.
5. In **Settings**, set the website itself to **published** and save.
6. Click **Visit website**. `/site/{websiteId}/home` reads the published content from the API.
7. Return to the builder. Changes autosave to a **private working copy** without altering the live page. Recover it on reopen, publish deliberately, or compare/restore revisions. Restoring a revision makes the page a draft and takes it offline.

A page must be published **and its website must be published** to be publicly accessible. Translations are independent pages selected with `?lang=fa`, `?lang=ps`, or `?lang=ar`; missing translations return 404 rather than silently replacing English text.

## Install Laravel / MySQL

Requirements: PHP 8.2+, Composer 2, MySQL 8+, Node 22+, and the standard Laravel PHP extensions including PDO MySQL, mbstring, XML, curl, fileinfo, OpenSSL and tokenizer. WAMP/XAMPP must use an appropriate PHP version.

1. In phpMyAdmin, create a database named `website_cms`, character set **utf8mb4**, collation **utf8mb4_unicode_ci**. Use a dedicated database user for production.
2. Install and configure Laravel:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

On Windows, use `copy .env.example .env` instead of `cp`. Set:

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
QUEUE_CONNECTION=database
CMS_ADMIN_EMAIL=your-admin@example.com
CMS_ADMIN_PASSWORD=choose-a-long-unique-password
```

`root` with no password is for a private local WAMP/XAMPP installation only. Do not deploy these credentials. The seeder does not create an administrator if `CMS_ADMIN_PASSWORD` is empty. It does not reset existing administrator passwords on subsequent runs. Remove the bootstrap password from the environment after creating the account.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

3. Stop the Node preview adapter if it is running on port 8000. Start the Quasar/Vue frontend with the commands above. Sign in using your seeded administrator credentials.
4. For scheduled publication, run `php artisan schedule:work` locally, or configure the production scheduler. Queue tables/configuration are included; no media optimization or exchange-rate worker has been implemented.

### phpMyAdmin compatibility

All deployment migrations use Laravel schema APIs and MySQL-compatible foreign keys, indexes, transactions and JSON columns. There is no PostgreSQL functionality. Tables are visible and editable through phpMyAdmin. Export/import SQL there for local backups. For production, use a coordinated database backup plus `backend/storage/app/public`, protected environment/secrets backup, and restore testing. Do not edit page JSON or normalized role/permission records without understanding application invariants.

## Structure

```text
frontend/
  src/components/     shell, dashboard, wizard, builder, appearance, media, public renderer
  src/store.ts        Pinia workspace state
  src/api.ts          same-origin cookie/CSRF REST client
  src/domain.ts       token resolution, direction, currency arithmetic helper
  public/images/      bundled original AI-generated demonstration photographs
backend/
  app/Domains/        Websites, Content, Access
  app/Events/         post-transaction website provisioning event
  app/Http/Controllers/Api/
  database/           migrations, seeders, factories
  routes/web.php     session-authenticated REST routes under /api
  routes/console.php scheduled publishing
  tests/             feature and unit tests
  storage/           Laravel storage structure
  phpunit.mysql.xml  dedicated MySQL test database configuration
  .env.example       MySQL configuration, no application secrets
 dev/                explicitly non-production Node/SQLite preview adapter
 docs/               API summary, feature status, validation report
 deploy/             example same-origin nginx configuration
```

Laravel uses **web middleware for the REST endpoints intentionally**: this provides cookie sessions and CSRF protection without storing authentication tokens in localStorage. The frontend fetches a CSRF token before writes and refreshes it after login/logout.

## Tests and verification

```bash
npm test --prefix frontend       # 13 helper/API-client tests
npm run build --prefix frontend  # TypeScript check + production build
npm test --prefix dev            # 15 isolated preview API integration tests
npm audit --prefix frontend
npm audit --prefix dev

cd backend
php artisan test                # Fast SQLite feature suite; dependencies required
# CAUTION: RefreshDatabase destroys data in the configured test database.
# Create EMPTY website_cms_test in phpMyAdmin before using this configuration:
php artisan test --configuration=phpunit.mysql.xml
```

The frontend tests/build and preview integration tests passed in this workspace. Custom PHP code passed PHP WASM syntax checks. **Composer installation, Laravel feature-test execution, and MySQL migrations were not run here.** The backend test suite is supplied, not claimed to have passed. See [validation](docs/VALIDATION.md).

## Production checklist

- Complete missing product modules and validate migrations/feature tests against real MySQL.
- Never deploy `dev/`; never expose its anonymous-admin endpoints publicly as a production service.
- Serve frontend build and Laravel API on the same HTTPS origin. Adapt `deploy/nginx.conf.example`.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, secure database credentials, and the public APP_URL. Configure trusted proxies explicitly for your infrastructure.
- Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`, and `npm run build` in the frontend. Commit a reviewed Composer lockfile after the first successful install.
- Make only Laravel `storage` and `bootstrap/cache` writable. Never expose `.env`, source, vendor, or database files from the web server.
- Configure PHP upload/request limits, TLS, appropriate HTTP security headers, session retention, log rotation, backup/restore, monitoring, scheduler, and queue supervision.
- Add malware scanning and sandboxed image processing before accepting untrusted production uploads. SVG uploads are deliberately disabled until sanitization is implemented.
- Perform accessibility, tenant-isolation, permission, CSRF, upload, performance and disaster-recovery testing before launch.

## Design reference and assets

The referenced [Kabul University repository](https://github.com/farhadyousafi1994-source/Kabul-University-) was inspected, especially its appearance service, token registry and theme components. Atelier uses the same useful **preset → tokens → CSS variables** principle with an original design and code implementation. Demo website names/content are fictional. The three bundled demonstration photographs are AI-generated. Fonts are self-hosted through Fontsource packages with their included licenses; icons use Lucide and Quasar's bundled Material Icons.
