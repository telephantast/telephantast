<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\MessageBus\Async\Exchange;
use Telephantast\MessageBus\Async\ObjectNormalizer;
use Telephantast\MessageBus\Async\TransportPublish;
use Thesis\Amqp\Channel;
use Thesis\Amqp\Client;
use Thesis\Amqp\Confirmation;
use Thesis\Amqp\PublishResult;

/**
 * @api
 */
final class ThesisPublish implements TransportPublish
{
    private readonly ThesisEnvelopeEncoder $encoder;

    private ?Channel $channel = null;

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
        if ($this->channel === null) {
            $this->channel = $this->client->channel();
            $this->channel->confirmSelect();
        }

        $confirmations = [];

        foreach ($envelopes as $envelope) {
            $exchange = $envelope->getStamp(Exchange::class)?->exchange ?? throw new \LogicException('No exchange stamp');
            $confirmation = $this->channel->publish(
                message: $this->encoder->encode($envelope),
                exchange: $exchange,
            );
            \assert($confirmation !== null);
            $confirmations[] = $confirmation;
        }

        foreach (Confirmation::awaitAll($confirmations) as $publishResult) {
            if ($publishResult !== PublishResult::Acked) {
                throw new \LogicException('Failed to publish an envelope');
            }
        }
    }
}
