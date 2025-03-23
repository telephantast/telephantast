<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\ObjectDenormalizer;
use Telephantast\MessageBus\Async\TransportConsume;
use Thesis\Amqp\Client;
use Thesis\Amqp\Delivery;

/**
 * @api
 */
final class ThesisConsume implements TransportConsume
{
    private readonly ThesisEnvelopeDecoder $decoder;

    /**
     * @param non-negative-int $prefetchCount
     */
    public function __construct(
        private readonly Client $client,
        ObjectDenormalizer $objectDenormalizer,
        private readonly int $prefetchCount,
    ) {
        $this->decoder = new ThesisEnvelopeDecoder($objectDenormalizer);
    }

    /**
     * @throws \Throwable
     */
    public function runConsumer(Consumer $consumer): \Closure
    {
        $channel = $this->client->channel();
        $channel->qos(prefetchCount: $this->prefetchCount);

        $consumerTag = $channel->consume(
            callback: function (Delivery $delivery) use ($consumer): void {
                $consumer->handle($this->decoder->decode($delivery));
                $delivery->ack();
            },
            queue: $consumer->queue,
        );

        return static function () use ($channel, $consumerTag): void {
            $channel->cancel($consumerTag);
            $channel->close();
        };
    }
}
