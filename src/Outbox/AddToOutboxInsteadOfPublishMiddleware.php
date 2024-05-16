<?php

declare(strict_types=1);

namespace Telephantast\Outbox;

use Telephantast\MessageBus\Handler\Pipeline;
use Telephantast\MessageBus\MessageContext;
use Telephantast\MessageBus\Middleware;
use function Telephantast\MessageBus\MessageId\messageId;

/**
 * @api
 */
final readonly class AddToOutboxInsteadOfPublishMiddleware implements Middleware
{
    public function __construct(
        private OutboxRepository $outboxRepository,
    ) {}

    public function handle(MessageContext $messageContext, Pipeline $pipeline): mixed
    {
        $outboxId = $messageContext->parent?->attribute(Outbox::class)?->outboxId;

        if ($outboxId === null) {
            return $pipeline->continue();
        }

        $this->outboxRepository->add($outboxId, messageId($messageContext), $messageContext->envelope);

        /** @phpstan-ignore return.type */
        return null;
    }
}
