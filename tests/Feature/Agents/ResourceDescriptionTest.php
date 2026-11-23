<?php

namespace Panneau\Tests\Feature\Agents;

use Illuminate\Auth\GenericUser;
use Panneau\Agents\ResourceDescription;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Resources\ResourceRegistry;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;
use TestApp\Conformance\ConformanceLandingsResource;
use TestApp\Panneau\Resources\ArticlesResource;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;

class ResourceDescriptionTest extends TestCase
{
    public function test_it_describes_a_resource_from_the_definition_of_the_user(): void
    {
        Panneau::resources([ImagesResource::class, DocumentsResource::class, ArticlesResource::class]);
        Panneau::allowWithoutPolicies();
        $registry = $this->app->make(ResourceRegistry::class);
        $articles = $registry->find('articles');
        $this->assertNotNull($articles);
        $user = new GenericUser(['id' => 1]);

        $definition = $this->app->make(DefinitionBuilder::class)->resource($articles, $user);
        $description = (new ResourceDescription)->resource($definition, hasTrash: $registry->hasTrash($articles), duplicable: $registry->duplicable($articles));

        $this->assertSame(['id', 'label', 'abilities', 'fields', 'list', 'actions'], array_keys($description));
        $this->assertSame(['name' => 'title', 'type' => 'text', 'required' => true], $description['fields'][0]);
        $this->assertSame(['name' => 'cover', 'type' => 'media', 'resource' => 'images'], $description['fields'][1]);
        $this->assertSame([['id' => 'image', 'label' => 'Image', 'fields' => [
            ['name' => 'image', 'type' => 'media', 'required' => true, 'resource' => 'images'],
        ]]], $description['fields'][5]['types']);
        $this->assertSame(['sorts' => ['title']], $description['list']);
        $this->assertSame([
            ['id' => 'delete', 'label' => 'Delete', 'scope' => ['item', 'selection'], 'destructive' => true, 'dryRun' => true, 'confirmation' => true],
        ], $description['actions']);
        $this->assertSame([['id' => 'articles', 'label' => ['singular' => 'article', 'plural' => 'articles'], 'abilities' => ['viewAny' => true, 'create' => true]]], (new ResourceDescription)->resources(['resources' => [$definition]]));
    }

    public function test_it_gives_back_the_block_types_the_definition_shares(): void
    {
        Panneau::resources([ConformanceLandingsResource::class]);
        Panneau::allowWithoutPolicies();

        $definition = $this->app->make(DefinitionBuilder::class)->build(new GenericUser(['id' => 1]));
        $this->assertArrayHasKey('blockTypeSets', $definition);
        $description = (new ResourceDescription)->resource($definition['resources'][0], ['fields'], blockTypeSets: $definition['blockTypeSets']);

        $this->assertCount(2, $description['types']);
        foreach ($description['types'] as $type) {
            $blocks = array_column($type['fields'], null, 'name')['blocks'];
            $this->assertSame(['text', 'stack'], array_column($blocks['types'], 'id'));
            $this->assertSame([['id' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body', 'type' => 'textarea']]]], $blocks['types'][1]['fields'][0]['types']);
        }
    }
}
