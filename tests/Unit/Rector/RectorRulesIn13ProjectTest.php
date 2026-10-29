<?php

namespace Panneau\Tests\Unit\Rector;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * The rules, run in a 1.4 project (tests/fixture/upgrade/v1.3): classes that extend 1.x classes
 * through the project's own classes.
 */
class RectorRulesIn13ProjectTest extends AbstractRectorTestCase
{
    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = (string) getcwd();
        chdir(__DIR__.'/../../fixture/upgrade/v1.3');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);

        parent::tearDown();
    }

    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/Project13Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/../../../rector.php';
    }
}
