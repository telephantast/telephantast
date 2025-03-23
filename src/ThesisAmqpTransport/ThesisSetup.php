<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\MessageBus\Async\TransportSetup;
use Thesis\Amqp\Client;

/**
 * @api
 */
final class ThesisSetup implements TransportSetup
{
    public function __construct(
        private readonly Client $client,
    ) {}

    /**
     * @throws \Throwable
     */
    public function setup(array $exchangeToQueues): void
    {
        $channel = $this->client->channel();

        foreach ($exchangeToQueues as $exchange => $queues) {
            $channel->exchangeDeclare($exchange, 'fanout', durable: true);

            foreach ($queues as $queue) {
                $channel->queueDeclare($queue, durable: true);
                $channel->queueBind($queue, $exchange);
            }
        }

        $channel->close();
    }
}
