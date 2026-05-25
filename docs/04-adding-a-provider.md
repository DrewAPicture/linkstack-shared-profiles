# Adding a Provider

This guide walks through building a new provider package against the core abstractions. The core package provides the scaffolding; the provider fills in the platform-specific parts.

A provider package is responsible for:

- Authenticating platform users as shared profile managers
- Notifying managers when a pending link is submitted
- Handling platform interaction payloads (webhook callbacks, slash commands, etc.)

## User Model Requirement

The host application's `User` model (configured as `auth.providers.users.model`) must implement `ApiTokenableContract`. The core package checks this at boot time and throws a `RuntimeException` if the contract is missing — it will not silently fail at the first API request.

Apply the bundled `HasApiToken` trait, which handles SHA-256 hashing on storage and lookup:

```php
use WerdsWords\LinkStack\SharedProfiles\Concerns\HasApiToken;
use WerdsWords\LinkStack\SharedProfiles\Contracts\ApiTokenableContract;

class User extends Authenticatable implements ApiTokenableContract
{
    use HasApiToken;
}
```

If your provider defines its own `User` model, extend the host app's conforming model rather than creating a parallel implementation.

## Service Provider

Extend `Providers\ServiceProvider` instead of Laravel's base `ServiceProvider`:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\ServiceProvider as CoreProviderServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider as CoreServiceProvider;

class ServiceProvider extends CoreProviderServiceProvider
{
    public function getProviderName(): string
    {
        return 'my-provider'; // unique slug, e.g. 'telegram', 'discord'
    }

    public function boot(): void
    {
        // Register a CSRF-exempt POST endpoint for incoming platform payloads
        $this->registerInteractionRoute(
            '/my-provider/interact',
            MyWebhookController::class,
            'my-provider.interact'
        );

        // Register this provider's notifier with the core fan-out system
        CoreServiceProvider::registerNotifier(new MyProviderNotifier(...));
    }
}
```

`registerInteractionRoute()` registers a `POST` route under the `web` middleware group with CSRF verification disabled. The platform signature on the payload serves as authentication proof.

## Notifier

Implement `NotifierContract` to receive `PendingLinkSubmitted` events and alert managers via your platform:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Contracts\NotifierContract;

class MyProviderNotifier implements NotifierContract
{
    public function notifyModerators(
        int $profileId,
        int $linkId,
        string $link,
        string $title
    ): void {
        // Look up managers for this profile and send a platform notification
        $managers = ProviderManager::forProvider('my-provider')
            ->where('profile_id', $profileId)
            ->get();

        foreach ($managers as $manager) {
            // Send message via platform API
        }
    }
}
```

The core `NotifyProvidersOfPendingLink` listener calls `notifyModerators()` on every registered notifier whenever a link lands as pending.

## Webhook Controller

Extend `AbstractWebhookController` to handle incoming platform payloads:

```php
use Illuminate\Http\Request;
use WerdsWords\LinkStack\SharedProfiles\Providers\Controllers\AbstractWebhookController;

class MyWebhookController extends AbstractWebhookController
{
    protected function verifySignature(Request $request): bool
    {
        // Verify the platform-specific signature on the incoming request
        // Return false to reject with 403
    }

    protected function isMessage(array $payload): bool
    {
        // Return true if this payload represents a user message
        // Return false if it's an interaction/callback
        return array_key_exists('message', $payload);
    }

    protected function handleMessage(array $payload): void
    {
        // Handle incoming message (e.g. respond to a command)
    }

    protected function handleInteraction(array $payload): void
    {
        // Handle interaction payloads (e.g. button callbacks, slash commands)
    }
}
```

The base `handle()` method orchestrates the flow: verify signature → dispatch to `handleMessage()` or `handleInteraction()` → return `200 OK`. A failed signature check returns `403 Forbidden` without calling either handler.

## Data

### Manager mapping

Use `ProviderManager` to map platform user IDs to LinkStack profile IDs:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Models\ProviderManager;

// Look up a manager by platform user ID
$manager = ProviderManager::forProvider('my-provider')
    ->where('external_id', $platformUserId)
    ->first();

// Check role
if ($manager->isOwner()) {
    // ...
}
```

Populate `provider_managers` rows when a platform user registers or is added by an owner.

### Provider settings

Use `ProviderSetting` to store per-profile credentials and configuration:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Models\ProviderSetting;

// Read settings
$settings = ProviderSetting::forProvider('my-provider')
    ->where('profile_id', $profileId)
    ->value('settings');

$botToken = $settings['bot_token'] ?? config('my-provider.bot_token');

// Write settings
ProviderSetting::updateOrCreate(
    ['profile_id' => $profileId, 'provider' => 'my-provider'],
    ['settings' => ['bot_token' => $token]],
);
```

The `settings` column is encrypted at rest using Laravel's `encrypted:array` cast. Provider code reads and writes a plain PHP array — encryption and decryption are transparent. The encryption key is the host application's `APP_KEY`.

## Replay Protection

Use `AuthReplayGuard` to reject stale signed payloads:

```php
use WerdsWords\LinkStack\SharedProfiles\Providers\Support\AuthReplayGuard;

if (AuthReplayGuard::isStale($payload['timestamp'], ttlSeconds: 300)) {
    return false; // treat as invalid signature
}
```

`isStale()` returns `true` when `(time() - $timestamp) > $ttlSeconds`. The boundary is exclusive: a payload aged exactly `$ttlSeconds` is not yet stale.

## Testing

A few patterns specific to provider package tests:

**Flush the notifier registry between tests.** `ServiceProvider::$notifiers` is a static array that persists across the test process. Flush it in `tearDown()`:

```php
protected function tearDown(): void
{
    \WerdsWords\LinkStack\SharedProfiles\ServiceProvider::flushNotifiers();
    parent::tearDown();
}
```

**Call `refreshNameLookups()` after manually booting a provider service provider.** When a test calls `$provider->boot()` directly, route name lookups via `getByName()` return null until the lookup table is rebuilt:

```php
$provider->boot();
$this->app['router']->getRoutes()->refreshNameLookups();
```

**Set `app.key` in `defineEnvironment()`.** The `encrypted:array` cast on `ProviderSetting::$settings` requires the encrypter, which requires `app.key`:

```php
protected function defineEnvironment($app): void
{
    $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
}
```

---

Previous: [Moderation Queue](03-moderation-queue.md)
