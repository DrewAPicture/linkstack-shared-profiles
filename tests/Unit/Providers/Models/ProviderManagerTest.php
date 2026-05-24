<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Models;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use WerdsWords\LinkStack\SharedProfiles\Providers\Models\ProviderManager;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(ProviderManager::class)]
final class ProviderManagerTest extends TestCase
{
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
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('provider_managers', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->unsignedBigInteger('profile_id');
            $table->enum('role', ['owner', 'moderator'])->default('moderator');
            $table->string('added_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('profile_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'external_id']);
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('provider_managers');
            Schema::dropIfExists('users');
        });
    }

    // -------------------------------------------------------------------------
    // isOwner()
    // -------------------------------------------------------------------------

    #[CoversMethod(ProviderManager::class, 'isOwner')]
    public function testIsOwnerReturnsTrueForOwnerRole(): void
    {
        $manager = new ProviderManager(['role' => 'owner']);

        $this->assertTrue($manager->isOwner());
    }

    #[CoversMethod(ProviderManager::class, 'isOwner')]
    public function testIsOwnerReturnsFalseForModeratorRole(): void
    {
        $manager = new ProviderManager(['role' => 'moderator']);

        $this->assertFalse($manager->isOwner());
    }

    // -------------------------------------------------------------------------
    // scopeForProvider()
    // -------------------------------------------------------------------------

    #[CoversMethod(ProviderManager::class, 'scopeForProvider')]
    public function testForProviderScopeFiltersResultsByProvider(): void
    {
        $profileId = DB::table('users')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        ProviderManager::create(['provider' => 'telegram', 'external_id' => '111', 'profile_id' => $profileId, 'role' => 'owner']);
        ProviderManager::create(['provider' => 'discord', 'external_id' => '222', 'profile_id' => $profileId, 'role' => 'moderator']);

        $results = ProviderManager::forProvider('telegram')->get();

        $this->assertCount(1, $results);
        $this->assertSame('telegram', $results->first()->provider);
    }

    #[CoversMethod(ProviderManager::class, 'scopeForProvider')]
    public function testForProviderScopeReturnsEmptyCollectionWhenNoMatchingProvider(): void
    {
        $results = ProviderManager::forProvider('discord')->get();

        $this->assertCount(0, $results);
    }
}
