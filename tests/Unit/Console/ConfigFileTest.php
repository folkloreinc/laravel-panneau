<?php

namespace Panneau\Tests\Unit\Console;

use Panneau\Console\ConfigFile;
use Panneau\Upgrade\Unknown;
use PHPUnit\Framework\TestCase;

class ConfigFileTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php

        return [
            'name' => 'Site',
            // The agents
            'agents' => [
                'mcp' => [
                    'enabled' => env('MCP', false),
                ],
            ],
            'empty' => [],
            'inline' => ['a' => 1]
        ];

        PHP;

    public function test_it_replaces_a_value_and_keeps_the_rest(): void
    {
        $file = new ConfigFile(self::CODE);

        $this->assertTrue($file->set('agents.mcp.enabled', 'true'));
        $this->assertFalse($file->set('agents.mcp.enabled', 'true'));

        $this->assertStringContainsString("// The agents\n    'agents' => [\n        'mcp' => [\n            'enabled' => true,\n", $file->code());
        $this->assertTrue($file->value('agents.mcp.enabled'));
    }

    public function test_it_adds_the_missing_keys_with_their_arrays(): void
    {
        $file = new ConfigFile(self::CODE);

        $file->set('agents.mcp.oauth.enabled', 'true');
        $file->set('empty.key', "'value'");
        $file->set('inline.b', '2');
        $file->set('new.deep.key', 'null');

        $this->assertStringContainsString("            'enabled' => env('MCP', false),\n            'oauth' => [\n                'enabled' => true,\n            ],\n        ],", $file->code());
        $config = eval('?>'.str_replace("env('MCP', false)", 'false', $file->code()));
        $this->assertSame(['enabled' => true], $config['agents']['mcp']['oauth']);
        $this->assertSame(['key' => 'value'], $config['empty']);
        $this->assertSame(['a' => 1, 'b' => 2], $config['inline']);
        $this->assertSame(['deep' => ['key' => null]], $config['new']);
        $this->assertSame('Site', $config['name']);
    }

    public function test_a_value_computed_by_code_is_unknown(): void
    {
        $file = new ConfigFile(self::CODE);

        $this->assertSame(Unknown::Value, $file->value('agents.mcp.enabled'));
        $this->assertNull($file->value('agents.mcp.guard'));
        $this->assertFalse($file->has('agents.mcp.guard'));
    }
}
