<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Mapping;

use Telephantast\TelephantastBundle\Handler\HandlerMiddlewareConfigurators;

/**
 * @api
 * @psalm-import-type CallableConfigurator from HandlerMiddlewareConfigurators
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class HandlerMiddlewareWithConfigurator
{
    /**
     * @param CallableConfigurator $configurator
     */
    public function __construct(
        public readonly mixed $configurator,
    ) {}
}
