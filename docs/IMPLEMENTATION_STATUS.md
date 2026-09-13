## September 13 builder modules update

Implemented nested navigation, image transforms/folders, richer nested blocks and reusable appearance presets in Vue and Laravel, with matching local-preview endpoints. Direct website-tool sidebar links are now present. This is not a claim that the entire original commercial platform is finished.

# Implementation status

This document deliberately separates an implemented core from the much larger product vision. A menu item or schema column is not evidence that an entire commercial module is complete.

## Implemented core

See [Business modules](BUSINESS_MODULES.md) for contact/newsletter setup, store policies, translation maintenance, API contracts and validation boundaries.

| Area | Current implementation |
| --- | --- |
| Frontend | Vue 3, Quasar components, TypeScript, Pinia, responsive layouts, lazy-loaded workspace/public renderer; no Bootstrap |
| Backend | Laravel 12 skeleton, MySQL environment template, migrations/seeders/factories, REST controllers, database transactions, cookie authentication and CSRF |
| Multi-website | Website switcher; server-side membership filtering; page/media/resource lookups scoped to website; unique domains |
| Provisioning | Atomic starter theme settings, website settings, header/footer/navigation records, Home draft with blocks/SEO, template, menu, media folder, four languages and seven currencies |
| Wizard | Name, domain, description, type, default language, currency, theme; branding uploads/contact/favicon not integrated into wizard |
| Dashboard | Database-derived website, published-page, draft and media counts; recent activity; website filtering and grid/list views |
| Themes | Four seeded themes plus site-scoped reusable appearance presets; expanded colors, body/heading fonts, size/line-height, section spacing, card shadow, radius, width and light/dark/system settings |
| Pages | Create/list/search/edit/delete; unique slug per website/language; draft/published/archived; published-only public API |
| Builder | Add/reorder/duplicate/delete/hide blocks; drag page structure; image picker; device widths; private per-user autosave every two idle seconds; recovery/discard; page and working-copy version guards; export local changes on conflict; explicit publish |
| Blocks | 25 choices with dedicated presentations, editable repeatable items, nested Section/Columns, video, galleries/sliders, FAQ and pricing. Contact/Newsletter submit real forms with a private inbox and email verification |
| Revisions | Backend snapshots before committed updates; side-by-side JSON comparison; version-checked restore as draft preserving previous content; in-session block undo/redo |
| Scheduled publication | Date/time dialog, future-time validation, UTC payloads and lock/version-aware every-minute Laravel scheduler. Scheduling an already published page takes it offline until publication |
| Media | Real upload/storage with progress, hierarchical folders, metadata/filter/sort; non-destructive crop/resize/format/quality conversion using GD in Laravel and sharp in local preview; originals preserved |
| Menus | Three-level language/location-specific header/mobile/footer/secondary menus; drag ordering, indent/outdent, safe links, new-tab flags, version conflict checks, responsive public rendering |
| Templates | Save pages/sections/blocks and insert recursively re-keyed templates into layouts; scoped to website |
| Localization | 811 catalogued admin messages in English/Dari/Pashto/Arabic, localized dialogs/options and persistent preference; independent editable/publishable pages; stored translation keys now resolve shared component title/text/button fields; public language switch |
| RTL | Document/body direction, logical spacing, mirrored icons, right-hand sidebar/mobile drawer, Quasar form adjustments and native text inputs; three RTL admin catalogs |
| Currency | Seven catalog currencies, per-website default/enabled settings, editable manual rate and symbol position; tested arithmetic helper |
| SEO | Title/description/slug preview; canonical, robots and Open Graph fields; public SPA metadata; XML sitemap excluding private/draft/noindex content. No SSR/prerendering yet |
| Access control | Normalized permissions/pivot; five protected built-in roles; custom role create/edit/delete; account create/edit/suspend; website Team editor; server-side capability checks; no self-escalation grants; last-active-Super-Admin guard; password changes and session revocation in Laravel |
| Audit | Website/page/appearance/media/resource, account/role/membership changes, login/logout/password changes; global account logs visible to Super Admin only. Autosave heartbeat writes are not logged individually. Not a tamper-proof audit service |
| Contact/newsletter | Consent-validated contact forms, private paginated inbox, read/archive/delete, expiring one-use newsletter confirmation/unsubscribe; real Laravel Mail and explicit preview-email mode |
| Commerce | Products/SKUs, stock/version guards, paginated storefront, cart, COD checkout, server prices and transactional/idempotent reservation; orders, manual payment recording and one-time cancellation stock restoration |
| Public website | Published page content and theme; shared header/logo/tagline, footer, optional announcement and CTA; language-specific navigation; component translations. Private settings/ownership/working copies are not returned. Missing translations return 404 |

## Not implemented / not production-complete

- Email invitations, self-service forgotten-password/email-verification/MFA flows and administrative bulk-account operations. Core account/role/membership editors are implemented; bootstrap admin creation remains environment-driven.
- Freeform canvas dragging, arbitrary cross-container drag moves, genuine linked global block references. Nested layout trees and reusable copied blocks/sections are implemented.
- Rich visual/content-aware revision diffs and collaborative editing. Comparison currently displays JSON side by side. Version conflicts require explicit human review; autosave is a private recovery copy, not a collaboration engine. Browser tab/window closing cannot guarantee delivery of unsent keystrokes.
- Full custom theme package creation, persisted theme reset history, wizard logo/favicon bindings, custom CSS and visitor appearance switcher. Reusable named appearance presets, expanded typography and shadow/spacing tokens are implemented.
- Locale administration/add-language UI, script-specific font bundles, professional linguistic review, and translation workflows beyond independent pages. Admin catalogs, Quasar packs, core validation translations and RTL layout are implemented; future/arbitrary server messages require catalog maintenance.
- SVG sanitization (SVG remains blocked), upload resumption, malware scanning, comprehensive metadata extraction and reference-safe replacement/deletion. Crop/resize/optimization, upload progress and hierarchical folders are implemented.
- Mega-menu content panels, arbitrary linked global blocks, cookie-consent workflows and newsletter campaign/bounce management. Nested mobile/footer/secondary/header menus and language switching are implemented.
- Automatic exchange-rate providers, visitor currency conversion, variants/coupons, invoices/accounting reports, blog taxonomy, online payment gateways, tax/shipping engines and automated refunds. Product catalog, base-currency COD checkout, transactional inventory and order administration are implemented; exchange rates are not an accounting system.
- Full global search. Current workspace search covers website names/domains; pages and media have independent scoped search.
- robots.txt policy/endpoint, structured data, dedicated social-image picker, server-side rendering/prerendering and multilingual SEO relationships. Canonical/Open Graph/robots metadata and sitemap are implemented, but SPA metadata is not equivalent to server-rendered crawler support. The current sitemap is a single document; partition large sites before exceeding sitemap limits.
- Automated custom domain/DNS/TLS provisioning. Domain is configuration; public sites currently use `/site/{id}/{slug}`. No host-based tenant resolver is installed.
- Tenant quotas, soft deletion, retention/purge jobs, production queue workers for media, storage/CDN adapters, robust notifications, account settings, audit export and operational monitoring.
- Full API pagination and module separation. The core schema uses relational ownership and JSON for block composition; it is not a fully normalized block/section graph. Some API resource operations share a controller and should be decomposed as modules grow.

## Installation and test automation

PowerShell/Bash setup scripts, standalone PHP prerequisite checking, a non-destructive `cms:doctor` command, dedicated MySQL test config, and a GitHub MySQL 8 workflow are included. The workflow and Laravel feature tests are **not claimed to have run** in this environment. See LOCAL_SETUP.md.

## Important runtime distinctions

The Laravel backend implements real authentication and permissions. The development adapter intentionally grants an anonymous preview administrator. It is for trying the UI, not for validating production security. It uses a generic SQLite record store, not the Laravel/MySQL tables. Demo account records do not support real sign-in and passwords are not stored; password change explicitly requires Laravel. Its upload filter is less strict than Laravel's content-based MIME validation. Do not expose the adapter in a production deployment.

MySQL migration compatibility is designed using Laravel's portable schema builder, but is **unverified in this environment**. Production readiness must be established with the documented MySQL test run and security review, not inferred from passing preview tests.
