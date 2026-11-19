<?php

namespace Panneau\Tests\Feature\Definition;

use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\BlockType;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Resources\RequestCache;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceType;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use TestApp\Models\Entry;

/**
 * A block type is built once per request and locale (#246), however many repeaters have it: the
 * shared sets of the definition (panneau-js decision 0094) are built once, not only sent once.
 */
class BlockTypesOncePerRequestTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        CountedTextarea::$definitions = 0;
        SharedBlocks::reset();
        Panneau::resources([SharedBlocksResource::class]);
    }

    public function test_a_set_of_block_types_used_in_many_places_is_built_once(): void
    {
        $definition = $this->app->make(DefinitionBuilder::class)->build();

        $this->assertValidDefinition($definition);
        // The text block is in the repeater of each of the three types and in the stack block
        $this->assertSame(1, CountedTextarea::$definitions);
        $this->assertSame(['text', 'stack'], array_column($definition['blockTypeSets']['blocks'], 'id'));
        $resource = collect($definition['resources'])->firstWhere('id', 'shared-blocks');
        foreach ($resource['types'] as $type) {
            $this->assertSame(['name' => 'blocks', 'type' => 'repeater', 'typeSet' => 'blocks'], $type['fields'][0]);
        }
    }

    public function test_it_is_built_again_in_another_locale_and_another_request(): void
    {
        $this->app->make(DefinitionBuilder::class)->build();
        $this->app->setLocale('fr');
        $this->app->make(DefinitionBuilder::class)->build();

        $this->assertSame(2, CountedTextarea::$definitions);

        $this->app->make(RequestCache::class)->flush();
        $this->app->make(DefinitionBuilder::class)->build();

        $this->assertSame(3, CountedTextarea::$definitions);
    }

    public function test_changing_a_block_type_or_a_repeater_builds_it_again(): void
    {
        $text = BlockType::make('text', 'Text', [Textarea::make('body')]);
        $repeater = Repeater::make('blocks')->types([$text]);
        $this->assertSame(['body'], array_column($repeater->toDefinition()['types'][0]['fields'], 'name'));

        $text->fields([Textarea::make('intro')])->icon('text');
        $this->assertSame(['intro'], array_column($repeater->toDefinition()['types'][0]['fields'], 'name'));
        $this->assertSame('text', $repeater->toDefinition()['types'][0]['icon']);

        $repeater->types([$text, BlockType::make('quote', 'Quote', [Textarea::make('quote')])]);
        $this->assertSame(['text', 'quote'], array_column($repeater->toDefinition()['types'], 'id'));
    }
}

class CountedTextarea extends Textarea
{
    public static int $definitions = 0;

    public function toDefinition(): array
    {
        self::$definitions++;

        return parent::toDefinition();
    }
}

/**
 * The block types of the resource, given to each repeater as the same instances.
 */
final class SharedBlocks
{
    private static ?Repeater $blocks = null;

    public static function reset(): void
    {
        self::$blocks = null;
    }

    public static function repeater(): Repeater
    {
        if (self::$blocks === null) {
            $text = BlockType::make('text', 'Text', [CountedTextarea::make('body')]);
            self::$blocks = Repeater::make('blocks')->types([
                $text,
                BlockType::make('stack', 'Stack', [Repeater::make('children')->types([$text])]),
            ]);
        }

        return self::$blocks;
    }
}

class SharedBlocksResource extends Resource
{
    public function model(): string
    {
        return Entry::class;
    }

    public function fields(): array
    {
        return [Text::make('title')];
    }

    public function types(): array
    {
        return [new SharedBlocksPage('page'), new SharedBlocksPage('landing'), new SharedBlocksPage('promo')];
    }
}

class SharedBlocksPage extends ResourceType
{
    public function __construct(private readonly string $type) {}

    public function id(): string
    {
        return $this->type;
    }

    public function fields(): array
    {
        return [SharedBlocks::repeater()];
    }
}
