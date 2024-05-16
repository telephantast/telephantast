<?php

declare(strict_types=1);

namespace Telephantast\EntityHandler;

/**
 * @api
 */
interface EntitySaver
{
    public function save(object $entity): void;
}
