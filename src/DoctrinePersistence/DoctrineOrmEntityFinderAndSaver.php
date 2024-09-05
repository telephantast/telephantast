<?php

declare(strict_types=1);

namespace Telephantast\DoctrinePersistence;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Telephantast\MessageBus\EntityHandler\EntityFinder;
use Telephantast\MessageBus\EntityHandler\EntitySaver;

/**
 * @api
 */
final class DoctrineOrmEntityFinderAndSaver implements EntityFinder, EntitySaver
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {}

    public function findBy(string $class, array $criteria): ?object
    {
        return $this->manager($class)->find($class, $criteria);
    }

    public function save(object $entity): void
    {
        $this->manager($entity::class)->persist($entity);
    }

    /**
     * @param class-string $class
     */
    private function manager(string $class): ObjectManager
    {
        return $this->managerRegistry->getManagerForClass($class)
            ?? throw new \LogicException(\sprintf('No manager for class %s', $class));
    }
}
