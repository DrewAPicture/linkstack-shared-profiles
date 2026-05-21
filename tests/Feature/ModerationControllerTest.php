<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WerdsWords\LinkStack\SharedProfiles\Http\Controllers\ModerationController;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Middleware\AllowAll;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(ModerationController::class)]
final class ModerationControllerTest extends TestCase
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
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('services.telegram.client_id', 'test-bot-id');
        $app['config']->set('services.telegram.client_secret', 'test-secret');
        $app['config']->set('services.telegram.redirect', 'https://example.com/callback');

        $app['config']->set('linkstack-shared-profiles.bot_token', 'test-token');
        $app['config']->set('linkstack-shared-profiles.auto_approve', false);

        // Stub LinkStack's 'blocked' middleware so studio routes resolve in tests
        $app['router']->aliasMiddleware('blocked', AllowAll::class);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'login')->name('login');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
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

    private function createUser(string $email = 'user@example.com'): User
    {
        return User::create(['name' => 'Test User', 'email' => $email]);
    }

    private function createButton(): int
    {
        return (int) DB::table('buttons')->insertGetId([
            'name' => 'Test Button',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLink(int $userId, int $buttonId, string $status = 'pending', string $title = 'My Link'): int
    {
        return (int) DB::table('links')->insertGetId([
            'user_id' => $userId,
            'link' => 'https://example.com',
            'title' => $title,
            'button_id' => $buttonId,
            'type' => 'predefined',
            'status' => $status,
            'order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // index() — authentication
    // -------------------------------------------------------------------------

    public function testIndexRequiresAuthentication(): void
    {
        $this->get('/studio/moderation')
            ->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // index() — pending link display
    // -------------------------------------------------------------------------

    public function testIndexReturnsPendingLinksForAuthUser(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $this->createLink($user->id, $buttonId, 'pending', 'Pending Link');

        $this->actingAs($user)
            ->get('/studio/moderation')
            ->assertStatus(200)
            ->assertSee('Pending Link');
    }

    public function testIndexExcludesPublishedLinks(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $this->createLink($user->id, $buttonId, 'published', 'Published Link');

        $this->actingAs($user)
            ->get('/studio/moderation')
            ->assertStatus(200)
            ->assertDontSee('Published Link');
    }

    public function testIndexExcludesRejectedLinks(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $this->createLink($user->id, $buttonId, 'rejected', 'Rejected Link');

        $this->actingAs($user)
            ->get('/studio/moderation')
            ->assertStatus(200)
            ->assertDontSee('Rejected Link');
    }

    public function testIndexExcludesOtherUsersLinks(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser('other@example.com');
        $buttonId = $this->createButton();
        $this->createLink($other->id, $buttonId, 'pending', 'Other User Link');

        $this->actingAs($owner)
            ->get('/studio/moderation')
            ->assertStatus(200)
            ->assertDontSee('Other User Link');
    }

    public function testIndexShowsEmptyStateWhenNoPendingLinks(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get('/studio/moderation')
            ->assertStatus(200)
            ->assertSee('No links are pending review.');
    }

    public function testIndexPassesLinksCollectionToView(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $this->createLink($user->id, $buttonId, 'pending');

        $this->actingAs($user)
            ->get('/studio/moderation')
            ->assertViewIs('linkstack-shared-profiles::moderation.index')
            ->assertViewHas('links', fn ($links) => $links->count() === 1);
    }

    // -------------------------------------------------------------------------
    // approve() — authentication
    // -------------------------------------------------------------------------

    public function testApproveRequiresAuthentication(): void
    {
        $this->post('/studio/moderation/1/approve')
            ->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // approve() — status change
    // -------------------------------------------------------------------------

    public function testApproveSetsPendingLinkToPublished(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $linkId = $this->createLink($user->id, $buttonId, 'pending');

        $this->actingAs($user)
            ->from('/studio/moderation')
            ->post("/studio/moderation/{$linkId}/approve")
            ->assertRedirect('/studio/moderation');

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'published']);
    }

    public function testApproveDoesNotChangeAnotherUsersLink(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser('other@example.com');
        $buttonId = $this->createButton();
        $linkId = $this->createLink($other->id, $buttonId, 'pending');

        $this->actingAs($owner)
            ->from('/studio/moderation')
            ->post("/studio/moderation/{$linkId}/approve");

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'pending']);
    }

    // -------------------------------------------------------------------------
    // reject() — authentication
    // -------------------------------------------------------------------------

    public function testRejectRequiresAuthentication(): void
    {
        $this->post('/studio/moderation/1/reject')
            ->assertRedirect('/login');
    }

    // -------------------------------------------------------------------------
    // reject() — status change
    // -------------------------------------------------------------------------

    public function testRejectSetsPendingLinkToRejected(): void
    {
        $user = $this->createUser();
        $buttonId = $this->createButton();
        $linkId = $this->createLink($user->id, $buttonId, 'pending');

        $this->actingAs($user)
            ->from('/studio/moderation')
            ->post("/studio/moderation/{$linkId}/reject")
            ->assertRedirect('/studio/moderation');

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'rejected']);
    }

    public function testRejectDoesNotChangeAnotherUsersLink(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser('other@example.com');
        $buttonId = $this->createButton();
        $linkId = $this->createLink($other->id, $buttonId, 'pending');

        $this->actingAs($owner)
            ->from('/studio/moderation')
            ->post("/studio/moderation/{$linkId}/reject");

        $this->assertDatabaseHas('links', ['id' => $linkId, 'status' => 'pending']);
    }
}
