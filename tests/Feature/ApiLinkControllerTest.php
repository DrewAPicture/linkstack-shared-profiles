<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature;

use Generator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WerdsWords\LinkStack\SharedProfiles\Events\PendingLinkSubmitted;
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
            $table->string('api_token', 64)->unique()->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->boolean('auto_approve')->nullable();
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

        Schema::create('telegram_managers', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id')->unique();
            $table->unsignedBigInteger('profile_id');
            $table->foreign('profile_id')->references('id')->on('users')->onDelete('cascade');
            $table->enum('role', ['owner', 'moderator'])->default('moderator');
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('telegram_managers');
            Schema::dropIfExists('links');
            Schema::dropIfExists('buttons');
            Schema::dropIfExists('users');
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function userWithToken(?bool $autoApprove = null): array
    {
        $token = Str::random(60);
        $user = User::create(array_filter([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'api_token' => hash('sha256', $token),
            'auto_approve' => $autoApprove,
        ], fn ($v) => $v !== null));

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

    private function createManager(int $profileId, string $telegramId): void
    {
        DB::table('telegram_managers')->insert([
            'telegram_id' => $telegramId,
            'profile_id' => $profileId,
            'role' => 'moderator',
        ]);
    }

    private function createLink(
        int $userId,
        int $buttonId,
        string $status = 'pending',
        string $link = 'https://example.com',
        string $title = 'My Link',
        ?array $meta = null,
    ): int {
        return (int) DB::table('links')->insertGetId([
            'user_id' => $userId,
            'link' => $link,
            'title' => $title,
            'button_id' => $buttonId,
            'type' => 'predefined',
            'type_params' => $meta !== null ? json_encode($meta) : null,
            'status' => $status,
            'order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // index() — authentication
    // -------------------------------------------------------------------------

    public function testIndexMissingBearerTokenReturns401(): void
    {
        $this->getJson('/api/links')->assertStatus(401);
    }

    public function testIndexInvalidBearerTokenReturns401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer invalid-token'])
            ->getJson('/api/links')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // index() — results
    // -------------------------------------------------------------------------

    public function testIndexReturnsPendingLinksForAuthenticatedProfile(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $this->createLink($user->id, $buttonId, 'pending', 'https://example.com', 'Pending Link');

        $this->withToken($token)
            ->getJson('/api/links')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pending Link');
    }

    public function testIndexExcludesPublishedLinks(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $this->createLink($user->id, $buttonId, 'published', 'https://example.com', 'Published Link');

        $this->withToken($token)
            ->getJson('/api/links')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function testIndexExcludesOtherProfilesLinks(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'api_token' => hash('sha256', 'other-token')]);
        $buttonId = $this->buttonId();
        $this->createLink($other->id, $buttonId, 'pending', 'https://example.com', 'Other Link');

        $this->withToken($token)
            ->getJson('/api/links')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function testIndexDecodesMetaFromTypeParams(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $this->createLink($user->id, $buttonId, 'pending', 'https://example.com', 'Link', ['source' => 'bot']);

        $this->withToken($token)
            ->getJson('/api/links')
            ->assertStatus(200)
            ->assertJsonPath('data.0.meta.source', 'bot');
    }

    public function testIndexReturnsEmptyDataWhenNoPendingLinks(): void
    {
        [, $token] = $this->userWithToken();

        $this->withToken($token)
            ->getJson('/api/links')
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    // -------------------------------------------------------------------------
    // approve() — authentication
    // -------------------------------------------------------------------------

    public function testApproveMissingBearerTokenReturns401(): void
    {
        $this->postJson('/api/links/1/approve')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // approve() — status change
    // -------------------------------------------------------------------------

    public function testApproveSetsPendingLinkToPublished(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($user->id, $buttonId, 'pending');

        $this->withToken($token)
            ->postJson("/api/links/{$linkId}/approve")
            ->assertStatus(200)
            ->assertJson(['status' => 'approved']);

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'published']);
    }

    public function testApproveReturns404ForAnotherProfilesLink(): void
    {
        [, $token] = $this->userWithToken();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'api_token' => hash('sha256', 'other-token')]);
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($other->id, $buttonId, 'pending');

        $this->withToken($token)
            ->postJson("/api/links/{$linkId}/approve")
            ->assertStatus(404);

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'pending']);
    }

    public function testApproveReturns404ForNonPendingLink(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($user->id, $buttonId, 'published');

        $this->withToken($token)
            ->postJson("/api/links/{$linkId}/approve")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // deny() — authentication
    // -------------------------------------------------------------------------

    public function testDenyMissingBearerTokenReturns401(): void
    {
        $this->deleteJson('/api/links/1')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // deny() — deletion
    // -------------------------------------------------------------------------

    public function testDenyDeletesPendingLink(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($user->id, $buttonId, 'pending');

        $this->withToken($token)
            ->deleteJson("/api/links/{$linkId}")
            ->assertStatus(200)
            ->assertJson(['status' => 'denied']);

        $this->assertDatabaseMissing('links', ['id' => $linkId]);
    }

    public function testDenyReturns404ForAnotherProfilesLink(): void
    {
        [, $token] = $this->userWithToken();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'api_token' => hash('sha256', 'other-token')]);
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($other->id, $buttonId, 'pending');

        $this->withToken($token)
            ->deleteJson("/api/links/{$linkId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('links', ['id' => $linkId]);
    }

    public function testDenyReturns404ForNonPendingLink(): void
    {
        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();
        $linkId = $this->createLink($user->id, $buttonId, 'published');

        $this->withToken($token)
            ->deleteJson("/api/links/{$linkId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('links', ['id' => $linkId]);
    }

    // -------------------------------------------------------------------------
    // store() — authentication
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

    public function testPerUserAutoApprovePublishesWhenGlobalIsFalse(): void
    {
        // global config is false (set in defineEnvironment); per-user is true
        [$user, $token] = $this->userWithToken(true);
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', $this->validPayload($buttonId));

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'status' => 'published',
        ]);
    }

    public function testPerUserAutoApproveFalseQueuesWhenGlobalIsTrue(): void
    {
        $this->app['config']->set('linkstack-shared-profiles.auto_approve', true);

        // global config is true, but per-user explicitly disables it
        [$user, $token] = $this->userWithToken(false);
        $buttonId = $this->buttonId();

        $this->withToken($token)->postJson('/api/links', $this->validPayload($buttonId));

        $this->assertDatabaseHas('links', [
            'user_id' => $user->id,
            'status' => 'pending',
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

    public static function provideInvalidPayloads(): Generator
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

    // -------------------------------------------------------------------------
    // store() — moderator notifications
    // -------------------------------------------------------------------------

    public function testStoreFiresPendingLinkSubmittedEventOnPendingLink(): void
    {
        Event::fake();

        [$user, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)
            ->postJson('/api/links', $this->validPayload($buttonId))
            ->assertStatus(201);

        Event::assertDispatched(PendingLinkSubmitted::class, function (PendingLinkSubmitted $event) use ($user) {
            return $event->profileId === $user->id
                && $event->link === 'https://example.com'
                && $event->title === 'My Link';
        });
    }

    public function testStoreDoesNotFireEventWhenAutoApproved(): void
    {
        Event::fake();
        $this->app['config']->set('linkstack-shared-profiles.auto_approve', true);

        [, $token] = $this->userWithToken();
        $buttonId = $this->buttonId();

        $this->withToken($token)
            ->postJson('/api/links', $this->validPayload($buttonId))
            ->assertStatus(201);

        Event::assertNotDispatched(PendingLinkSubmitted::class);
    }
}
