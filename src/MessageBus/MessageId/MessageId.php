<?php

declare(strict_types=1);

namespace Telephantast\MessageBus\MessageId;

use Telephantast\MessageBus\Stamp;

/**
 * @api
 * @psalm-immutable
 */
final readonly class MessageId implements Stamp
{
    /**
     * @param non-empty-string $messageId
     */
    public function __construct(
        public string $messageId,
    ) {}
}
