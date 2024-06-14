<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Message;
use Telephantast\MessageBus\Async\Consumer;
use Telephantast\MessageBus\Async\ObjectDenormalizer;
use Telephantast\MessageBus\Async\TransportConsume;
use function React\Async\async;
use function React\Async\await;

/**
 * @api
 */
final class BunnyConsume implements TransportConsume
{
    private const DEFAULT_PREFETCH_COUNT = 50;

    private BunnyMessageDecoder $messageDecoder;

    /**
     * @var \WeakMap<Consumer, \Closure(): void>
     */
    private \WeakMap $consumerToCancel;

    public function __construct(
        private readonly BunnyConnectionPool $connectionPool,
        ObjectDenormalizer $objectDenormalizer,
        private readonly int $prefetchCount = self::DEFAULT_PREFETCH_COUNT,
    ) {
        $this->messageDecoder = new BunnyMessageDecoder($objectDenormalizer);
        /** @var \WeakMap<Consumer, \Closure(): void> */
        $this->consumerToCancel = new \WeakMap();
    }

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    public function runConsumer(Consumer $consumer): void
    {
        $channel = await($this->connectionPool->get()->channel());
        await($channel->qos(prefetchCount: $this->prefetchCount));
        $consumerTag = await($channel->consume(
            callback: function (Message $message) use ($channel, $consumer): void {
                async(function () use ($channel, $consumer, $message): void {
                    $consumer->handle($this->messageDecoder->decode($message));
                    await($channel->ack($message));
                })();
            },
            queue: $consumer->queue,
        ))->consumerTag;
        $this->consumerToCancel[$consumer] = static function () use ($channel, $consumerTag): void {
            await($channel->cancel($consumerTag));
            await($channel->close());
        };
    }

    /**
     * @throws \Throwable
     */
    public function stopConsumer(Consumer $consumer): void
    {
        $cancel = $this->consumerToCancel[$consumer] ?? null;

        if ($cancel !== null) {
            $cancel();
            unset($this->consumerToCancel[$consumer]);
        }
    }
}
