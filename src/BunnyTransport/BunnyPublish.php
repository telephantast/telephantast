<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Channel;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Telephantast\MessageBus\Async\ObjectNormalizer;
use Telephantast\MessageBus\Async\TransportPublish;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

/**
 * @api
 */
final class BunnyPublish implements TransportPublish
{
    private readonly BunnyMessageEncoder $messageEncoder;

    /**
     * @var null|Channel|PromiseInterface<Channel>
     */
    private null|Channel|PromiseInterface $channel = null;

    private ConfirmListener $confirmListener;

    public function __construct(
        private readonly BunnyConnectionPool $connectionPool,
        ObjectNormalizer $objectNormalizer,
    ) {
        $this->messageEncoder = new BunnyMessageEncoder($objectNormalizer);
        $this->confirmListener = new ConfirmListener();
    }

    /**
     * @throws \JsonException
     * @throws \Throwable
     */
    public function publish(array $envelopes): void
    {
        $channel = $this->channel();
        $confirmListener = $this->confirmListener;
        $promises = [];

        foreach ($envelopes as $envelope) {
            $deferred = new Deferred();
            $deferred->promise()->then($envelope->onSuccess(...), $envelope->onFailure(...));
            $promises[] = $channel
                ->publish(...$this->messageEncoder->encode($envelope->envelope), exchange: $envelope->exchange)
                ->then(
                    onFulfilled: static function (int $deliveryTag) use ($confirmListener, $deferred): void {
                        $confirmListener->registerEnvelope($deliveryTag, $deferred);
                    },
                    onRejected: $deferred->reject(...),
                );
        }

        await(all($promises));
    }

    /**
     * @psalm-suppress MissingThrowsDocblock
     */
    private function channel(): Channel
    {
        if ($this->channel instanceof Channel && $this->channel->getClient()->isConnected()) {
            return $this->channel;
        }

        if (!$this->channel instanceof PromiseInterface) {
            $this->confirmListener = new ConfirmListener();
            $this->channel = async(function (): Channel {
                $channel = await($this->connectionPool->get()->channel());
                await($channel->confirmSelect($this->confirmListener));

                return $this->channel = $channel;
            })();
        }

        return await($this->channel);
    }
}
