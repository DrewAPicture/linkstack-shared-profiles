# Testing

## Stack

| Tool | Version | Purpose |
|---|---|---|
| PHPUnit | ^10.5 | Test runner |
| Orchestra Testbench | ^8.0 | Laravel application bootstrapping for package tests |
| Mockery | ^1.6 (transitive via Testbench) | Facade mocking (Socialite) |
| Larastan | ^2.0 | PHPStan extension for Laravel type inference |
| PHPStan | ^1.12 | Static analysis at **max** level |
| Pint | ^1.0 | Code style (Laravel preset + camelCase test methods) |

Run everything:
```bash
composer test      # phpunit
composer analyse   # phpstan --memory-limit must be raised to 512M; the default 128M crashes
composer format    # pint (writes); pint --test (dry-run)
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
    TelegramAuthControllerTest.php
    ModerationControllerTest.php
  Unit/
    ServiceProviderTest.php
    TelegramManagerTest.php
  Support/
    Models/
      User.php    extends Illuminate\Foundation\Auth\User (Authenticatable)
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
    return [
        SocialiteServiceProvider::class,  // always include for Socialite facade
        ServiceProvider::class,
    ];
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

    // Socialite config (always required; services.telegram must be set)
    $app['config']->set('services.telegram.client_id', 'test-bot-id');
    $app['config']->set('services.telegram.client_secret', 'test-secret');
    $app['config']->set('services.telegram.redirect', 'https://example.com/callback');

    $app['config']->set('linkstack-shared-profiles.bot_token', 'test-token');
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

### CSRF on `POST /telegram-login`

Telegram Mini Apps post `initData` without a prior page load, so no CSRF cookie is available. The route is marked `->withoutMiddleware([VerifyCsrfToken::class])`. In PHPUnit, `VerifyCsrfToken::runningUnitTests()` returns true and auto-bypasses verification — no test-specific workaround needed.

### PHPStan max level — type narrowing

Several patterns trip PHPStan at max level:

| Pattern | Problem | Fix |
|---|---|---|
| `(string) config('key')` | Cannot cast `mixed` to `string` | `/** @var string $x */` docblock |
| `(int) config('key')` | Cannot cast `mixed` to `int` | `/** @var int $x */` docblock |
| `parse_str($str, $params)` | `$params` values are `array\|string` | Narrow via `foreach` with `is_string()` check |
| `array_map(fn($k,$v), array_keys($p), $p)` | `array_keys` typed as `list<int\|string>` | Use a `foreach` loop to build pairs instead |
| `Socialite::driver()->redirect()` | Returns `Symfony\...\RedirectResponse` (per Contracts\Provider) not `Illuminate\...\RedirectResponse` | Declare return type as `Symfony\Component\HttpFoundation\RedirectResponse` |

### Socialite facade mocking

Use Mockery via the facade's `shouldReceive` to mock the driver chain:

```php
$mockUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
$mockUser->shouldReceive('getId')->andReturn('12345');

$mockProvider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
$mockProvider->shouldReceive('user')->andReturn($mockUser);

Socialite::shouldReceive('driver')->with('telegram')->andReturn($mockProvider);
```

Mockery cleanup is handled automatically by Orchestra Testbench's `tearDown`.

### Building valid Telegram `initData` in tests

The `initDataLogin` controller verifies an HMAC-signed `initData` string. Tests must produce a correctly signed payload using the same algorithm as the controller:

```php
private function buildValidInitData(int|string $telegramId, int $authDate = 0): string
{
    if ($authDate === 0) { $authDate = time(); }

    $params = [
        'auth_date' => (string) $authDate,
        'user' => json_encode(['id' => $telegramId, 'first_name' => 'Test']),
    ];
    ksort($params);

    $checkStr = implode("\n", array_map(
        fn ($k, $v) => "{$k}={$v}", array_keys($params), $params
    ));
    $secret = hash_hmac('sha256', 'WebAppData', self::BOT_TOKEN, true);
    $hash   = hash_hmac('sha256', $checkStr, $secret);

    return http_build_query([...$params, 'hash' => $hash]);
}
```

The bot token used here must match `linkstack-shared-profiles.bot_token` set in `defineEnvironment`.

---

## Authentication in Feature Tests

For routes behind `auth` middleware, use `actingAs()` — it bypasses the actual login flow and sets the authenticated user directly:

```php
$this->actingAs($user)->get('/studio/moderation')->assertStatus(200);
```

For testing that `Auth::loginUsingId()` correctly sets the session (Telegram auth flows), use `assertAuthenticated()` or `assertAuthenticatedAs($user)` after the request. The session guard persists the user ID in the array session store between the controller action and the assertion.

For testing redirects to the referrer (approve/reject back()), set the referrer with `from()`:

```php
$this->actingAs($user)
    ->from('/studio/moderation')
    ->post("/studio/moderation/{$id}/approve")
    ->assertRedirect('/studio/moderation');
```
