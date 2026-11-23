<?php

namespace Panneau\Tests\Unit\Agents;

use InvalidArgumentException;
use Panneau\Agents\ResourceDescription;
use PHPUnit\Framework\TestCase;

class ResourceDescriptionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function posts(): array
    {
        return [
            'id' => 'posts',
            'label' => ['singular' => 'post', 'plural' => 'posts'],
            'description' => 'Articles of the blog',
            'icon' => 'file',
            'open' => 'panel',
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'help' => 'Short', 'localized' => true, 'rules' => [['rule' => 'required'], ['rule' => 'max', 'value' => 120]], 'visibleWhen' => ['field' => 'x']],
                ['name' => 'password', 'type' => 'text', 'rules' => [['rule' => 'required', 'mode' => 'create']]],
                ['name' => 'status', 'type' => 'select', 'options' => [['value' => 'draft', 'label' => 'Draft', 'tone' => 'muted']]],
                ['name' => 'author', 'type' => 'relation', 'resource' => 'authors', 'multiple' => true],
                ['name' => 'url', 'type' => 'text', 'notInForm' => true],
                ['name' => 'locked', 'type' => 'text', 'abilities' => ['update' => false]],
                ['name' => 'secret', 'type' => 'text', 'abilities' => ['view' => false]],
                ['name' => 'seo', 'type' => 'group', 'fields' => [
                    ['name' => 'meta', 'type' => 'group', 'fields' => [['name' => 'title', 'type' => 'text', 'rules' => [['rule' => 'required']]]]],
                ]],
                ['name' => 'blocks', 'type' => 'repeater', 'types' => [
                    ['id' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'text', 'type' => 'textarea']]],
                ]],
            ],
            'types' => [
                ['id' => 'event', 'label' => 'Event', 'icon' => 'calendar', 'fields' => [['name' => 'date', 'type' => 'date']], 'abilities' => ['create' => false]],
            ],
            'form' => ['layout' => [['type' => 'section']]],
            'list' => [
                'columns' => [['id' => 'title']],
                'filters' => [
                    ['id' => 'status', 'type' => 'select', 'multiple' => true, 'options' => [['value' => 'draft', 'label' => 'Draft', 'tone' => 'muted']]],
                    ['id' => 'author_id', 'type' => 'relation', 'resource' => 'authors'],
                ],
                'sorts' => ['title'],
                'defaultSort' => '-title',
                'search' => true,
            ],
            'actions' => [
                ['id' => 'publish', 'label' => 'Publish', 'kind' => 'server', 'scope' => ['item', 'selection'], 'placement' => ['row'], 'icon' => 'send', 'confirm' => ['message' => 'Sure?'], 'fields' => [['name' => 'notify', 'type' => 'boolean', 'label' => 'Notify']], 'abilities' => ['run' => true]],
                ['id' => 'purge', 'label' => 'Purge', 'kind' => 'server', 'scope' => ['selection'], 'destructive' => true, 'dryRun' => true, 'abilities' => ['run' => true]],
                ['id' => 'archive', 'label' => 'Archive', 'kind' => 'server', 'scope' => ['selection'], 'abilities' => ['run' => false]],
                ['id' => 'preview', 'label' => 'Preview', 'kind' => 'link', 'scope' => ['item'], 'url' => '/posts/{id}'],
                ['id' => 'copy', 'label' => 'Copy', 'kind' => 'client', 'scope' => ['item'], 'abilities' => ['run' => true]],
            ],
            'abilities' => ['viewAny' => true, 'create' => true],
            'features' => ['search' => true],
        ];
    }

    public function test_it_lists_the_resources_the_user_can_list(): void
    {
        $description = (new ResourceDescription)->resources(['resources' => [
            $this->posts(),
            ['id' => 'tags', 'label' => ['singular' => 'tag', 'plural' => 'tags'], 'abilities' => ['viewAny' => true, 'create' => false]],
            ['id' => 'users', 'label' => ['singular' => 'user', 'plural' => 'users'], 'abilities' => ['viewAny' => false, 'create' => false]],
        ]]);

        $this->assertSame([
            ['id' => 'posts', 'label' => ['singular' => 'post', 'plural' => 'posts'], 'description' => 'Articles of the blog', 'abilities' => ['viewAny' => true, 'create' => true]],
            ['id' => 'tags', 'label' => ['singular' => 'tag', 'plural' => 'tags'], 'abilities' => ['viewAny' => true, 'create' => false]],
        ], $description);
    }

    public function test_it_describes_the_fields(): void
    {
        $description = (new ResourceDescription)->resource($this->posts(), ['fields']);

        $this->assertSame(['id', 'label', 'description', 'abilities', 'fields', 'types'], array_keys($description));
        $this->assertSame([
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'help' => 'Short', 'localized' => true, 'required' => true],
            ['name' => 'password', 'type' => 'text', 'required' => 'create'],
            ['name' => 'status', 'type' => 'select', 'options' => [['value' => 'draft', 'label' => 'Draft']]],
            ['name' => 'author', 'type' => 'relation', 'multiple' => true, 'resource' => 'authors'],
            ['name' => 'url', 'type' => 'text', 'readOnly' => true],
            ['name' => 'locked', 'type' => 'text', 'readOnly' => true],
            ['name' => 'seo', 'type' => 'group', 'fields' => [
                ['name' => 'meta', 'type' => 'group', 'fields' => [['name' => 'title', 'type' => 'text', 'required' => true]]],
            ]],
            ['name' => 'blocks', 'type' => 'repeater', 'types' => [
                ['id' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'text', 'type' => 'textarea']]],
            ]],
        ], $description['fields']);
        $this->assertSame([
            ['id' => 'event', 'label' => 'Event', 'fields' => [['name' => 'date', 'type' => 'date']], 'create' => false],
        ], $description['types']);
    }

    public function test_it_gives_back_the_shared_block_types_of_a_repeater(): void
    {
        $resource = ['id' => 'landings', 'fields' => [['name' => 'blocks', 'type' => 'repeater', 'typeSet' => 'blocks']]];
        $sets = [
            'blocks' => [['id' => 'stack', 'label' => 'Stack', 'fields' => [['name' => 'children', 'type' => 'repeater', 'typeSet' => 'children']]]],
            'children' => [['id' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body', 'type' => 'textarea']]]],
        ];

        $description = (new ResourceDescription)->resource($resource, ['fields'], blockTypeSets: $sets);

        $this->assertSame([['name' => 'blocks', 'type' => 'repeater', 'types' => [
            ['id' => 'stack', 'label' => 'Stack', 'fields' => [['name' => 'children', 'type' => 'repeater', 'types' => [
                ['id' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body', 'type' => 'textarea']]],
            ]]]],
        ]]], $description['fields']);
    }

    public function test_it_describes_the_list(): void
    {
        $description = (new ResourceDescription)->resource($this->posts(), ['list']);

        $this->assertSame([
            'filters' => [
                ['id' => 'status', 'type' => 'select', 'multiple' => true, 'options' => [['value' => 'draft', 'label' => 'Draft']]],
                ['id' => 'author_id', 'type' => 'relation', 'resource' => 'authors'],
            ],
            'sorts' => ['title'],
            'defaultSort' => '-title',
            'search' => true,
        ], $description['list']);
        $this->assertArrayNotHasKey('fields', $description);
        $this->assertArrayNotHasKey('actions', $description);
    }

    public function test_it_describes_the_server_actions_the_user_can_run(): void
    {
        $description = (new ResourceDescription)->resource($this->posts(), ['actions']);

        $this->assertSame([
            ['id' => 'publish', 'label' => 'Publish', 'scope' => ['item', 'selection'], 'fields' => [['name' => 'notify', 'type' => 'boolean', 'label' => 'Notify']], 'confirmation' => true],
            ['id' => 'purge', 'label' => 'Purge', 'scope' => ['selection'], 'destructive' => true, 'dryRun' => true, 'confirmation' => true],
            ['id' => 'delete', 'label' => 'Delete', 'scope' => ['item', 'selection'], 'destructive' => true, 'dryRun' => true, 'confirmation' => true],
        ], $description['actions']);
    }

    public function test_it_adds_the_built_in_actions_of_the_resource(): void
    {
        $description = (new ResourceDescription)->resource($this->posts(), ['actions'], hasTrash: true, duplicable: true);

        $this->assertSame([
            ['id' => 'delete', 'label' => 'Delete', 'scope' => ['item', 'selection'], 'destructive' => true, 'undoable' => true, 'dryRun' => true],
            ['id' => 'restore', 'label' => 'Restore', 'scope' => ['item', 'selection']],
            ['id' => 'duplicate', 'label' => 'Duplicate', 'scope' => ['item']],
        ], array_slice($description['actions'], 2));
    }

    public function test_it_leaves_out_the_built_in_actions_the_user_cant_run(): void
    {
        $resource = $this->posts();
        // What `DefinitionBuilder` gives a user who can't delete, and a resource without duplicate
        $resource['actions'] = [['id' => 'delete', 'kind' => 'server', 'scope' => ['item', 'selection'], 'abilities' => ['run' => false]]];
        $resource['itemActions'] = ['show', 'edit', 'delete'];

        $description = (new ResourceDescription)->resource($resource, ['actions'], duplicable: true);
        $this->assertArrayNotHasKey('actions', $description);

        $resource['actions'] = [];
        $resource['itemActions'] = ['show', 'duplicate'];
        $resource['abilities']['create'] = false;
        $description = (new ResourceDescription)->resource($resource, ['actions'], duplicable: true);
        $this->assertArrayNotHasKey('actions', $description);
    }

    public function test_it_leaves_out_what_the_ui_needs(): void
    {
        $json = json_encode((new ResourceDescription)->resource($this->posts()));

        foreach (['layout', 'columns', 'icon', 'placement', 'visibleWhen', 'tone', 'open', 'features', 'preview', 'copy', 'archive'] as $key) {
            $this->assertStringNotContainsString('"'.$key.'"', (string) $json);
        }
    }

    public function test_an_unknown_part_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown part "items"');

        (new ResourceDescription)->resource($this->posts(), ['fields', 'items']);
    }
}
