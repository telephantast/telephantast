<?php

declare(strict_types=1);

namespace Telephantast\DoctrinePersistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Telephantast\MessageBus\Outbox\Outbox;
use Telephantast\MessageBus\Outbox\OutboxAlreadyExists;
use Telephantast\MessageBus\Outbox\OutboxStorage;

/**
 * @api
 */
final class DoctrinePostgresOutboxStorage implements OutboxStorage
{
    /**
     * @param literal-string $table
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'telephantast_outbox',
    ) {}

    public function configureSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->table);
        $table->addColumn('queue', Types::TEXT);
        $table->addColumn('message_id', Types::GUID);
        $table->addColumn('outbox', Types::BINARY);
        $table->setPrimaryKey(['message_id', 'queue']);
    }

    public function get(?string $queue, string $messageId): ?Outbox
    {
        $result = $this->connection->executeQuery(
            <<<SQL
                select outbox
                from {$this->table}
                where queue = ? and message_id = ?
                SQL,
            [$queue ?? '', $messageId],
        );
        $outbox = $result->fetchOne();

        if (\is_resource($outbox)) {
            /** @var Outbox */
            return unserialize(stream_get_contents($outbox));
        }

        return null;
    }

    public function create(?string $queue, string $messageId, Outbox $outbox): void
    {
        $affectedRows = $this->connection->executeStatement(
            <<<SQL
                insert into {$this->table} (queue, message_id, outbox)
                values (?, ?, ?)
                on conflict (message_id, queue) do nothing
                SQL,
            [$queue ?? '', $messageId, serialize($outbox)],
            [2 => ParameterType::BINARY],
        );

        if ($affectedRows === 0) {
            throw new OutboxAlreadyExists();
        }
    }

    public function empty(?string $queue, string $messageId): void
    {
        $this->connection->executeStatement(
            <<<SQL
                update {$this->table}
                set outbox = ?
                where queue = ? and message_id = ?
                SQL,
            [serialize(new Outbox()), $queue ?? '', $messageId],
            [ParameterType::BINARY],
        );
    }
}
