<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\MessageBus\Async\Delay;
use Telephantast\MessageBus\Async\ObjectNormalizer;
use Telephantast\MessageBus\CreatedAt\CreatedAt;
use Telephantast\MessageBus\Envelope;
use Telephantast\MessageBus\MessageId\CorrelationId;
use Telephantast\MessageBus\MessageId\MessageId;
use Thesis\Amqp\DeliveryMode;
use Thesis\Amqp\Message;

/**
 * @internal
 * @psalm-internal Telephantast\ThesisAmqpTransport
 */
final class ThesisEnvelopeEncoder
{
    private const ENCODING = 'UTF-8';
    private const CONTENT_TYPE = 'application/json';

    public function __construct(
        private readonly ObjectNormalizer $objectNormalizer,
    ) {}

    public function encode(Envelope $envelope): Message
    {
        $headers = [];
        $delay = $envelope->getStamp(Delay::class)?->milliseconds ?? 0;

        if ($delay > 0) {
            $headers['x-delay'] = (string) $delay;
            $envelope = $envelope->withoutStamp(Delay::class);
        }

        $stamps = $envelope
            ->withoutStamp(CorrelationId::class, MessageId::class, CreatedAt::class)
            ->stamps;
        $headers['stamps'] = array_map($this->objectNormalizer->normalize(...), $stamps);

        return new Message(
            body: json_encode($this->objectNormalizer->normalize($envelope->message), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            headers: $headers,
            contentType: self::CONTENT_TYPE,
            contentEncoding: self::ENCODING,
            deliveryMode: DeliveryMode::Persistent,
            correlationId: $envelope->getStamp(CorrelationId::class)?->correlationId,
            messageId: $envelope->getStamp(MessageId::class)?->messageId,
            timestamp: $envelope->getStamp(CreatedAt::class)?->time,
            type: $envelope->getMessageClass(),
        );
    }
}
