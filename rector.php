<?php

use Panneau\Rector\ClassesToMoveByHandRector;
use Panneau\Rector\FieldCallsRector;
use Panneau\Rector\ResourceIndexRector;
use Panneau\Rector\ResourcePropertiesRector;
use Rector\Config\RectorConfig;

// The Rector rules of the upgrade from laravel-panneau 1.3 and 1.4. In the project:
// vendor/bin/rector process app --config vendor/folklore/laravel-panneau/rector.php

// A cache of its own for each run, removed at its end. Rector skips the files it cached as
// unchanged while their contents stay the same, but the rules also read the project's parents
// in other files, and change from one release to the next: a shared cache would skip files they
// now convert. The rules run once or twice, so a cache gains nothing (#130).
$cache = sys_get_temp_dir().'/panneau-rector-'.bin2hex(random_bytes(8));
register_shutdown_function(function () use ($cache): void {
    if (! is_dir($cache)) {
        return;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($cache);
});

return RectorConfig::configure()
    ->withRules([
        ResourcePropertiesRector::class,
        ResourceIndexRector::class,
        FieldCallsRector::class,
        ClassesToMoveByHandRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withCache(cacheDirectory: $cache);
