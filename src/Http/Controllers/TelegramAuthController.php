<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use WerdsWords\LinkStack\SharedProfiles\Models\TelegramManager;

class TelegramAuthController extends Controller
{
    /**
     * Approach A: initiate the Telegram Login Widget OAuth redirect.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('telegram')->redirect();
    }

    /**
     * Approach A: handle the callback after the user authorises via the Login Widget.
     */
    public function callback(): RedirectResponse
    {
        $social = Socialite::driver('telegram')->user();

        $manager = TelegramManager::where('telegram_id', (string) $social->getId())->first();

        if (! $manager) {
            return redirect()->route('login')->withErrors(['telegram' => 'Not authorised.']);
        }

        Auth::loginUsingId($manager->profile_id);

        return redirect('/studio/index');
    }

    /**
     * Approach B: authenticate via Telegram Mini App initData.
     *
     * The Mini App sends Telegram.WebApp.initData as the `init_data` field.
     * We verify the HMAC, check the auth_date freshness, then log in as the
     * mapped shared-profile user and return a redirect URL for the client to follow.
     */
    public function initDataLogin(Request $request): JsonResponse
    {
        $initData = $request->validate(['init_data' => 'required|string'])['init_data'];

        /** @var string $botToken */
        $botToken = config('linkstack-shared-profiles.bot_token');

        $secret = hash_hmac('sha256', 'WebAppData', $botToken, true);

        parse_str($initData, $rawParams);

        // Narrow parse_str output (array<string, array|string>) to array<string, string>
        $params = [];
        foreach ($rawParams as $key => $value) {
            if (is_string($value)) {
                $params[$key] = $value;
            }
        }

        $hash = $params['hash'] ?? '';
        unset($params['hash']);
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }
        $checkStr = implode("\n", $pairs);

        $computed = hash_hmac('sha256', $checkStr, $secret);

        if (! hash_equals($computed, $hash)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        /** @var int $ttl */
        $ttl = config('linkstack-shared-profiles.auth_date_ttl', 300);

        $authDate = isset($params['auth_date']) ? (int) $params['auth_date'] : 0;

        if (time() - $authDate > $ttl) {
            return response()->json(['error' => 'Token expired'], 403);
        }

        $userJson = $params['user'] ?? '{}';

        /** @var array{id?: int|string}|null $tgUser */
        $tgUser = json_decode($userJson, true);

        $manager = TelegramManager::where('telegram_id', (string) ($tgUser['id'] ?? ''))->first();

        if (! $manager) {
            return response()->json(['error' => 'Not authorised'], 403);
        }

        Auth::loginUsingId($manager->profile_id);
        $request->session()->regenerate();

        return response()->json(['redirect' => '/studio/index']);
    }
}
