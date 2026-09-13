# REST API

All URLs are relative to the frontend origin. Responses are JSON. Authenticated Laravel routes require a session cookie. Mutating requests require `X-CSRF-TOKEN` from `GET /api/csrf`. Send `Accept: application/json` for JSON authentication/validation failures. Login rotates the session; obtain a fresh CSRF token afterward.

| Method | Path | Purpose / permission |
| --- | --- | --- |
| GET | `/api/csrf` | Session CSRF token |
| POST | `/api/auth/login` | email/password; rate-limited 5/minute |
| GET | `/api/auth/me` | Current authenticated user |
| POST | `/api/auth/logout` | Invalidates session |
| PUT | `/api/auth/password` | Current password + new confirmed strong password; other sessions revoked |
| GET | `/api/access` | Accounts, roles and permission catalog; Super Admin only |
| POST / PUT | `/api/users` / `/api/users/{user}` | Create/update/suspend an account; Super Admin; last owner protected |
| POST / PUT / DELETE | `/api/roles` / `/api/roles/{role}` | Custom roles; protected built-ins cannot be modified/deleted |
| GET / POST | `/api/websites/{site}/members` | Website membership list/upsert by existing active email; users capability |
| DELETE | `/api/websites/{site}/members/{user}` | Remove membership, not the global account |
| GET | `/api/websites/{site}/pages/{page}/editor` | Saved page + requesting user's private working copy |
| PUT | `/api/websites/{site}/pages/{page}/autosave` | Validated private snapshot; `version` and `autosave_version` preconditions |
| DELETE | `/api/websites/{site}/pages/{page}/autosave` | Discard own copy with `autosave_version` precondition |
| GET | `/api/public/websites/{site}/sitemap.xml` | XML sitemap of published, indexable pages; APP_URL must match frontend origin |
| GET | `/api/dashboard` | Accessible websites, scoped counts/activity |
| POST | `/api/websites` | Atomic provisioning; global administrator |
| PUT | `/api/websites/{site}` | Website settings; settings permission |
| GET | `/api/websites/{site}/pages` | List website pages; read permission |
| POST | `/api/websites/{site}/pages` | Create page; pages permission |
| PUT | `/api/websites/{site}/pages/{page}` | Save and revision; pages permission |
| DELETE | `/api/websites/{site}/pages/{page}` | Delete page/revisions; pages permission |
| GET | `/api/websites/{site}/pages/{page}/revisions` | Last 50 revisions |
| POST | `/api/websites/{site}/pages/{page}/revisions/{revision}/restore` | Restore as draft |
| PUT | `/api/websites/{site}/appearance` | theme + appearance object; appearance permission |
| GET | `/api/websites/{site}/media` | Media records |
| POST | `/api/websites/{site}/media` | Multipart `files[]`; media permission |
| PUT | `/api/websites/{site}/media/{media}` | Name, alt, caption, folder metadata |
| DELETE | `/api/websites/{site}/media/{media}` | File/record deletion |
| GET | `/api/websites/{site}/{resource}` | templates, menus, translations, currencies, components |
| POST | `/api/websites/{site}/{resource}` | Save templates, menus, translations, currencies; components writes limited to Header/Footer/Announcement Bar/Global CTA |
| GET | `/api/public/websites/{site}/{slug}?lang=en` | Published website/page, theme, menus and footer; no session required |

Website creation example:

```json
{
  "name": "My Studio",
  "domain": "studio.example.com",
  "description": "Thoughtful work for a brighter tomorrow.",
  "type": "Agency",
  "theme": "Evergreen",
  "default_language": "en",
  "default_currency": "USD"
}
```

Page save example:

```json
{
  "version": 1,
  "autosave_version": 0,
  "title": "Home",
  "slug": "home",
  "language": "en",
  "status": "draft",
  "blocks": [
    { "id": "unique-client-id", "type": "Text", "title": "Our story", "text": "Welcome." }
  ],
  "seo": { "title": "My Studio", "description": "Independent studio." }
}
```

Updates require the current page `version`; a working copy also has its own monotonically increasing `autosave_version` (0 before the first autosave). Commit/restore/scheduled publication increments page version. A mismatch returns **409** without overwriting data. Autosave never mutates the saved/live page, and snapshots are private to the requesting user. Restore explicitly changes a page to draft.

Component text translation keys are `components.{component name}.{field}`, e.g. `components.Header.title` or `components.Global CTA.button`. Global edits apply immediately on all pages.

Laravel responds with 401 for unauthenticated access, 403 for missing capabilities, 404 for inaccessible website/record combinations, and 422 for validation failures. Errors are surfaced as notifications by the frontend. The current list endpoints are not paginated; introduce pagination before large-scale use.

## Builder modules — September 13 update

All administrative routes below require authenticated site membership and the matching `menus`, `media`, `appearance`, or `pages` capability in Laravel.

- `GET|POST /api/websites/{site}/menus`: language/location-scoped menu trees. Write `{name,location,language,version,items}`; locations `header|mobile|footer|secondary`. Items `{key,label,url,new_tab,children}`; three levels, 100 links. New menu version `0`; stale saves return `409`.
- `GET|POST /api/websites/{site}/media-folders`; `PUT|DELETE .../media-folders/{folder}`. Folder writes `{name,parent_id}`; tenant and cycle validation; deletion requires an empty folder.
- `PUT /api/websites/{site}/media/{media}`: `{name,alt,caption,description,folder_id}`.
- `POST /api/websites/{site}/media/{media}/transform`: `{x,y,crop_width,crop_height,width,quality,format,name}`. Crop coordinates are percentages. Formats `jpeg|png|webp`; quality 20–100, width 16–4096. Creates a new media record/file, never overwrites the original. Laravel requires GD, with EXIF recommended for phone JPEG orientation. Local stored sources only; 16 MP / 20 MB input, 4 MP / 4096 px edge output. Rate limited to 10 requests/minute in Laravel.
- `GET|POST /api/websites/{site}/theme-presets`; `PUT|DELETE .../theme-presets/{preset}`. Write `{name,theme,appearance}`. Saving a preset does not activate it: load it in the appearance preview and use the existing appearance save endpoint to activate.
- Page/template `blocks` now support 25 types, `items`, `children: Block[][]`, `link`, `imageAlt`, and `imageStyle:{fit,position,radius,opacity}`. Maximum six nesting levels, 100 blocks, 500 items, 2 MB. Only Section/Columns can contain children. Block IDs must be unique throughout a page.
- Public payload includes `menu_locations` for all four locations; `menus` remains the selected-language header alias.

Contact and Newsletter now render submission forms; see BUSINESS_MODULES.md for verification/inbox routes. Pricing, Events and Team are authored content blocks, not database-backed commerce/event/person collections.

## Contact/newsletter and commerce

See [BUSINESS_MODULES.md](BUSINESS_MODULES.md#api-routes) for the new public submission/catalog/checkout contracts and private inbox/product/order routes.
