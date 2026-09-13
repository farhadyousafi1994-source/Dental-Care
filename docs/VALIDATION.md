# Validation record

## Executed in the Arena workspace

- Frontend `vue-tsc -b` and Vite production build: **passed**.
- Frontend Vitest: **13/13 passed** (direction/theme/currency helpers; session/CSRF request de-duplication and bounded retry; conflict and non-JSON error handling).
- Isolated Node/SQLite preview API integration suite: **15/15 passed** (session requirement, website provisioning, duplicate domains, draft visibility, cross-site identifiers, publication, persistence, appearance, revision restore, translation/menu/currency persistence, media upload/edit/delete; private autosave; stale-write conflicts; account/role/membership demo storage; future scheduling; global components/translations and sitemap filtering).
- `npm audit` in frontend and dev: **0 reported vulnerabilities** at verification time.
- PHP syntax checks using an npm-distributed PHP WASM CLI: custom models, services, controller, migrations, seeders, routes and tests passed.
- Headless Chromium: page list, builder load/add/edit/save/restore, appearance, media, menus, templates, language and currency screens loaded; isolated five-step wizard created a site and opened its provisioned appearance module; public page rendered stored content.
- Mobile Chromium viewport: dashboard `document.scrollWidth === innerWidth === 390`; no horizontal overflow.
- Second-iteration Chromium checks passed for private autosave preserving live content, reopen/recovery, explicit publication, conflict/export banner, global header persistence/rendering, account creation form and custom role editor. Long combined Chromium runs hit screenshot/navigation timeouts after successful functional checks. Separate account/role and mobile runs passed with zero page errors; mobile scroll width remained 390px. This is targeted browser coverage, not a completed full regression run.
- Fixed TypeScript configuration to use `noEmit`; removed generated JS sidecars that could shadow TypeScript modules and duplicate tests. Counts above refer only to source tests.
- Bundled fonts/photos avoid external runtime image/font requests.

## Not executed

- Composer dependency resolution or Laravel bootstrap against installed vendor packages.
- `php artisan migrate` on a running MySQL instance.
- Laravel feature-test suite (`CmsTest.php`) on SQLite or MySQL.
- Full browser regression suite against the real Laravel backend.
- GitHub workflow execution or PowerShell setup script execution on a Windows/WAMP machine. The workflow, setup scripts and new access/editing/global-publication feature tests are supplied for local/CI verification.
- Production security audit, accessibility audit, load/scale benchmarks, backup/restore drill, custom-domain deployment and cross-browser/RTL QA.

The Node preview tests do not prove that the Laravel implementation passes its tests. PHP syntax checks do not validate framework behavior, schema migration success or SQL semantics. A MySQL-specific test configuration is supplied so these checks can be completed in an appropriate environment.

## September 13 builder modules validation

Executed in this workspace:
- Frontend: **18 tests passed** (API, appearance/domain, recursive block construction/cloning/depth and safe links).
- Local preview API: **20 tests passed**, including multilingual nested menu conflicts, folder cycles/tenant isolation, actual sharp raster conversion retaining original bytes, preset isolation, nested publication and duplicate block rejection.
- Vue TypeScript checking + Vite production build passed.
- PHP syntax parsing: **56 PHP files passed**, using PHP-WASM (syntax only, not a native Laravel runtime).
- Headless Chromium smoke: explicit sidebar navigation to Menus, Theme & Appearance, Media library and Pages loaded without JavaScript page errors.

Added five Laravel feature tests in `BuilderModulesTest.php`. They have **not been executed**: native PHP/Composer/MySQL are unavailable here. Run the documented disposable MySQL test workflow before deployment. Preview integration tests use the explicitly development-only Node/SQLite adapter; they do not prove MySQL migrations, native GD encoding, Laravel validation/permissions or deployment correctness.

## Contact/newsletter, commerce and localization follow-up

Latest: **21 frontend tests**, **25 preview API tests**, **811 catalogued messages with no missing RTL translations**, TypeScript/Vite build and **64 PHP syntax checks** passed. Chromium completed product creation, checkout, cancellation/stock restoration, contact inbox, newsletter confirmation, three RTL admin views and mobile shop. A separate check verifies computed RTL body direction, right-side sidebar and mobile drawer. Six native Laravel feature tests were added but not executed here. Detailed evidence and limits: [BUSINESS_MODULES.md](BUSINESS_MODULES.md#validation-performed).
