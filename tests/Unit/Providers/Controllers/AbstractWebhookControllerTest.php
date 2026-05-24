<?php

declare(strict_types=1);

namespace WerdsWords\LinkStack\SharedProfiles\Tests\Unit\Providers\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use WerdsWords\LinkStack\SharedProfiles\Providers\Controllers\AbstractWebhookController;
use WerdsWords\LinkStack\SharedProfiles\ServiceProvider;
use WerdsWords\LinkStack\SharedProfiles\Tests\Support\Models\User;

#[CoversClass(AbstractWebhookController::class)]
final class AbstractWebhookControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a concrete subclass with controlled behaviour for each abstract method.
     *
     * @param  array<string, mixed>  $messagePayload  Keys that identify a payload as a message
     */
    private function makeController(
        bool $signatureValid,
        array $messagePayload = ['message' => true],
        ?callable $onMessage = null,
        ?callable $onInteraction = null,
    ): AbstractWebhookController {
        return new class($signatureValid, $messagePayload, $onMessage, $onInteraction) extends AbstractWebhookController
        {
            public function __construct(
                private readonly bool $signatureValid,
                private readonly array $messagePayload,
                private readonly mixed $onMessage,
                private readonly mixed $onInteraction,
            ) {}

            protected function verifySignature(Request $request): bool
            {
                return $this->signatureValid;
            }

            protected function isMessage(array $payload): bool
            {
                return array_key_exists(array_key_first($this->messagePayload), $payload);
            }

            protected function handleMessage(array $payload): void
            {
                if ($this->onMessage !== null) {
                    ($this->onMessage)($payload);
                }
            }

            protected function handleInteraction(array $payload): void
            {
                if ($this->onInteraction !== null) {
                    ($this->onInteraction)($payload);
                }
            }
        };
    }

    private function makeRequest(string $method = 'POST', mixed $body = []): Request
    {
        return Request::create('/webhook', $method, $body);
    }

    // -------------------------------------------------------------------------
    // handle() — signature verification
    // -------------------------------------------------------------------------

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandleReturnsForbiddenWhenSignatureInvalid(): void
    {
        $response = $this->makeController(signatureValid: false)->handle($this->makeRequest());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['error' => 'Forbidden'], $response->getData(true));
    }

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandleDoesNotDispatchWhenSignatureInvalid(): void
    {
        $dispatched = false;

        $this->makeController(
            signatureValid: false,
            onMessage: function () use (&$dispatched) {
                $dispatched = true;
            },
            onInteraction: function () use (&$dispatched) {
                $dispatched = true;
            },
        )->handle($this->makeRequest());

        $this->assertFalse($dispatched);
    }

    // -------------------------------------------------------------------------
    // handle() — dispatching
    // -------------------------------------------------------------------------

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandleCallsHandleMessageWhenPayloadIsMessage(): void
    {
        $called = false;

        $this->makeController(
            signatureValid: true,
            messagePayload: ['message' => true],
            onMessage: function () use (&$called) {
                $called = true;
            },
        )->handle($this->makeRequest(body: ['message' => ['text' => 'hello']]));

        $this->assertTrue($called);
    }

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandleCallsHandleInteractionWhenPayloadIsNotMessage(): void
    {
        $called = false;

        $this->makeController(
            signatureValid: true,
            onInteraction: function () use (&$called) {
                $called = true;
            },
        )->handle($this->makeRequest(body: ['callback_query' => ['id' => '123']]));

        $this->assertTrue($called);
    }

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandleReturnsOkResponseOnSuccess(): void
    {
        $response = $this->makeController(signatureValid: true)
            ->handle($this->makeRequest(body: ['callback_query' => []]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandlePassesPayloadToHandleMessage(): void
    {
        $received = [];

        $this->makeController(
            signatureValid: true,
            messagePayload: ['message' => true],
            onMessage: function (array $payload) use (&$received) {
                $received = $payload;
            },
        )->handle($this->makeRequest(body: ['message' => ['text' => 'hello']]));

        $this->assertSame(['message' => ['text' => 'hello']], $received);
    }

    #[CoversMethod(AbstractWebhookController::class, 'handle')]
    public function testHandlePassesPayloadToHandleInteraction(): void
    {
        $received = [];

        $this->makeController(
            signatureValid: true,
            onInteraction: function (array $payload) use (&$received) {
                $received = $payload;
            },
        )->handle($this->makeRequest(body: ['callback_query' => ['id' => '42']]));

        $this->assertSame(['callback_query' => ['id' => '42']], $received);
    }
}
