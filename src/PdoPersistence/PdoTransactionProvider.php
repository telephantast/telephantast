<?php

declare(strict_types=1);

namespace Telephantast\PdoPersistence;

use Telephantast\MessageBus\Transaction\TransactionProvider;

/**
 * @api
 */
final readonly class PdoTransactionProvider implements TransactionProvider
{
    public function __construct(
        private \PDO $connection,
    ) {}

    /**
     * @throws \Throwable
     */
    public function wrapInTransaction(callable $operation): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $operation();

            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
