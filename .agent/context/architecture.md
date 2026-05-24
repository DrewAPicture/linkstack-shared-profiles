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
  Concerns/
    HasApiToken.php             trait implementing ApiTokenableContract
  Contracts/
    ApiTokenableContract.php    interface: setApiToken(), scopeForToken()
  Events/
    PendingLinkSubmitted.php
  Http/Controllers/
    ApiLinkController.php       GET+POST /api/links, approve, deny
    ModerationController.php    GET/POST /studio/moderation
  Providers/
    Contracts/
      NotifierContract.php
    Controllers/
      AbstractWebhookController.php
    Listeners/
      NotifyProvidersOfPendingLink.php
    Models/
      ProviderManager.php
      ProviderSetting.php
    Support/
      AuthReplayGuard.php
    ServiceProvider.php

database/migrations/
  2026_05_24_000001_add_api_token_to_users_table.php
  2026_05_24_000002_add_status_to_links_table.php
  2026_05_24_000003_add_auto_approve_to_users_table.php
  2026_05_24_000004_create_provider_managers_table.php
  2026_05_24_000005_create_provider_settings_table.php

routes/
  api.php    POST /api/links (throttle)
  web.php    moderation routes

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

| `boot()` call | Effect |
|---|---|
| `is_a($model, ApiTokenableContract::class, true)` | Checks auth user model implements `ApiTokenableContract`; throws `RuntimeException` if not |
| `loadRoutesFrom(routes/web.php)` | Registers moderation routes |
| `loadRoutesFrom(routes/api.php)` | Registers `POST /api/links` |
| `loadMigrationsFrom(database/migrations)` | Auto-runs migrations on `artisan migrate` |
| `loadViewsFrom(resources/views, 'linkstack-shared-profiles')` | Registers `linkstack-shared-profiles::` view namespace |
| `View::composer('linkstack.linkstack', ...)` | Filters pending/rejected links before public profile renders |
| `Event::listen(PendingLinkSubmitted, NotifyProvidersOfPendingLink)` | Wires the provider fan-out listener |

### Notifier Registry

`ServiceProvider` maintains a static registry of `NotifierContract` implementations. Provider packages register themselves during their own `boot()`:

```php
ServiceProvider::registerNotifier(new MyProviderNotifier(...));
```

When `PendingLinkSubmitted` fires, `NotifyProvidersOfPendingLink` iterates all registered notifiers and calls `notifyModerators()` on each. `flushNotifiers()` is available for test isolation.

### View Composer

`UserController::littlelink()` in LinkStack builds the public profile link list via a raw `DB::table('links')` query — **not** Eloquent — so a model global scope cannot intercept it. The view composer fires immediately before the `linkstack.linkstack` Blade template renders and strips any links where `status` is not `'published'`.

The filter is additive: if the `status` column doesn't exist yet (migration not run), `!isset($link->status)` is true and all links pass through unchanged.

---

## Data Model

### `users` table additions

| Column | Type | Notes |
|---|---|---|
| `api_token` | `string(64)` | Unique, nullable. Stored as a SHA-256 hash of the raw token. The raw token is sent by the client in the `Authorization: Bearer` header; the package hashes it before lookup via `HasApiToken` trait / `ApiTokenableContract`. |
| `auto_approve` | `boolean` | Nullable. Per-profile auto-approve override. Falls back to `linkstack-shared-profiles.auto_approve` config when null. |

### `links` table additions

| Column | Type | Default | Notes |
|---|---|---|---|
| `status` | `enum(pending, published, rejected)` | `'published'` | Default preserves all pre-existing and studio-created links. Only API-submitted links arrive as `'pending'`. |

### `provider_managers` table (new)

| Column | Type | Notes |
|---|---|---|
| `id` | auto-increment PK | |
| `provider` | `string` | Provider slug, e.g. `'telegram'`, `'discord'` |
| `external_id` | `string` | Platform user ID as a string |
| `profile_id` | `unsignedBigInteger` | FK → `users.id` (cascade delete) |
| `role` | `enum(owner, moderator)` | Default `'moderator'` |
| `added_by` | `string`, nullable | Platform ID of the user who added this record |
| `created_at` | `timestamp` | Single timestamp; no `updated_at` |

Unique constraint on `(provider, external_id)`. Use `ProviderManager::forProvider('telegram')` scope to filter by provider.

### `provider_settings` table (new)

| Column | Type | Notes |
|---|---|---|
| `id` | auto-increment PK | |
| `profile_id` | `unsignedBigInteger` | FK → `users.id` (cascade delete) |
| `provider` | `string` | Provider slug |
| `settings` | `text` | Provider-specific config; encrypted at rest via `encrypted:array` cast — reads/writes as a plain PHP array. Values are shielded from stack traces via `#[SensitiveParameter]` on the internal setter. |

Unique constraint on `(profile_id, provider)` — one row per provider per profile. Use `ProviderSetting::forProvider('telegram')` scope and the Eloquent toolkit directly.

---

## Routes

All package routes use distinct URL prefixes to avoid collision with LinkStack's own routes.

| Method | URL | Middleware | Controller |
|---|---|---|---|
| `GET` | `/api/links` | `throttle:60,1` | `ApiLinkController@index` |
| `POST` | `/api/links` | `throttle:60,1` | `ApiLinkController@store` |
| `POST` | `/api/links/{id}/approve` | `throttle:60,1` | `ApiLinkController@approve` |
| `DELETE` | `/api/links/{id}` | `throttle:60,1` | `ApiLinkController@deny` |
| `GET` | `/studio/moderation` | `web`, `auth`, `blocked` | `ModerationController@index` |
| `POST` | `/studio/moderation/{id}/approve` | `web`, `auth`, `blocked` | `ModerationController@approve` |
| `POST` | `/studio/moderation/{id}/reject` | `web`, `auth`, `blocked` | `ModerationController@reject` |

**Why `web` middleware is explicit on package routes:** package routes are loaded via `loadRoutesFrom()`, which bypasses the host application's `RouteServiceProvider`. They do not automatically inherit the `web` middleware group that wraps `routes/web.php` in the host app. Session support (`StartSession`) is required for `Auth::loginUsingId()` and `$request->session()`.

---

## Feature Summaries

### Feature 1 — API Link Submission and Moderation

All four endpoints share bearer token auth: the token is resolved to a `users` row via `users.api_token` in a private `resolveUser()` helper that aborts 401 on failure.

- `GET /api/links` — returns pending links for the authenticated profile as a JSON `data` array. Each item includes `id`, `link`, `title`, `button_id`, `meta` (decoded from `type_params`), and `submitted_at`.
- `POST /api/links` — validates and inserts a link. Status is `'pending'` or `'published'` depending on the per-profile or global `auto_approve` setting. Contributor metadata in `meta` is stored as JSON in `type_params`.
- `POST /api/links/{id}/approve` — sets a pending link's status to `'published'`. Returns 404 if the link is not found, not pending, or belongs to another profile.
- `DELETE /api/links/{id}` — hard-deletes a pending link. Returns 404 if the link is not found, not pending, or belongs to another profile. LinkStack does not support soft deletes on the `links` table.

### Feature 2 — Link Moderation Queue

`GET /studio/moderation` lists pending links for the authenticated user joined to `buttons` for display. `POST /studio/moderation/{id}/approve` and `reject` update `status` with an ownership `WHERE user_id = Auth::id()` guard — cross-user changes are impossible even if the route guard were bypassed.

The view is published separately (`--tag=linkstack-shared-profiles-views`) so host apps can extend their own layout.

### Feature 3 — Provider Abstractions

`src/Providers/` contains reusable scaffolding for building provider packages (Telegram, Discord, etc.) without reinventing common patterns:

| Class | Purpose |
|---|---|
| `NotifierContract` | Interface all provider notifiers must implement: `notifyModerators(int $profileId, int $linkId, string $link, string $title): void` |
| `NotifyProvidersOfPendingLink` | Fan-out listener: iterates `ServiceProvider::registeredNotifiers()` and calls each on `PendingLinkSubmitted` |
| `AbstractWebhookController` | Template-method base: `verifySignature()` → dispatch to `handleMessage()` or `handleInteraction()` → return `200 OK` |
| `Providers\ServiceProvider` | Abstract base for provider service providers; provides `registerInteractionRoute()` for CSRF-exempt POST endpoints |
| `ProviderManager` | Eloquent model for `provider_managers`; `forProvider(string $provider)` scope, `isOwner(): bool` |
| `ProviderSetting` | Eloquent model for `provider_settings`; `settings` cast to `array`, `forProvider(string $provider)` scope |
| `AuthReplayGuard` | `isStale(int $timestamp, int $ttlSeconds): bool` — timestamp freshness check for signed payloads |

Provider packages extend `Providers\ServiceProvider`, implement `NotifierContract`, and extend `AbstractWebhookController` for their webhook endpoint.

---

## Ownership Model

LinkStack's `LinkId` middleware checks `$user->id != $link->user_id`. Provider managers are authenticated **as** the shared profile's `user_id` via `Auth::loginUsingId()`, so this check passes without any modification to LinkStack core files.

---

## Config

```php
// config/linkstack-shared-profiles.php
return [
    'auto_approve' => env('LINKSTACK_SHARED_PROFILES_AUTO_APPROVE', false),
];
```
