<?php

declare(strict_types=1);

namespace Telephantast\DoctrinePersistence;

use Doctrine\ORM\EntityManagerInterface;
use Telephantast\MessageBus\Transaction\TransactionProvider;

/**
 * @api
 */
final class DoctrineOrmTransactionProvider implements TransactionProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function wrapInTransaction(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn(): mixed => $operation());
    }
}
