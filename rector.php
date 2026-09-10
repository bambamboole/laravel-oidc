<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/packages/server',
        __DIR__.'/packages/client',
        __DIR__.'/packages/ui',
        __DIR__.'/tests',
        __DIR__.'/workbench/app',
    ])
    ->withCache(cacheDirectory: __DIR__.'/.rector-cache')
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
