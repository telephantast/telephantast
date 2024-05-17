<?php

declare(strict_types=1);

namespace Telephantast\PdoPersistence;

use Telephantast\MessageBus\Deduplication\Deduplicator;

/**
 * @api
 */
final readonly class PostgresDeduplicator implements Deduplicator
{
    /**
     * @param literal-string $table
     */
    public function __construct(
        private \PDO $connection,
        private string $table,
    ) {}

    public function createTable(): void
    {
        $this->connection->exec(
            <<<SQL
                create table if not exists {$this->table}
                (
                    queue      character varying(255) not null,
                    message_id character varying(255) not null,
                    primary key (queue, message_id)
                )
                SQL,
        );
    }

    public function isHandled(string $queue, string $messageId): bool
    {
        $statement = $this->connection->prepare(
            <<<SQL
                insert into {$this->table} (queue, message_id)
                values (?, ?)
                on conflict (queue, message_id) do nothing
                SQL,
        );
        $statement->execute([$queue, $messageId]);

        return $statement->rowCount() === 0;
    }
}
