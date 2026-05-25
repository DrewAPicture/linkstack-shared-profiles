# Testing

## Stack

| Tool | Version | Purpose |
|---|---|---|
| PHPUnit | ^10.5 | Test runner |
| Orchestra Testbench | ^8.0 | Laravel application bootstrapping for package tests |
| Larastan | ^2.0 | PHPStan extension for Laravel type inference |
| PHPStan | ^1.12 | Static analysis at **max** level |
| Pint | ^1.0 | Code style (Laravel preset) |

Run everything:
```bash
composer test      # phpunit
composer analyse   # phpstan --memory-limit must be raised to 512M; the default 128M crashes
composer format    # pint (writes); pint --test (dry-run)
composer artisan   # vendor/bin/testbench — artisan stand-in for the package context
composer ci        # test + analyse + format in sequence
```

PHPStan memory: the default 128 MB limit causes a worker crash. Always run with `--memory-limit=512M`:
```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

---

## Test Structure

```
tests/
  Feature/
    ApiLinkControllerTest.php
    ModerationControllerTest.php
    Providers/Listeners/
      PendingLinkSubmittedTest.php
  Unit/
    ServiceProviderTest.php
    ServiceProviderBootTest.php  boot-time HasApiTokenContract check tests
    Concerns/
      HasApiTokenTest.php
    Providers/
      Controllers/
        AbstractWebhookControllerTest.php
      Listeners/
        NotifyProvidersOfPendingLinkTest.php
      Models/
        ProviderManagerTest.php
        ProviderSettingTest.php
      ServiceProviderTest.php
      Support/
        AuthReplayGuardTest.php
  Support/
    Models/
      User.php    extends Authenticatable; implements HasApiTokenContract via HasApiToken
      Link.php    extends Illuminate\Database\Eloquent\Model
    Middleware/
      AllowAll.php   no-op stub registered as 'blocked' alias in Testbench
```

---

## Test Setup Pattern

Each feature test class extends `Orchestra\Testbench\TestCase` and implements these hooks:

```php
protected function getPackageProviders($app): array
{
    return [ServiceProvider::class];
}

protected function defineEnvironment($app): void
{
    // Required when routes use web middleware (StartSession → encryption)
    $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    // In-memory SQLite for speed
    $app['config']->set('database.default', 'testing');
    $app['config']->set('database.connections.testing', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]);

    // Point auth at the test support User model
    $app['config']->set('auth.providers.users.model', User::class);

    // Stub LinkStack's 'blocked' middleware (not registered in Testbench)
    $app['router']->aliasMiddleware('blocked', AllowAll::class);
}

protected function defineRoutes($router): void
{
    // Named routes the package redirects to in error cases
    $router->get('/login', fn () => 'login')->name('login');
    $router->get('/studio/index', fn () => 'studio')->name('studio.index');
}

protected function defineDatabaseMigrations(): void
{
    // Each test class defines its own in-memory schema inline.
    // Do NOT call the package migrations here — define the schema directly
    // to keep tests self-contained and avoid migration ordering issues.
    Schema::create('users', function (Blueprint $table) { ... });
    // ...
    $this->beforeApplicationDestroyed(function () {
        Schema::dropIfExists('links');
        // drop in reverse dependency order
    });
}
```

---

## Support Models

### `tests/Support/Models/User.php`

Extends `Illuminate\Foundation\Auth\User` (which implements `Authenticatable`), **not** `Illuminate\Database\Eloquent\Model`. This is required for `Auth::loginUsingId()` to work — the session guard calls `login()` which expects an `Authenticatable` instance.

If the model only extends `Model`, `Auth::loginUsingId()` will still resolve the model but `login()` will type-error at runtime.

This model also implements `HasApiTokenContract` via the `HasApiToken` trait. Any new test support User model must do the same, or the core `ServiceProvider::boot()` will throw a `RuntimeException` before tests can run.

### `tests/Support/Middleware/AllowAll.php`

A no-op middleware registered under the `blocked` alias in `defineEnvironment`. Without this, any route that declares `->middleware('blocked')` will throw a `RuntimeException` when dispatched in Testbench (the alias is not in scope).

---

## Key Gotchas

### `app.key` must be set for web middleware routes

Routes that carry `->middleware('web')` trigger `StartSession`, which requires the encrypter, which requires `app.key`. Without it, every request to those routes throws `MissingAppKeyException`. Set it in `defineEnvironment`:

```php
$app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
```

### Package routes don't inherit the host's web middleware group

`loadRoutesFrom()` registers routes directly — it bypasses the host's `RouteServiceProvider`. Routes that need sessions (for `Auth::loginUsingId()` or `$request->session()`) must declare `->middleware('web')` explicitly on the route or group.

### `$request->session()` requires `StartSession` to have run

The session store **is** bound in the container, but `$request->session()` calls `$request->getSession()` which throws `RuntimeException: Session store not set on request` if `StartSession` middleware hasn't run and called `$request->setLaravelSession()`. The session guard (`Auth::loginUsingId()`) also stores the user ID in the session — same requirement.

### CSRF on interaction endpoints

Provider interaction routes (webhook callbacks, initData endpoints) receive signed payloads without a prior page load, so no CSRF cookie is available. Use `registerInteractionRoute()` from `Providers\ServiceProvider`, which applies `->withoutMiddleware(VerifyCsrfToken::class)` automatically. In PHPUnit, `VerifyCsrfToken::runningUnitTests()` returns true and auto-bypasses verification — no test-specific workaround needed.

### `refreshNameLookups()` after manual `boot()` calls

When a test calls `$provider->boot()` directly (rather than letting Testbench boot it via `getPackageProviders()`), route name lookups via `getByName()` return null until the lookup table is rebuilt:

```php
$provider->boot();
$this->app['router']->getRoutes()->refreshNameLookups();

$route = $this->app['router']->getRoutes()->getByName('my.route');
```

### `auth.providers.users.model` must be set in every Testbench test that boots the core ServiceProvider

`ServiceProvider::boot()` checks that the configured auth model implements `HasApiTokenContract` and throws if it doesn't. Any test class that registers the core `ServiceProvider` in `getPackageProviders()` must set:

```php
$app['config']->set('auth.providers.users.model', User::class);
```

in `defineEnvironment()`. Without it, the default (`App\Models\User`) fails the check and the test class never initialises.

### Notifier registry isolation

`ServiceProvider::$notifiers` is a static array — it persists across tests in the same process. Tests that call `ServiceProvider::registerNotifier()` must flush afterwards to prevent bleed:

```php
protected function tearDown(): void
{
    ServiceProvider::flushNotifiers();
    parent::tearDown();
}
```

Or register the flush via `$this->beforeApplicationDestroyed(fn() => ServiceProvider::flushNotifiers())`.

### PHPStan max level — type narrowing

Several patterns trip PHPStan at max level:

| Pattern | Problem | Fix |
|---|---|---|
| `(string) config('key')` | Cannot cast `mixed` to `string` | `/** @var string $x */` docblock |
| `(int) config('key')` | Cannot cast `mixed` to `int` | `/** @var int $x */` docblock |
| `$model->value('column')` returning `mixed` | Return type is `mixed` | `/** @var string|null $x */` docblock before use |

---

## Authentication in Feature Tests

For routes behind `auth` middleware, use `actingAs()` — it bypasses the actual login flow and sets the authenticated user directly:

```php
$this->actingAs($user)->get('/studio/moderation')->assertStatus(200);
```

For testing that `Auth::loginUsingId()` correctly sets the session, use `assertAuthenticated()` or `assertAuthenticatedAs($user)` after the request.

For testing redirects to the referrer (approve/reject back()), set the referrer with `from()`:

```php
$this->actingAs($user)
    ->from('/studio/moderation')
    ->post("/studio/moderation/{$id}/approve")
    ->assertRedirect('/studio/moderation');
```
