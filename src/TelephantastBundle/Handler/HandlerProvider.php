<?php

declare(strict_types=1);

namespace Telephantast\TelephantastBundle\Handler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 * @psalm-internal Telephantast\TelephantastBundle
 */
interface HandlerProvider
{
    /**
     * @return iterable<HandlerBuilder>
     */
    public function getHandlers(ContainerBuilder $container): iterable;
}
