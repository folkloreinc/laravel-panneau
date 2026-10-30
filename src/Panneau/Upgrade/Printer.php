<?php

namespace Panneau\Upgrade;

use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;

/**
 * Prints the code of the project as it was written: functions and constants without the
 * leading backslash that resolving names gave them (`env()`, not `\env()`).
 *
 * @internal
 */
final class Printer extends Standard
{
    protected function pName_FullyQualified(Name\FullyQualified $node): string
    {
        return count($node->getParts()) === 1 ? $node->toString() : '\\'.$node->toString();
    }
}
