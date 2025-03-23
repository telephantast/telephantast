<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\MessageBus\Async\Exchange;
use Telephantast\MessageBus\Async\ObjectNormalizer;
use Telephantast\MessageBus\Async\TransportPublish;
use Thesis\Amqp\Client;
use Thesis\Amqp\Confirmation;

/**
 * @api
 */
final class ThesisPublish implements TransportPublish
{
    private readonly ThesisEnvelopeEncoder $encoder;

    public function __construct(
        private readonly Client $client,
        ObjectNormalizer $objectNormalizer,
    ) {
        $this->encoder = new ThesisEnvelopeEncoder($objectNormalizer);
    }

    /**
     * @throws \Throwable
     */
    public function publish(array $envelopes): void
    {
        $this->client->connect();
        $channel = $this->client->channel();
        $channel->confirmSelect();

        $confirmations = [];

        foreach ($envelopes as $envelope) {
            $exchange = $envelope->getStamp(Exchange::class)?->exchange ?? throw new \LogicException('No exchange stamp');
            $confirmation = $channel->publish(
                message: $this->encoder->encode($envelope),
                exchange: $exchange,
            );
            \assert($confirmation !== null);
            $confirmations[] = $confirmation;
        }

        Confirmation::awaitAll($confirmations);
    }
}
