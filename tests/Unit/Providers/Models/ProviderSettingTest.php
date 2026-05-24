<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use WerdsWords\LinkStack\SharedProfiles\Providers\Models\ProviderSetting;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;

#[CoversClass(ProviderSetting::class)]
final class ProviderSettingTest extends TestCase
{
    private int $profileId;

    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
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
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('provider');
            $table->text('settings');
            $table->unique(['profile_id', 'provider']);
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
    // settings cast
    // -------------------------------------------------------------------------

    public function testSettingsAreCastToArray(): void
    {
        $setting = ProviderSetting::create([
            'profile_id' => $this->profileId,
            'provider' => 'telegram',
            'settings' => ['bot_token' => 'abc123'],
        ]);

        $this->assertIsArray($setting->settings);
        $this->assertSame('abc123', $setting->settings['bot_token']);
    }

    public function testSettingsRoundTripThroughDatabase(): void
    {
        ProviderSetting::create([
            'profile_id' => $this->profileId,
            'provider' => 'telegram',
            'settings' => ['bot_token' => 'abc123', 'other' => 'xyz'],
        ]);

        $fetched = ProviderSetting::forProvider('telegram')->where('profile_id', $this->profileId)->first();

        $this->assertNotNull($fetched);
        $this->assertSame('abc123', $fetched->settings['bot_token']);
        $this->assertSame('xyz', $fetched->settings['other']);
    }

    // -------------------------------------------------------------------------
    // scopeForProvider()
    // -------------------------------------------------------------------------

    #[CoversMethod(ProviderSetting::class, 'scopeForProvider')]
    public function testForProviderScopeFiltersResultsByProvider(): void
    {
        ProviderSetting::create(['profile_id' => $this->profileId, 'provider' => 'telegram', 'settings' => ['bot_token' => 'tg']]);
        ProviderSetting::create(['profile_id' => $this->profileId, 'provider' => 'discord', 'settings' => ['bot_token' => 'dc']]);

        $results = ProviderSetting::forProvider('telegram')->get();

        $this->assertCount(1, $results);
        $this->assertSame('telegram', $results->first()->provider);
    }

    #[CoversMethod(ProviderSetting::class, 'scopeForProvider')]
    public function testForProviderScopeReturnsEmptyCollectionWhenNoMatch(): void
    {
        $results = ProviderSetting::forProvider('discord')->get();

        $this->assertCount(0, $results);
    }
}
