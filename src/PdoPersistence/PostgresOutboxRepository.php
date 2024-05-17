<?php

declare(strict_types=1);

namespace Telephantast\PdoPersistence;

use Telephantast\MessageBus\Async\Outbox;
use Telephantast\MessageBus\Async\OutboxRepository;

/**
 * @api
 */
final readonly class PostgresOutboxRepository implements OutboxRepository
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
                    consumed_message_id varchar(255) not null,
                    message_id          varchar(255) not null,
                    envelope            bytea        not null,
                    primary key (consumed_message_id, message_id)
                )
                SQL,
        );
    }

    public function get(string $consumedMessageId): Outbox
    {
        return new PostgresOutbox($this->connection, $this->table, $consumedMessageId);
    }
}
