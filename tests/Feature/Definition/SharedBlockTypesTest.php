<?php

namespace Panneau\Tests\Feature\Definition;

use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\BlockType;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceType;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use TestApp\Models\Entry;

/**
 * The blocks of every type of a resource are sent once (panneau-js decision 0094).
 */
class SharedBlockTypesTest extends TestCase
{
    use ValidatesProtocol;

    public function test_the_blocks_of_the_types_of_a_resource_are_a_shared_set(): void
    {
        Panneau::resources([LandingPagesResource::class]);

        $definition = $this->app->make(DefinitionBuilder::class)->build();

        $this->assertValidDefinition($definition);
        $pages = collect($definition['resources'])->firstWhere('id', 'landing-pages');
        foreach ($pages['types'] as $type) {
            $this->assertSame(['name' => 'blocks', 'type' => 'repeater', 'typeSet' => 'blocks'], $type['fields'][0]);
        }
        $this->assertSame(['text', 'stack'], array_column($definition['blockTypeSets']['blocks'], 'id'));
        // The stack holds blocks that appear once: they stay in its field
        $this->assertSame(['text'], array_column($definition['blockTypeSets']['blocks'][1]['fields'][0]['types'], 'id'));
    }
}

class LandingPagesResource extends Resource
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
        return [new LandingType, new PromoType];
    }

    public static function blocks(): Repeater
    {
        return Repeater::make('blocks')->types([
            BlockType::make('text', 'Text', [Textarea::make('body')]),
            BlockType::make('stack', 'Stack', [
                Repeater::make('children')->types([BlockType::make('text', 'Text', [Textarea::make('body')])]),
            ]),
        ]);
    }
}

class LandingType extends ResourceType
{
    public function fields(): array
    {
        return [LandingPagesResource::blocks()];
    }
}

class PromoType extends ResourceType
{
    public function fields(): array
    {
        return [LandingPagesResource::blocks()];
    }
}
