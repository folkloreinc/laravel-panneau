<?php

namespace Panneau\Tests\Unit\Definition;

use Panneau\Definition\BlockTypeSets;
use PHPUnit\Framework\TestCase;

/**
 * The lists of block types that repeat in a definition are sent once (panneau-js decision
 * 0094).
 */
class BlockTypeSetsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function text(): array
    {
        return ['id' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body', 'type' => 'textarea']]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stack(): array
    {
        return ['id' => 'stack', 'label' => 'Stack', 'fields' => [['name' => 'children', 'type' => 'repeater', 'types' => [self::text()]]]];
    }

    /**
     * @param  list<array<string, mixed>>  $types
     * @return array<string, mixed>
     */
    private static function blocks(array $types): array
    {
        return ['name' => 'blocks', 'type' => 'repeater', 'types' => $types];
    }

    /**
     * @param  list<array<string, mixed>>  $fieldsOfTypes
     * @return array<string, mixed>
     */
    private static function definition(array $fieldsOfTypes): array
    {
        return [
            'protocol' => 1,
            'resources' => [[
                'id' => 'pages',
                'fields' => [['name' => 'title', 'type' => 'text']],
                'types' => array_map(fn (array $field, int $index): array => ['id' => 'type-'.$index, 'label' => 'Type', 'fields' => [$field]], $fieldsOfTypes, array_keys($fieldsOfTypes)),
            ]],
        ];
    }

    public function test_the_lists_that_repeat_are_named_by_a_key(): void
    {
        $blocks = self::blocks([self::text(), self::stack()]);

        $shared = BlockTypeSets::share(self::definition([$blocks, $blocks, $blocks]));

        foreach ($shared['resources'][0]['types'] as $type) {
            $this->assertSame([['name' => 'blocks', 'type' => 'repeater', 'typeSet' => 'blocks']], $type['fields']);
        }
        // The list a stack holds is in the shared list once: it stays in its field
        $this->assertSame(['blocks' => [self::text(), self::stack()]], $shared['blockTypeSets']);
    }

    public function test_a_list_held_by_blocks_of_several_lists_is_shared(): void
    {
        $shared = BlockTypeSets::share(self::definition([self::blocks([self::text(), self::stack()]), self::blocks([self::stack()])]));

        // Each list of blocks appears once, the list of the stack in both
        $this->assertSame(['children' => [self::text()]], $shared['blockTypeSets']);
        $this->assertSame(['text', 'stack'], array_column($shared['resources'][0]['types'][0]['fields'][0]['types'], 'id'));
        $this->assertSame('children', $shared['resources'][0]['types'][1]['fields'][0]['types'][0]['fields'][0]['typeSet']);
    }

    public function test_a_list_that_appears_once_stays_in_its_field(): void
    {
        $quote = ['id' => 'quote', 'label' => 'Quote', 'fields' => []];
        $definition = self::definition([self::blocks([$quote]), self::blocks([self::stack()])]);

        $this->assertSame($definition, BlockTypeSets::share($definition));
    }

    public function test_two_different_lists_of_fields_of_the_same_name_get_two_keys(): void
    {
        $first = self::blocks([self::text()]);
        $second = self::blocks([self::text(), ['id' => 'quote', 'label' => 'Quote', 'fields' => []]]);

        $shared = BlockTypeSets::share(self::definition([$first, $first, $second, $second]));

        $this->assertSame(['blocks', 'blocks-2'], array_keys($shared['blockTypeSets']));
        $this->assertSame('blocks-2', $shared['resources'][0]['types'][3]['fields'][0]['typeSet']);
    }

    public function test_the_types_of_a_resource_and_other_types_lists_are_left_alone(): void
    {
        $link = ['name' => 'website', 'type' => 'link', 'settings' => ['types' => ['url', 'page']]];
        $definition = self::definition([$link, $link]);

        $this->assertSame($definition, BlockTypeSets::share($definition));
    }
}
