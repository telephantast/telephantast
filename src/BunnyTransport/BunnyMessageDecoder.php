<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Message as BunnyMessage;
use Telephantast\MessageBus\Async\Delay;
use Telephantast\MessageBus\Async\ObjectDenormalizer;
use Telephantast\MessageBus\CreatedAt\CreatedAt;
use Telephantast\MessageBus\Envelope;
use Telephantast\MessageBus\MessageId\CorrelationId;
use Telephantast\MessageBus\MessageId\MessageId;

/**
 * @internal
 * @psalm-internal Telephantast\BunnyTransport
 * @psalm-import-type BunnyHeaders from BunnyMessageEncoder
 */
final class BunnyMessageDecoder
{
    public function __construct(
        private readonly ObjectDenormalizer $objectDenormalizer,
    ) {}

    public function decode(BunnyMessage $bunnyMessage): Envelope
    {
        /** @var BunnyHeaders $headers */
        $headers = $bunnyMessage->headers;
        $message = $this->objectDenormalizer->denormalize(
            json_decode($bunnyMessage->content, associative: true, flags: JSON_THROW_ON_ERROR),
            $headers['type'],
        );
        $stamps = [];

        foreach ($headers['stamps'] ?? [] as $stampClass => $stampData) {
            $stamps[] = $this->objectDenormalizer->denormalize($stampData, $stampClass);
        }

        if (isset($headers['message-id'])) {
            $stamps[] = new MessageId($headers['message-id']);
        }

        if (isset($headers['correlation-id'])) {
            $stamps[] = new CorrelationId($headers['correlation-id']);
        }

        if (isset($headers['timestamp'])) {
            $stamps[] = new CreatedAt(\DateTimeImmutable::createFromInterface($headers['timestamp']));
        }

        if (isset($headers['x-delay'])) {
            $stamps[] = new Delay((int) ($headers['x-delay'] / 1000));
        }

        return Envelope::wrap($message, ...$stamps);
    }
}
