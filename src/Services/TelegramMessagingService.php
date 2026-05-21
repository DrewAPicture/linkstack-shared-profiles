<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Services;

use Illuminate\Support\Facades\Http;

class TelegramMessagingService
{
    private const API_BASE = 'https://api.telegram.org';

    /**
     * Send a text message via the Telegram Bot API.
     *
     * @param  int|string  $chatId  Telegram chat ID (numeric user ID or @username)
     */
    public function sendMessage(#[\SensitiveParameter] string $botToken, int|string $chatId, string $text): bool
    {
        $response = Http::post(
            self::API_BASE."/bot{$botToken}/sendMessage",
            ['chat_id' => $chatId, 'text' => $text]
        );

        return $response->successful();
    }
}
