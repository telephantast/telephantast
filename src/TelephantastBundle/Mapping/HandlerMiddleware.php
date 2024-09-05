<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Mapping;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class HandlerMiddleware
{
    public function __construct(
        public readonly int $priority = 0,
    ) {}
}
