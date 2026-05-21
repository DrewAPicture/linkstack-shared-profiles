# Shared Profiles for LinkStack

A Laravel package that extends [LinkStack](https://github.com/LinkStackOrg/LinkStack) with three features, without modifying any core LinkStack files:

1. **API link submission** — `POST /api/links` with bearer token auth; submitted links land in a moderation queue.
2. **Telegram multi-user management** — multiple Telegram accounts can authenticate as one shared LinkStack profile.
3. **Link moderation queue** — approve or reject pending links from the studio.

---

## Requirements

- PHP 8.2+
- LinkStack (Laravel 10)
- A Telegram bot (required for Telegram auth features)

---

## Installation

```bash
composer require werdswords/linkstack-shared-profiles
php artisan migrate
```

Auto-discovery registers the service provider. No changes to `config/app.php` are needed.

---

## Feature 1 — API Link Submission

Any external tool can submit links to a profile by POSTing to `/api/links` with a bearer token.

### Setting an API token

Generate a token for a profile via Artisan tinker:

```bash
php artisan tinker
>>> \App\Models\User::find($profileId)->update(['api_token' => \Illuminate\Support\Str::random(80)]);
```

### Listing pending links

```
GET /api/links
Authorization: Bearer <api_token>
```

Returns pending links for the authenticated profile:

```json
{
    "data": [
        {
            "id": 1,
            "link": "https://example.com",
            "title": "Example",
            "button_id": 1,
            "meta": { "source": "my-app" },
            "submitted_at": "2024-01-01 00:00:00"
        }
    ]
}
```

### Approving a link

```
POST /api/links/{id}/approve
Authorization: Bearer <api_token>
```

Sets the link's status to `published`. Returns `404` if the link is not found, not in `pending` status, or belongs to a different profile.

### Denying a link

```
DELETE /api/links/{id}
Authorization: Bearer <api_token>
```

Permanently deletes the link. Returns `404` if the link is not found, not in `pending` status, or belongs to a different profile. LinkStack does not support soft deletes on links.

### Submitting a link

```
POST /api/links
Authorization: Bearer <api_token>
Content-Type: application/json
```

```json
{
    "link": "https://example.com",
    "title": "Example",
    "button_id": 1,
    "meta": { "source": "my-app" }
}
```

| Field | Required | Description |
|---|---|---|
| `link` | Yes | URL (max 2048 characters) |
| `title` | Yes | Display title (max 255 characters) |
| `button_id` | Yes | ID of an existing button type |
| `meta` | No | Arbitrary key/value object stored as JSON in `type_params` |

Submitted links arrive with `status = pending` unless auto-approve is enabled. This can be set globally via `LINKSTACK_SHARED_PROFILES_AUTO_APPROVE=true`, or on a per-profile basis:

```bash
php artisan tinker
>>> \App\Models\User::find($profileId)->update(['auto_approve' => true]);
```

The per-profile value takes precedence over the global config when set. Setting it to `false` will queue links even if the global config enables auto-approve.

**Response:** `201 Created` with `{"status": "queued"}`.

---

## Feature 2 — Telegram Multi-User Management

Multiple Telegram users can log in and be redirected to a shared LinkStack profile. Two authentication flows are supported.

### Bot token configuration

Each profile can have its own bot token stored in the database. If no per-profile token is set, the package falls back to the global `TELEGRAM_BOT_TOKEN` environment variable.

Set a per-profile token via tinker:

```bash
php artisan tinker
>>> \App\Models\User::find($profileId)->update(['telegram_bot_token' => 'your-bot-token']);
```

Or set the global fallback in `.env`:

```
TELEGRAM_BOT_TOKEN=your-bot-token
```

### Adding Telegram managers

A Telegram manager is a Telegram user who is permitted to log in as a given profile. Add one via tinker:

```bash
php artisan tinker
>>> \DB::table('telegram_managers')->insert([
...     'telegram_id' => '123456789',   // Telegram user ID (string)
...     'profile_id'  => 1,             // users.id of the LinkStack profile
...     'role'        => 'moderator',   // 'owner' or 'moderator'
... ]);
```

### Approach A — Telegram Login Widget (browser-based)

Direct users to:

```
/telegram-auth/{profileId}
```

where `{profileId}` is the `users.id` of the target LinkStack profile. The package handles the OAuth redirect and callback at `/telegram-auth/{profileId}/callback`.

Configure your Telegram bot to allow the Login Widget for your domain, then set the Socialite driver config in `config/services.php`:

```php
'telegram' => [
    'client_id'     => env('TELEGRAM_BOT_ID'),   // numeric bot ID (without the bot_ prefix)
    'client_secret' => env('TELEGRAM_BOT_TOKEN'), // bot token (used as global fallback)
    'redirect'      => '',                         // set dynamically per-profile; leave blank
],
```

### Approach B — Telegram Mini App (initData)

A Telegram Mini App can authenticate by posting `Telegram.WebApp.initData` directly:

```
POST /telegram-login
Content-Type: application/json
```

```json
{
    "init_data": "<Telegram.WebApp.initData value>"
}
```

The controller verifies the HMAC signature and checks that the `auth_date` is within the last 5 minutes (configurable via `auth_date_ttl`). On success it returns:

```json
{"redirect": "/studio/index"}
```

The Mini App should then navigate to the returned URL.

---

## Feature 3 — Link Moderation Queue

Authenticated profile users can review pending links at:

```
/studio/moderation
```

Approve or reject individual links from there. Only links belonging to the authenticated user are shown.

### Customising the view

Publish the view to override the default layout:

```bash
php artisan vendor:publish --tag=linkstack-shared-profiles-views
```

The file will be published to `resources/views/vendor/linkstack-shared-profiles/moderation/index.blade.php`.

---

## Configuration

Publish the config file if you want to modify defaults:

```bash
php artisan vendor:publish --tag=linkstack-shared-profiles
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `bot_token` | `TELEGRAM_BOT_TOKEN` | — | Global fallback bot token for Telegram HMAC verification |
| `auto_approve` | `LINKSTACK_SHARED_PROFILES_AUTO_APPROVE` | `false` | Publish API-submitted links immediately instead of queuing them. Can be overridden per profile via `users.auto_approve`. |
| `auth_date_ttl` | — | `300` | Seconds before a Telegram Mini App `initData` payload is considered stale |

---

## License

MIT
