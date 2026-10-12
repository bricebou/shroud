<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Attribute\ExplicitAttributeNamedArgsRector;
use Rector\Config\RectorConfig;

// The PHP set follows the floor declared in composer.json: keep both in step.
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin/shroud-baseline', __DIR__ . '/bin/shroud-diff', __DIR__ . '/bin/shroud-dump', __DIR__ . '/docs/examples'])
    ->withRootFiles()
    ->withPhpSets(php84: true)
    ->withAttributesSets(phpunit: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, namedArgs: true, earlyReturn: true)
    ->withSkip([
        // PHPUnit attributes refuse named arguments (@no-named-arguments).
        ExplicitAttributeNamedArgsRector::class,
    ])
;
