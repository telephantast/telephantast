<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;
use PHPyh\CodingStandard\PhpCsFixerCodingStandard;

$config = (new Config())
    ->setParallelConfig(new ParallelConfig())
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tools/psalm/src')
            ->append([__FILE__])
            ->append(
                Finder::create()
                    ->in(__DIR__ . '/tests'),
            ),
    )
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');

(new PhpCsFixerCodingStandard())->applyTo($config);

return $config;
