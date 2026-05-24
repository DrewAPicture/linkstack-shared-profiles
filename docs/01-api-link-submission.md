# API Link Submission

The API allows an external client (a platform bot, a provider integration, or any HTTP client) to submit, list, approve, and deny links on behalf of a shared profile — without touching the LinkStack studio UI.

All endpoints require a bearer token and are throttled at 60 requests per minute.

## API Token

Each profile that will accept API submissions needs an `api_token` set on its `users` row. Tokens are stored as SHA-256 hashes; only the raw value is ever sent over the wire. Store tokens via `setApiToken()` so the hashing is handled consistently:

```php
// Generate and store a token (e.g. in tinker or a setup command)
$raw = \Illuminate\Support\Str::random(60);
$user = \App\Models\User::findOrFail($profileId);
$user->setApiToken($raw);
$user->save();
// Give $raw to the client — it cannot be recovered from the stored hash
```

The client sends the raw token:

```
Authorization: Bearer <raw_token>
```

Token generation is the host application's responsibility.

## Endpoints

### List pending links

```
GET /api/links
Authorization: Bearer <token>
```

Returns all pending links for the authenticated profile.

```json
{
    "data": [
        {
            "id": 1,
            "link": "https://example.com",
            "title": "Example",
            "button_id": 1,
            "meta": { "source": "my-bot" },
            "submitted_at": "2026-05-24 00:00:00"
        }
    ]
}
```

### Submit a link

```
POST /api/links
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
    "link": "https://example.com",
    "title": "Example",
    "button_id": 1,
    "meta": { "source": "my-bot" }
}
```

| Field | Required | Notes |
|---|---|---|
| `link` | Yes | URL, max 2048 characters |
| `title` | Yes | Max 255 characters |
| `button_id` | Yes | Must exist in the `buttons` table |
| `meta` | No | Arbitrary key/value object; stored as JSON in `type_params` |

**Response:** `201 Created` — `{"status": "queued"}`

If auto-approve is enabled for this profile, the link lands as `published` immediately and no event fires. See [auto_approve](#auto_approve) below.

### Approve a link

```
POST /api/links/{id}/approve
Authorization: Bearer <token>
```

Sets a pending link's status to `published`. Returns `404` if the link is not found, not pending, or belongs to a different profile.

**Response:** `200 OK` — `{"status": "approved"}`

### Deny a link

```
DELETE /api/links/{id}
Authorization: Bearer <token>
```

Permanently deletes a pending link. Returns `404` if the link is not found, not pending, or belongs to a different profile. LinkStack does not support soft deletes on links.

**Response:** `200 OK` — `{"status": "denied"}`

## auto_approve

By default, submitted links land with `status = pending` and enter the moderation queue. Set `auto_approve = true` in the package config to publish them immediately instead:

```
LINKSTACK_SHARED_PROFILES_AUTO_APPROVE=true
```

This can be overridden per profile via the `users.auto_approve` column. The per-profile value takes precedence over the global config when set (including `false` to force queuing even if the global setting enables auto-approve).

## PendingLinkSubmitted Event

When a link is stored with `status = pending`, the package fires a `PendingLinkSubmitted` event carrying the `profileId`, `linkId`, `link` URL, and `title`. Registered provider notifiers receive this event and use it to alert moderators via their platform (Telegram message, Discord notification, etc.).

See [Adding a Provider](04-adding-a-provider.md) for how to implement a notifier.

---

Next: [Platform Authentication](02-platform-authentication.md)
