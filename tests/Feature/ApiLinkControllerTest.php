<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WerdsWords\LinkStack\SharedProfiles\Http\Controllers\ApiLinkController;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\Link;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(ApiLinkController::class)]
final class ApiLinkControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            ServiceProvider::class,
        ];
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

        $app['config']->set('services.telegram.client_id', 'test-bot-id');
        $app['config']->set('services.telegram.client_secret', 'test-bot-token');
        $app['config']->set('services.telegram.redirect', 'https://example.com/callback');

        $app['config']->set('linkstack-shared-profiles.auto_approve', false);
        $app['config']->set('linkstack-shared-profiles.bot_token', 'test-token');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('api_token', 80)->unique()->nullable();
            $table->timestamps();
        });

        Schema::create('buttons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('link', 2048);
            $table->string('title');
            $table->unsignedBigInteger('button_id');
            $table->string('type')->default('predefined');
            $table->text('type_params')->nullable();
            $table->enum('status', ['pending', 'published', 'rejected'])->default('published');
            $table->integer('order')->default(999);
            $table->string('up_link')->nullable();
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('links');
            Schema::dropIfExists('buttons');
            Schema::dropIfExists('users');
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithToken(): array
    {
        $token = Str::random(60);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'api_token' => $token,
        ]);

        return [$user, $token];
    }

    private function buttonId(): int
    {
        return (int) DB::table('buttons')->insertGetId([
            'name' => 'Test Button',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validPayload(int $buttonId): array
    {
        return [
            'link' => 'https://example.com',
            'title' => 'My Link',
            'button_id' => $buttonId,
        ];
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function testMissingBearerTokenReturns401(): void
    {
        $this->postJson('/api/links', ['link' => 'https://example.com', 'title' => 'T', 'button_id' => 1])
            ->assertStatus(401);
    }

    public function testInvalidBearerTokenReturns401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
            ->postJson('/api/links', ['link' => 'https://example.com', 'title' => 'T', 'button_id' => 1])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Successful submission
    // -------------------------------------------------------------------------

    public function testValidRequestReturns201WithQueuedStatus(): void
    {
        [, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)
            ->postJson('/api/links', $this->validPayload($buttonId))
            ->assertStatus(201)
            ->assertJson(['status' => 'queued']);
    }

    public function testValidRequestCreatesLinkWithPendingStatus(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', $this->validPayload($buttonId));

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'link' => 'https://example.com',
            'title' => 'My Link',
            'button_id' => $buttonId,
            'type' => 'predefined',
            'status' => 'pending',
            'order' => 999,
        ]);
    }

    public function testAutoApproveCreatesPublishedLink(): void
    {
        $this->app['config']->set('linkstack-shared-profiles.auto_approve', true);

        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', $this->validPayload($buttonId));

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'status' => 'published',
        ]);
    }

    public function testMetaIsStoredAsJsonInTypeParams(): void
    {
        [, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', [
            ...$this->validPayload($buttonId),
            'meta' => ['contributor' => 'Jane', 'telegram_id' => '123456'],
        ]);

        $typeParams = Link::first()->type_params;
        $this->assertSame(
            ['contributor' => 'Jane', 'telegram_id' => '123456'],
            json_decode($typeParams, true)
        );
    }

    public function testLinkWithoutMetaHasNullTypeParams(): void
    {
        [, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', $this->validPayload($buttonId));

        $this->assertNull(Link::first()->type_params);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    #[DataProvider('provideInvalidPayloads')]
    public function testInvalidPayloadReturns422(array $payload, string $invalidField): void
    {
        [, $token] = $this->userWithToken();
        $this->buttonId();

        $this->withToken($token)
            ->postJson('/api/links', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$invalidField]);
    }

    public static function provideInvalidPayloads(): \Generator
    {
        yield 'invalid url' => [
            ['link' => 'not-a-url', 'title' => 'T', 'button_id' => 1],
            'link',
        ];

        yield 'missing title' => [
            ['link' => 'https://example.com', 'button_id' => 1],
            'title',
        ];

        yield 'non-existent button_id' => [
            ['link' => 'https://example.com', 'title' => 'T', 'button_id' => 999],
            'button_id',
        ];

        yield 'meta is not an array' => [
            ['link' => 'https://example.com', 'title' => 'T', 'button_id' => 1, 'meta' => 'string'],
            'meta',
        ];
    }
}
