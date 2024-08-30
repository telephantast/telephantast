<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Telephantast\MessageBus\Async\TransportSetup;
use function React\Async\await;

/**
 * @api
 */
final class BunnySetup implements TransportSetup
{
    public function __construct(
        private readonly BunnyConnectionPool $connectionPool,
    ) {}

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    public function setup(array $exchangeToQueues): void
    {
        $channel = await($this->connectionPool->get()->channel());

        foreach ($exchangeToQueues as $exchange => $queues) {
            await($channel->exchangeDeclare($exchange, 'x-delayed-message', durable: true, arguments: [
                'x-delayed-type' => 'fanout',
            ]));

            foreach ($queues as $queue) {
                await($channel->queueDeclare($queue, durable: true));
                await($channel->queueBind($queue, $exchange));
            }
        }

        await($channel->close());
    }
}
