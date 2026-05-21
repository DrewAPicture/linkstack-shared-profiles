# Package Architecture

## Identity

| Key | Value |
|---|---|
| Composer name | `werdswords/linkstack-shared-profiles` |
| Root namespace | `WerdsWords\LinkStack\SharedProfiles` |
| Service provider | `WerdsWords\LinkStack\SharedProfiles\ServiceProvider` |
| Config key | `linkstack-shared-profiles` |
| View namespace | `linkstack-shared-profiles::` |

Auto-discovery via `extra.laravel.providers` in `composer.json` means `composer require` is the entire installation step — no manual registration in `config/app.php`.

---

## File Tree

```
src/
  ServiceProvider.php
  Http/Controllers/
    ApiLinkController.php       GET+POST /api/links, approve, deny
    TelegramAuthController.php  GET /telegram-auth/{profileId}, callback, POST /telegram-login
    ModerationController.php    GET/POST /studio/moderation
  Models/
    TelegramManager.php
  Services/
    TelegramMessagingService.php  sendMessage() — wraps Telegram Bot API HTTP calls

database/migrations/
  2024_01_01_000001_add_api_token_to_users_table.php
  2024_01_01_000002_add_status_to_links_table.php
  2024_01_01_000003_create_telegram_managers_table.php

routes/
  api.php    POST /api/links (throttle)
  web.php    Telegram auth routes + moderation routes

resources/views/
  moderation/index.blade.php

config/
  linkstack-shared-profiles.php
```

---

## Service Provider

The service provider is the **only** integration point with the host application. Nothing in LinkStack's core files is modified.

| `register()` call | Effect |
|---|---|
| `mergeConfigFrom(...)` | Merges package config defaults under `linkstack-shared-profiles` |
| `singleton(TelegramMessagingService::class, ...)` | Binds the messaging service as a singleton |

| `boot()` call | Effect |
|---|---|
| `loadRoutesFrom(routes/web.php)` | Registers Telegram auth + moderation routes |
| `loadRoutesFrom(routes/api.php)` | Registers `POST /api/links` |
| `loadMigrationsFrom(database/migrations)` | Auto-runs migrations on `artisan migrate` |
| `loadViewsFrom(resources/views, 'linkstack-shared-profiles')` | Registers `linkstack-shared-profiles::` view namespace |
| `View::composer('linkstack.linkstack', ...)` | Filters pending/rejected links before public profile renders |
| `Socialite::extend('telegram', ...)` | Registers Telegram driver without touching `config/app.php` |

### View Composer

`UserController::littlelink()` in LinkStack builds the public profile link list via a raw `DB::table('links')` query — **not** Eloquent — so a model global scope cannot intercept it. The view composer fires immediately before the `linkstack.linkstack` Blade template renders and strips any links where `status` is not `'published'`.

The filter is additive: if the `status` column doesn't exist yet (migration not run), `!isset($link->status)` is true and all links pass through unchanged.

---

## Data Model

### `users` table additions

| Column | Type | Notes |
|---|---|---|
| `api_token` | `string(80)` | Unique, nullable. Used by `POST /api/links` bearer auth. |
| `telegram_bot_token` | `string` | Nullable. Per-profile bot token for Telegram auth HMAC. Falls back to `linkstack-shared-profiles.bot_token` config when null. |
| `auto_approve` | `boolean` | Nullable. Per-profile auto-approve override. Falls back to `linkstack-shared-profiles.auto_approve` config when null. |

### `links` table additions

| Column | Type | Default | Notes |
|---|---|---|---|
| `status` | `enum(pending, published, rejected)` | `'published'` | Default preserves all pre-existing and studio-created links. Only API-submitted links arrive as `'pending'`. |

### `telegram_managers` table (new)

| Column | Type | Notes |
|---|---|---|
| `id` | auto-increment PK | |
| `telegram_id` | `string`, unique | Telegram user ID as a string |
| `profile_id` | `unsignedBigInteger` | FK → `users.id` (cascade delete) |
| `role` | `enum(owner, moderator)` | Default `'moderator'` |
| `added_by` | `unsignedBigInteger`, nullable | Telegram ID of the user who added this record |
| `created_at` | `timestamp` | Single timestamp; no `updated_at` (`$timestamps = false`) |

---

## Routes

All package routes use distinct URL prefixes to avoid collision with LinkStack's own routes.

| Method | URL | Middleware | Controller |
|---|---|---|---|
| `GET` | `/api/links` | `throttle:60,1` | `ApiLinkController@index` |
| `POST` | `/api/links` | `throttle:60,1` | `ApiLinkController@store` |
| `POST` | `/api/links/{id}/approve` | `throttle:60,1` | `ApiLinkController@approve` |
| `DELETE` | `/api/links/{id}` | `throttle:60,1` | `ApiLinkController@deny` |
| `GET` | `/telegram-auth/{profileId}` | `web` | `TelegramAuthController@redirect` |
| `GET` | `/telegram-auth/{profileId}/callback` | `web` | `TelegramAuthController@callback` |
| `POST` | `/telegram-login` | `web`, no CSRF | `TelegramAuthController@initDataLogin` |
| `GET` | `/studio/moderation` | `web`, `auth`, `blocked` | `ModerationController@index` |
| `POST` | `/studio/moderation/{id}/approve` | `web`, `auth`, `blocked` | `ModerationController@approve` |
| `POST` | `/studio/moderation/{id}/reject` | `web`, `auth`, `blocked` | `ModerationController@reject` |

**Why `web` middleware is explicit on package routes:** package routes are loaded via `loadRoutesFrom()`, which bypasses the host application's `RouteServiceProvider`. They do not automatically inherit the `web` middleware group that wraps `routes/web.php` in the host app. Session support (`StartSession`) is required for `Auth::loginUsingId()` and `$request->session()`.

**Why `POST /telegram-login` is CSRF-exempt:** Telegram Mini Apps inject `initData` directly without a prior page load that would set a CSRF cookie. The HMAC-signed `initData` itself is the authentication proof.

---

## Feature Summaries

### Feature 1 — API Link Submission and Moderation

All four endpoints share bearer token auth: the token is resolved to a `users` row via `users.api_token` in a private `resolveUser()` helper that aborts 401 on failure.

- `GET /api/links` — returns pending links for the authenticated profile as a JSON `data` array. Each item includes `id`, `link`, `title`, `button_id`, `meta` (decoded from `type_params`), and `submitted_at`.
- `POST /api/links` — validates and inserts a link. Status is `'pending'` or `'published'` depending on the per-profile or global `auto_approve` setting. Contributor metadata in `meta` is stored as JSON in `type_params`.
- `POST /api/links/{id}/approve` — sets a pending link's status to `'published'`. Returns 404 if the link is not found, not pending, or belongs to another profile.
- `DELETE /api/links/{id}` — hard-deletes a pending link. Returns 404 if the link is not found, not pending, or belongs to another profile. LinkStack does not support soft deletes on the `links` table.

### Feature 2 — Telegram Multi-User Management

Two authentication flows share the `telegram_managers` table:

- **Approach A (Socialite):** Browser-based Login Widget via `GET /telegram-auth/{profileId}` → `GET /telegram-auth/{profileId}/callback`. The `{profileId}` (a `users.id`) is encoded in the URL so the callback can resolve the per-profile bot token without session state. Before each Socialite call, the controller overrides `services.telegram.client_secret` (the token `socialiteproviders/telegram` uses for HMAC) with the profile's token. Falls back to the global config when `users.telegram_bot_token` is null.

- **Approach B (initData):** Telegram Mini App posts `Telegram.WebApp.initData` to `POST /telegram-login`. The controller resolves the `telegram_id` from the payload, looks up the manager record first (fast-fail on unknown IDs), then fetches the matching per-profile token before running HMAC verification. Falls back to the global config token when the profile has no per-profile token set.

Both flows log in using `Auth::loginUsingId($manager->profile_id)`, establishing a standard Laravel session as the shared profile user. All LinkStack ownership checks (`user_id` equality) pass without modification.

### Feature 3 — Link Moderation Queue

`GET /studio/moderation` lists pending links for the authenticated user joined to `buttons` for display. `POST /studio/moderation/{id}/approve` and `reject` update `status` with an ownership `WHERE user_id = Auth::id()` guard — cross-user changes are impossible even if the route guard were bypassed.

The view is published separately (`--tag=linkstack-shared-profiles-views`) so host apps can extend their own layout.

---

## Ownership Model

LinkStack's `LinkId` middleware checks `$user->id != $link->user_id`. Telegram managers are authenticated **as** the shared profile's `user_id` via `Auth::loginUsingId()`, so this check passes without any modification to LinkStack core files.

---

## Config

```php
// config/linkstack-shared-profiles.php
return [
    'bot_token'     => env('TELEGRAM_BOT_TOKEN'),
    'auto_approve'  => env('LINKSTACK_SHARED_PROFILES_AUTO_APPROVE', false),
    'auth_date_ttl' => 300,  // seconds before Telegram initData is considered stale
];
```
