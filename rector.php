<?php

use Panneau\Rector\ClassesToMoveByHandRector;
use Panneau\Rector\FieldCallsRector;
use Panneau\Rector\ResourceIndexRector;
use Panneau\Rector\ResourcePropertiesRector;
use Rector\Config\RectorConfig;

// The Rector rules of the upgrade from laravel-panneau 1.3 and 1.4. In the project:
// vendor/bin/rector process app --config vendor/folklore/laravel-panneau/rector.php
return RectorConfig::configure()
    ->withRules([
        ResourcePropertiesRector::class,
        ResourceIndexRector::class,
        FieldCallsRector::class,
        ClassesToMoveByHandRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
