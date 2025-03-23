<?php

declare(strict_types=1);

namespace Telephantast\ThesisAmqpTransport;

use Telephantast\Message\Message;
use Telephantast\MessageBus\Async\Delay;
use Telephantast\MessageBus\Async\ObjectDenormalizer;
use Telephantast\MessageBus\CreatedAt\CreatedAt;
use Telephantast\MessageBus\Envelope;
use Telephantast\MessageBus\MessageId\CorrelationId;
use Telephantast\MessageBus\MessageId\MessageId;
use Telephantast\MessageBus\Stamp;
use Thesis\Amqp\Delivery;

/**
 * @internal
 * @psalm-internal Telephantast\ThesisAmqpTransport
 */
final class ThesisEnvelopeDecoder
{
    public function __construct(
        private readonly ObjectDenormalizer $objectDenormalizer,
    ) {}

    public function decode(Delivery $delivery): Envelope
    {
        \assert($delivery->type !== null && class_exists($delivery->type));
        $message = $this->objectDenormalizer->denormalize(
            json_decode($delivery->body, associative: true, flags: JSON_THROW_ON_ERROR),
            $delivery->type,
        );
        \assert($message instanceof Message);

        $stamps = [];

        foreach ($delivery->headers['stamps'] ?? [] as $stampClass => $stampData) {
            \assert(\is_string($stampClass) && class_exists($stampClass));
            $stamp = $this->objectDenormalizer->denormalize($stampData, $stampClass);
            \assert($stamp instanceof Stamp);
            $stamps[] = $stamp;
        }

        if ($delivery->messageId !== null && $delivery->messageId !== '') {
            $stamps[] = new MessageId($delivery->messageId);
        }

        if ($delivery->correlationId !== null && $delivery->correlationId !== '') {
            $stamps[] = new CorrelationId($delivery->correlationId);
        }

        if ($delivery->timestamp !== null) {
            $stamps[] = new CreatedAt(\DateTimeImmutable::createFromInterface($delivery->timestamp));
        }

        if (isset($delivery->headers['x-delay'])) {
            \assert(\is_int($delivery->headers['x-delay']));
            $stamps[] = new Delay((int) ($delivery->headers['x-delay'] / 1000));
        }

        return Envelope::wrap($message, ...$stamps);
    }
}
