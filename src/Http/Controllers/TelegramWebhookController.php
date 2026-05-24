<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        /** @var string|null $secret */
        $secret = config('linkstack-shared-profiles.webhook_secret');

        if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $update */
        $update = $request->all();

        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleMessage(array $message): void {}

    /**
     * @param  array<string, mixed>  $query
     */
    private function handleCallbackQuery(array $query): void {}
}
