<?php

declare(strict_types=1);

namespace Telephantast\BunnyTransport;

use Bunny\Protocol\MethodBasicAckFrame;
use Bunny\Protocol\MethodBasicNackFrame;
use React\Promise\Deferred;

/**
 * @internal
 * @psalm-internal Telephantast\BunnyTransport
 */
final class ConfirmListener
{
    /**
     * @var array<int, Deferred>
     */
    private array $deliveryTagToDeferred = [];

    public function registerEnvelope(int $deliveryTag, Deferred $deferred): void
    {
        $this->deliveryTagToDeferred[$deliveryTag] = $deferred;
    }

    public function __invoke(MethodBasicAckFrame|MethodBasicNackFrame $frame): void
    {
        $action = $frame instanceof MethodBasicAckFrame
            ? static function (Deferred $deferred): void { $deferred->resolve(); }
        : static function (Deferred $deferred): void { $deferred->reject(new \LogicException('NACK')); };

        if ($frame->multiple) {
            foreach ($this->deliveryTagToDeferred as $deliveryTag => $deferred) {
                if ($deliveryTag > $frame->deliveryTag) {
                    break;
                }

                unset($this->deliveryTagToDeferred[$deliveryTag]);
                $action($deferred);
            }

            return;
        }

        if (!isset($this->deliveryTagToDeferred[$frame->deliveryTag])) {
            return;
        }

        $deferred = $this->deliveryTagToDeferred[$frame->deliveryTag];
        unset($this->deliveryTagToDeferred[$frame->deliveryTag]);
        $action($deferred);
    }
}
