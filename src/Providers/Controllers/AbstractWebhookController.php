<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Providers\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class AbstractWebhookController extends Controller
{
    abstract protected function verifySignature(Request $request): bool;

    /** @param array<string, mixed> $payload */
    abstract protected function isMessage(array $payload): bool;

    /** @param array<string, mixed> $payload */
    abstract protected function handleMessage(array $payload): void;

    /** @param array<string, mixed> $payload */
    abstract protected function handleInteraction(array $payload): void;

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        if ($this->isMessage($payload)) {
            $this->handleMessage($payload);
        } else {
            $this->handleInteraction($payload);
        }

        return response()->json(['ok' => true]);
    }
}
