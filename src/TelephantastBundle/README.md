# Telephantast Symfony Bundle

## Installation

```shell
composer require telephantast/telephantast-bundle '^1.0@dev'
```

If you use Symfony Flex, the bundle will be automatically registered in `bundles.php`. Otherwise, do it manually:

```diff
return [
    // ...
+   Telephantast\TelephantastBundle\TelephantastBundle::class => ['all' => true],
];
```

## Configuration

See the full config by running:

```shell
bin/console debug:config telephantast
```

Configuration for [bunny-transport](https://github.com/telephantast/bunny-transport) and [doctrine-persistence](https://github.com/telephantast/doctrine-persistence):

```php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Telephantast\DoctrinePersistence\DoctrineOrmEntityFinderAndSaver;
use Telephantast\DoctrinePersistence\DoctrineOrmTransactionProvider;
use Telephantast\DoctrinePersistence\DoctrinePostgresOutboxStorage;

return static function (ContainerConfigurator $di): void {
    $di->extension('telephantast', [
        'entity_finder_id' => DoctrineOrmEntityFinderAndSaver::class,
        'entity_saver_id' => DoctrineOrmEntityFinderAndSaver::class,
        'async' => [
            'bunny' => [
                'host' => '%env(string:key:host:url:TELEPHANTAST_TRANSPORT_URL)%',
                'port' => '%env(int:key:port:url:TELEPHANTAST_TRANSPORT_URL)%',
                'user' => '%env(string:key:user:url:TELEPHANTAST_TRANSPORT_URL)%',
                'password' => '%env(string:key:pass:url:TELEPHANTAST_TRANSPORT_URL)%',
                'vhost' => '%env(string:key:path:url:TELEPHANTAST_TRANSPORT_URL)%',
                'heartbeat' => '%env(int:key:heartbeat:query_string:TELEPHANTAST_TRANSPORT_URL)%',
            ],
            'outbox' => [
                'transaction_provider_id' => DoctrineOrmTransactionProvider::class,
                'storage_id' => DoctrinePostgresOutboxStorage::class,
            ],
        ],
        'entities' => [
            MyEntity::class => null,
        ],
    ]);

    $di->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->set(DoctrineOrmTransactionProvider::class)
        ->set(DoctrinePostgresOutboxStorage::class)
        ->set(DoctrineOrmEntityFinderAndSaver::class);
};
```

```dotenv
TELEPHANTAST_TRANSPORT_URL=bunny://guest:guest@rabbitmq:5672//?heartbeat=60
```
