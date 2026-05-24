<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use SensitiveParameter;
use WerdsWords\LinkStack\SharedProfiles\Concerns\HasApiToken;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(HasApiToken::class)]
final class HasApiTokenTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('api_token', 64)->unique()->nullable();
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('users');
        });
    }

    public function testSetApiTokenStoresHashedValue(): void
    {
        $user = new User;
        $user->setApiToken('my-raw-token');

        $this->assertSame(hash('sha256', 'my-raw-token'), $user->api_token);
    }

    public function testSetApiTokenDoesNotStoreRawValue(): void
    {
        $user = new User;
        $user->setApiToken('my-raw-token');

        $this->assertNotSame('my-raw-token', $user->api_token);
    }

    public function testSetApiTokenHasSensitiveParameter(): void
    {
        $reflection = new ReflectionMethod(HasApiToken::class, 'setApiToken');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $attrs = $params[0]->getAttributes(SensitiveParameter::class);
        $this->assertNotEmpty($attrs, 'setApiToken $rawToken must carry #[SensitiveParameter]');
    }

    public function testScopeForTokenBuildsQueryWithHashedToken(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->setApiToken('my-raw-token');
        $user->save();

        $found = User::forToken('my-raw-token')->first();

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function testScopeForTokenDoesNotMatchWrongToken(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        $user->setApiToken('correct-token');
        $user->save();

        $found = User::forToken('wrong-token')->first();

        $this->assertNull($found);
    }

    public function testScopeForTokenHasSensitiveParameter(): void
    {
        $reflection = new ReflectionMethod(HasApiToken::class, 'scopeForToken');
        $params = $reflection->getParameters();

        $rawTokenParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'rawToken') {
                $rawTokenParam = $param;
                break;
            }
        }

        $this->assertNotNull($rawTokenParam, 'scopeForToken must have a $rawToken parameter');
        $attrs = $rawTokenParam->getAttributes(SensitiveParameter::class);
        $this->assertNotEmpty($attrs, 'scopeForToken $rawToken must carry #[SensitiveParameter]');
    }
}
