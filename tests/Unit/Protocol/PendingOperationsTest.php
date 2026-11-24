<?php

namespace Panneau\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;

/**
 * The operations of tests/protocol/pending.openapi.json wait for the protocol: once
 * openapi.json documents one, it leaves the pending file, and the tests check it against the
 * protocol's own.
 */
class PendingOperationsTest extends TestCase
{
    public function test_no_pending_operation_is_in_the_protocol(): void
    {
        $protocol = json_decode((string) file_get_contents(__DIR__.'/../../protocol/openapi.json'), true);
        $pending = json_decode((string) file_get_contents(__DIR__.'/../../protocol/pending.openapi.json'), true);
        $this->assertIsArray($protocol);
        $this->assertIsArray($pending);

        foreach ($pending['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $this->assertArrayNotHasKey($method, $protocol['paths'][$path] ?? [], sprintf('%s %s is in openapi.json: remove it from pending.openapi.json.', strtoupper($method), $path));
            }
        }
        foreach (array_keys($pending['components']['schemas'] ?? []) as $schema) {
            $this->assertArrayNotHasKey($schema, $protocol['components']['schemas'], sprintf('The schema %s is in openapi.json: remove it from pending.openapi.json.', $schema));
        }
    }
}
