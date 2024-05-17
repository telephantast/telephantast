<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Telephantast\Message\Message;
use Telephantast\MessageBus\Async\Delay;
use Telephantast\MessageBus\Async\ObjectNormalizer;
use Telephantast\MessageBus\CreatedAt\CreatedAt;
use Telephantast\MessageBus\Envelope;
use Telephantast\MessageBus\MessageId\CorrelationId;
use Telephantast\MessageBus\MessageId\MessageId;
use Telephantast\MessageBus\Stamp;

/**
 * @internal
 * @psalm-internal Telephantast\BunnyTransport
 * @psalm-type BunnyHeaders = array{
 *     type: class-string<Message>,
 *     content-type?: string,
 *     content-encoding?: string,
 *     delivery-mode?: self::DELIVERY_MODE_*,
 *     priority?: int,
 *     message-id?: non-empty-string,
 *     correlation-id?: non-empty-string,
 *     reply-to?: string,
 *     user-id?: string,
 *     expiration?: int,
 *     timestamp?: \DateTimeInterface,
 *     x-delay?: numeric-string,
 *     app-id?: string,
 *     stamps?: array<class-string<Stamp>, mixed>,
 * }
 */
final readonly class BunnyMessageEncoder
{
    private const ENCODING = 'UTF-8';
    private const CONTENT_TYPE = 'application/json';
    private const DELIVERY_MODE_PERSISTENT = 2;

    public function __construct(
        private ObjectNormalizer $objectNormalizer,
    ) {}

    /**
     * @return array{
     *     body: string,
     *     headers?: BunnyHeaders,
     *     mandatory?: bool,
     *     immediate?: bool,
     * }
     */
    public function encode(Envelope $envelope): array
    {
        $headers = [
            'type' => $envelope->message::class,
            'content-type' => self::CONTENT_TYPE,
            'content-encoding' => self::ENCODING,
            'delivery-mode' => self::DELIVERY_MODE_PERSISTENT,
        ];

        $messageIdStamp = $envelope->getStamp(MessageId::class);

        if ($messageIdStamp !== null) {
            $headers['message-id'] = $messageIdStamp->messageId;
            $envelope = $envelope->withoutStamp(MessageId::class);
        }

        $correlationIdStamp = $envelope->getStamp(CorrelationId::class);

        if ($correlationIdStamp !== null) {
            $headers['correlation-id'] = $correlationIdStamp->correlationId;
            $envelope = $envelope->withoutStamp(CorrelationId::class);
        }

        $createdAt = $envelope->getStamp(CreatedAt::class);

        if ($createdAt !== null) {
            $headers['timestamp'] = $createdAt->time;
            $envelope = $envelope->withoutStamp(CreatedAt::class);
        }

        $delay = $envelope->getStamp(Delay::class)?->milliseconds ?? 0;

        if ($delay > 0) {
            $headers['x-delay'] = (string) $delay;
            $envelope = $envelope->withoutStamp(Delay::class);
        }

        $headers['stamps'] = array_map($this->objectNormalizer->normalize(...), $envelope->classToStamp);

        return [
            'body' => json_encode($this->objectNormalizer->normalize($envelope->message), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'headers' => $headers,
        ];
    }
}
