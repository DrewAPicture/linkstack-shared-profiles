<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use WerdsWords\LinkStack\SharedProfiles\Providers\Support\ProviderSettings;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

#[CoversClass(ProviderSettings::class)]
final class ProviderSettingsTest extends TestCase
{
    private int $profileId;

    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('provider_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_id');
            $table->string('provider');
            $table->json('settings');
            $table->primary(['profile_id', 'provider']);
            $table->foreign('profile_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('provider_settings');
            Schema::dropIfExists('users');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    #[CoversMethod(ProviderSettings::class, 'get')]
    public function testGetReturnsDefaultWhenNoRowExists(): void
    {
        $result = ProviderSettings::get('telegram', $this->profileId, 'bot_token', 'fallback');

        $this->assertSame('fallback', $result);
    }

    #[CoversMethod(ProviderSettings::class, 'get')]
    public function testGetReturnsNullDefaultWhenNotSpecified(): void
    {
        $result = ProviderSettings::get('telegram', $this->profileId, 'bot_token');

        $this->assertNull($result);
    }

    #[CoversMethod(ProviderSettings::class, 'get')]
    public function testGetReturnsStoredValue(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'abc123']);

        $result = ProviderSettings::get('telegram', $this->profileId, 'bot_token');

        $this->assertSame('abc123', $result);
    }

    #[CoversMethod(ProviderSettings::class, 'get')]
    public function testGetReturnsDefaultWhenKeyAbsentFromSettings(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['other_key' => 'value']);

        $result = ProviderSettings::get('telegram', $this->profileId, 'bot_token', 'fallback');

        $this->assertSame('fallback', $result);
    }

    #[CoversMethod(ProviderSettings::class, 'get')]
    public function testGetIsScopedToProvider(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'tg-token']);
        ProviderSettings::set('discord', $this->profileId, ['bot_token' => 'dc-token']);

        $this->assertSame('tg-token', ProviderSettings::get('telegram', $this->profileId, 'bot_token'));
        $this->assertSame('dc-token', ProviderSettings::get('discord', $this->profileId, 'bot_token'));
    }

    // -------------------------------------------------------------------------
    // set()
    // -------------------------------------------------------------------------

    #[CoversMethod(ProviderSettings::class, 'set')]
    public function testSetCreatesRowWhenNoneExists(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'abc123']);

        $count = DB::table('provider_settings')
            ->where('profile_id', $this->profileId)
            ->where('provider', 'telegram')
            ->count();

        $this->assertSame(1, $count);
    }

    #[CoversMethod(ProviderSettings::class, 'set')]
    public function testSetMergesWithExistingSettings(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'abc123']);
        ProviderSettings::set('telegram', $this->profileId, ['other_key' => 'xyz']);

        $this->assertSame('abc123', ProviderSettings::get('telegram', $this->profileId, 'bot_token'));
        $this->assertSame('xyz', ProviderSettings::get('telegram', $this->profileId, 'other_key'));
    }

    #[CoversMethod(ProviderSettings::class, 'set')]
    public function testSetOverwritesExistingKeyOnMerge(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'old']);
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'new']);

        $this->assertSame('new', ProviderSettings::get('telegram', $this->profileId, 'bot_token'));
    }

    #[CoversMethod(ProviderSettings::class, 'set')]
    public function testSetDoesNotWriteAcrossProviders(): void
    {
        ProviderSettings::set('telegram', $this->profileId, ['bot_token' => 'tg-token']);

        $result = ProviderSettings::get('discord', $this->profileId, 'bot_token');

        $this->assertNull($result);
    }
}
