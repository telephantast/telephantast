<?php

declare(strict_types=1);

namespace Telephantast\Outbox;

use Telephantast\MessageBus\Envelope;

/**
 * @api
 */
interface OutboxPublish
{
    /**
     * @param non-empty-list<Envelope> $envelopes
     * @param \Closure (Envelope): void $onSuccess
     */
    public function publish(array $envelopes, \Closure $onSuccess): void;
}
