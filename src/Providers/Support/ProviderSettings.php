<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Support;

use Illuminate\Support\Facades\DB;

class ProviderSettings
{
    public static function get(string $provider, int $profileId, string $key, mixed $default = null): mixed
    {
        /** @var string|null $raw */
        $raw = DB::table('provider_settings')
            ->where('profile_id', $profileId)
            ->where('provider', $provider)
            ->value('settings');

        if ($raw === null) {
            return $default;
        }

        /** @var array<string, mixed>|null $settings */
        $settings = json_decode($raw, true);

        if (! is_array($settings)) {
            return $default;
        }

        return $settings[$key] ?? $default;
    }

    /**
     * Merge the given settings into the stored settings for this provider and profile.
     * Existing keys not present in $settings are preserved.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function set(string $provider, int $profileId, array $settings): void
    {
        /** @var string|null $existingRaw */
        $existingRaw = DB::table('provider_settings')
            ->where('profile_id', $profileId)
            ->where('provider', $provider)
            ->value('settings');

        if ($existingRaw !== null) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($existingRaw, true);
            $current = is_array($decoded) ? $decoded : [];
            $merged = array_merge($current, $settings);

            DB::table('provider_settings')
                ->where('profile_id', $profileId)
                ->where('provider', $provider)
                ->update(['settings' => json_encode($merged)]);
        } else {
            DB::table('provider_settings')->insert([
                'profile_id' => $profileId,
                'provider' => $provider,
                'settings' => json_encode($settings),
            ]);
        }
    }
}
