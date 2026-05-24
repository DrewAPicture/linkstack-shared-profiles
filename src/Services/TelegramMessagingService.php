<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Services;

use Illuminate\Support\Facades\Http;
use SensitiveParameter;

class TelegramMessagingService
{
    private const API_BASE = 'https://api.telegram.org';

    /**
     * Send a text message via the Telegram Bot API.
     *
     * @param  int|string  $chatId  Telegram chat ID (numeric user ID or @username)
     */
    public function sendMessage(#[SensitiveParameter] string $botToken, int|string $chatId, string $text): bool
    {
        $response = Http::post(
            self::API_BASE."/bot{$botToken}/sendMessage",
            ['chat_id' => $chatId, 'text' => $text]
        );

        return $response->successful();
    }

    /**
     * Send a text message with an inline keyboard via the Telegram Bot API.
     *
     * @param  int|string  $chatId         Telegram chat ID (numeric user ID or @username)
     * @param  array<int, array<int, array<string, string>>>  $inlineKeyboard  Array of button rows
     */
    public function sendMessageWithKeyboard(#[SensitiveParameter] string $botToken, int|string $chatId, string $text, array $inlineKeyboard): bool
    {
        $response = Http::post(
            self::API_BASE."/bot{$botToken}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => ['inline_keyboard' => $inlineKeyboard],
            ]
        );

        return $response->successful();
    }
}
