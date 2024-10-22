<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Message;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\ObjectDenormalizer;
use Telephantast\MessageBus\Async\TransportConsume;
use function React\Async\await;

/**
 * @api
 */
final class BunnyConsume implements TransportConsume
{
    private const DEFAULT_PREFETCH_COUNT = 1;

    private readonly BunnyMessageDecoder $messageDecoder;

    public function __construct(
        private readonly BunnyConnectionPool $connectionPool,
        ObjectDenormalizer $objectDenormalizer,
        private readonly int $prefetchCount = self::DEFAULT_PREFETCH_COUNT,
    ) {
        $this->messageDecoder = new BunnyMessageDecoder($objectDenormalizer);
    }

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    public function runConsumer(Consumer $consumer): \Closure
    {
        $channel = await($this->connectionPool->get()->channel());
        await($channel->qos(prefetchCount: $this->prefetchCount));

        $consumerTag = await($channel->consume(
            callback: function (Message $message) use ($channel, $consumer): void {
                $consumer->handle($this->messageDecoder->decode($message));
                await($channel->ack($message));
            },
            queue: $consumer->queue,
        ))->consumerTag;

        return static function () use ($channel, $consumerTag): void {
            await($channel->cancel($consumerTag));
            await($channel->close());
        };
    }

    public function disconnect(): void
    {
        $this->connectionPool->disconnect();
    }
}
