# Platform Authentication

Platform authentication lets a user from an external platform (a bot, a provider integration) authenticate as a shared profile manager without a traditional login form. The platform sends a cryptographically signed payload to an interaction endpoint; the provider verifies the signature and establishes a standard Laravel session as the shared profile user.

## Overview

The flow at a high level:

1. The platform sends a signed payload to a CSRF-exempt POST endpoint registered by the provider
2. The provider verifies the signature (platform-specific algorithm)
3. The provider extracts the platform user ID from the payload
4. The platform user ID is looked up in `provider_managers` to resolve a `profile_id`
5. `Auth::loginUsingId($manager->profile_id)` establishes a session as the shared profile user

The specifics of step 1–3 are provider-specific. See [Adding a Provider](04-adding-a-provider.md) for how to implement signature verification in a provider package.

## CSRF Exemption

Interaction endpoints receive signed payloads without a prior page load, so no CSRF cookie is available. The platform signature on the payload serves as authentication proof in place of a CSRF token.

Provider service providers register these endpoints using `registerInteractionRoute()`, which applies `->withoutMiddleware(VerifyCsrfToken::class)` automatically:

```php
// In a provider's ServiceProvider::boot()
$this->registerInteractionRoute(
    '/my-provider/interact',
    MyWebhookController::class,
    'my-provider.interact'
);
```

## ProviderManager Lookup

The `provider_managers` table maps platform user IDs to LinkStack profile IDs. A row must exist before a platform user can authenticate:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Models\ProviderManager;

// Check if a platform user is a registered manager
$manager = ProviderManager::forProvider('my-provider')
    ->where('external_id', $platformUserId)
    ->first();

if (! $manager) {
    // Unknown user — reject
}
```

| Column | Description |
|---|---|
| `provider` | Provider slug, e.g. `'telegram'`, `'discord'` |
| `external_id` | The platform's user ID (string) |
| `profile_id` | FK to `users.id` — the LinkStack profile this manager belongs to |
| `role` | `'owner'` or `'moderator'` (default) |
| `added_by` | Platform ID of the user who added this record (nullable) |

See [Adding a Provider](04-adding-a-provider.md) for how providers populate this table.

## Signature Verification

Each provider implements its own signature verification algorithm in a subclass of `AbstractWebhookController`. The core package provides the template; the provider fills in the platform-specific check.

See [Adding a Provider](04-adding-a-provider.md) — **Webhook Controller** section.

## Replay Protection

Signed payloads typically include a timestamp to prevent replay attacks. Use `AuthReplayGuard` to check freshness:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Support\AuthReplayGuard;

if (AuthReplayGuard::isStale($payload['timestamp'], ttlSeconds: 300)) {
    // Payload is too old — reject
}
```

`isStale()` returns `true` when `(time() - $timestamp) > $ttlSeconds`. The TTL is provider-specific and should be configured in the provider package's own config.

## Session

Once the signature is verified and the manager is resolved, log in as the shared profile user:

```php
Auth::loginUsingId($manager->profile_id);
```

This establishes a standard Laravel session. All LinkStack ownership checks (which compare `$user->id` to `$link->user_id`) pass without any modification to LinkStack core files, because the manager is now authenticated as the profile user.

---

Previous: [API Link Submission](01-api-link-submission.md) · Next: [Moderation Queue](03-moderation-queue.md)
