<?php

declare(strict_types=1);

namespace Telephantast\Async;

use Telephantast\MessageBus\Stamp;

/**
 * @api
 * @psalm-immutable
 */
final readonly class Delay implements Stamp
{
    public function __construct(
        public int $seconds,
    ) {}
}
