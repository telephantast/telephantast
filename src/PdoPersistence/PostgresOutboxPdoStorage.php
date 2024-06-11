<?php

declare(strict_types=1);

namespace Telephantast\PdoPersistence;

use Telephantast\MessageBus\Outbox\Outbox;
use Telephantast\MessageBus\Outbox\OutboxStorage;

/**
 * @api
 */
final readonly class PostgresOutboxPdoStorage implements OutboxStorage
{
    /**
     * @param literal-string $table
     */
    public function __construct(
        private \PDO $connection,
        private string $table,
    ) {}

    public function setup(): void
    {
        $this->connection->exec(
            <<<SQL
                create table if not exists {$this->table}
                (
                    queue      text  not null,
                    message_id text  not null,
                    outbox     bytea not null,
                    primary key (message_id, queue)
                )
                SQL,
        );
    }

    public function get(?string $queue, string $messageId): ?Outbox
    {
        $statement = $this->connection->prepare(
            <<<SQL
                select outbox
                from {$this->table}
                where queue = ? and message_id = ?
                SQL,
        );
        $statement->execute([$queue ?? '', $messageId]);
        $outbox = $statement->fetchColumn();

        if (\is_resource($outbox)) {
            /** @var Outbox */
            return unserialize(stream_get_contents($outbox));
        }

        return null;
    }

    public function save(?string $queue, string $messageId, Outbox $outbox): void
    {
        $statement = $this->connection->prepare(
            <<<SQL
                insert into {$this->table} (queue, message_id, outbox)
                values (?, ?, ?)
                on conflict (message_id, queue) do update
                set outbox = excluded.outbox
                SQL,
        );
        $statement->bindValue(1, $queue ?? '');
        $statement->bindValue(2, $messageId);
        $statement->bindValue(3, serialize($outbox), \PDO::PARAM_LOB);
        $statement->execute();
    }
}
