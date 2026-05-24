<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WerdsWords\LinkStack\SharedProfiles\Http\Controllers\TelegramWebhookController;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(TelegramWebhookController::class)]
final class TelegramWebhookControllerTest extends TestCase
{
    private const WEBHOOK_SECRET = 'test-webhook-secret';

    private const BOT_TOKEN = 'test-bot-token';

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
        $app['config']->set('app.url', 'https://example.com');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('services.telegram.client_id', 'test-bot-id');
        $app['config']->set('services.telegram.client_secret', self::BOT_TOKEN);
        $app['config']->set('services.telegram.redirect', 'https://example.com/callback');

        $app['config']->set('linkstack-shared-profiles.bot_token', self::BOT_TOKEN);
        $app['config']->set('linkstack-shared-profiles.webhook_secret', self::WEBHOOK_SECRET);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'login')->name('login');
        $router->get('/studio/index', fn () => 'studio')->name('studio.index');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('telegram_bot_token')->nullable();
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
            Schema::dropIfExists('telegram_managers');
            Schema::dropIfExists('users');
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $email = 'test@example.com'): User
    {
        return User::create(['name' => 'Test User', 'email' => $email]);
    }

    private function createManager(int $profileId, string $telegramId): void
    {
        DB::table('telegram_managers')->insert([
            'telegram_id' => $telegramId,
            'profile_id' => $profileId,
            'role' => 'moderator',
        ]);
    }

    private function createLink(int $profileId, string $status = 'pending'): int
    {
        return (int) DB::table('links')->insertGetId([
            'user_id' => $profileId,
            'link' => 'https://example.com',
            'title' => 'Test Link',
            'button_id' => 1,
            'type' => 'predefined',
            'status' => $status,
            'order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function postWebhook(array $payload, string $secret = self::WEBHOOK_SECRET): TestResponse
    {
        return $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $secret])
            ->postJson('/telegram/webhook', $payload);
    }

    private function messageUpdate(string $text, string $telegramId = '12345678'): array
    {
        return [
            'message' => [
                'message_id' => 1,
                'from' => ['id' => (int) $telegramId, 'username' => 'testuser'],
                'chat' => ['id' => (int) $telegramId, 'type' => 'private'],
                'text' => $text,
            ],
        ];
    }

    private function callbackUpdate(string $callbackData, string $telegramId = '12345678', int $messageId = 99): array
    {
        return [
            'callback_query' => [
                'id' => 'callback-query-id',
                'from' => ['id' => (int) $telegramId, 'username' => 'testuser'],
                'message' => [
                    'message_id' => $messageId,
                    'chat' => ['id' => (int) $telegramId],
                ],
                'data' => $callbackData,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // handle() — secret verification
    // -------------------------------------------------------------------------

    public function testMissingSecretHeaderReturns403(): void
    {
        $this->postJson('/telegram/webhook', [])->assertStatus(403);
    }

    public function testWrongSecretHeaderReturns403(): void
    {
        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])
            ->postJson('/telegram/webhook', [])
            ->assertStatus(403);
    }

    public function testValidSecretWithUnknownUpdateTypeReturns200(): void
    {
        $this->postWebhook([])->assertStatus(200);
    }
}
