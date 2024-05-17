<?php

declare(strict_types=1);

namespace Telephantast\PdoPersistence;

use Telephantast\MessageBus\Async\Outbox;
use Telephantast\MessageBus\Envelope;

/**
 * @api
 */
final readonly class PostgresOutbox implements Outbox
{
    /**
     * @param literal-string $table
     * @param non-empty-string $consumedMessageId
     */
    public function __construct(
        private \PDO $connection,
        private string $table,
        private string $consumedMessageId,
    ) {}

    public function add(Envelope $envelope): void
    {
        $statement = $this->connection->prepare(
            <<<SQL
                insert into {$this->table} (consumed_message_id, message_id, envelope)
                values (?, ?, ?)
                SQL,
        );
        $statement->bindValue(1, $this->consumedMessageId);
        $statement->bindValue(2, $envelope->getMessageId());
        $statement->bindValue(3, serialize($envelope), \PDO::PARAM_LOB);
        $statement->execute();
    }

    public function all(): array
    {
        $statement = $this->connection->prepare(
            <<<SQL
                select envelope
                from {$this->table}
                where consumed_message_id = ?
                SQL,
        );
        $statement->execute([$this->consumedMessageId]);

        $envelopes = [];

        /** @psalm-suppress InvalidArrayOffset, InvalidArrayAccess */
        foreach ($statement as ['envelope' => $serializedEnvelope]) {
            \assert(\is_resource($serializedEnvelope));
            $envelope = unserialize(stream_get_contents($serializedEnvelope));
            \assert($envelope instanceof Envelope);
            $envelopes[] = $envelope;
        }

        return $envelopes;
    }

    public function remove(string $messageId): void
    {
        $statement = $this->connection->prepare(
            <<<SQL
                delete from {$this->table}
                where consumed_message_id = ? and message_id = ?
                SQL,
        );
        $statement->execute([$this->consumedMessageId, $messageId]);
    }
}
