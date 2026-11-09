<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Fields\Field;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Http\Problem;
use Panneau\Layout\Field as LayoutField;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\ResourceType;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Models\Author;
use TestApp\Models\Entry;
use TestApp\Panneau\Resources\EntriesResource;

/**
 * Resource types (panneau-js decision 0077): items of one table told apart by their `type`, each
 * type with fields of its own.
 */
class ResourceTypesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Entries have no policy
        Panneau::allowWithoutPolicies();
        Panneau::resources([EntriesResource::class]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEntry(string $type, array $attributes = []): Entry
    {
        return Entry::query()->create($attributes + ['type' => $type, 'title' => 'An entry']);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $resource = 'entries'): array
    {
        return collect($this->getJson(self::API.'/definition')->assertOk()->json('resources'))->firstWhere('id', $resource);
    }

    public function test_the_definition_has_the_types_their_fields_and_abilities(): void
    {
        $entries = $this->definition();

        $this->assertSame([
            [
                'id' => 'event',
                'label' => 'Event',
                'description' => 'Something that happens on a day.',
                'icon' => 'calendar',
                'fields' => [
                    // The fields of the resource for some types only, then the type's
                    ['name' => 'location', 'type' => 'text'],
                    ['name' => 'intro', 'type' => 'textarea'],
                    ['name' => 'starts_on', 'type' => 'date', 'rules' => [['rule' => 'required']]],
                    ['name' => 'slug', 'type' => 'slug', 'from' => 'title', 'check' => true],
                ],
            ],
            [
                // A type that extends EventType: its fields, and the resource's for EventType
                'id' => 'conference',
                'label' => 'Conference',
                'description' => 'An event with a venue.',
                'icon' => 'calendar',
                'fields' => [
                    ['name' => 'location', 'type' => 'text'],
                    ['name' => 'intro', 'type' => 'textarea'],
                    ['name' => 'starts_on', 'type' => 'date', 'rules' => [['rule' => 'required']]],
                    ['name' => 'slug', 'type' => 'slug', 'from' => 'title', 'check' => true],
                    ['name' => 'venue', 'type' => 'text', 'rules' => [['rule' => 'required']]],
                ],
            ],
            [
                'id' => 'article',
                'label' => 'Article',
                'fields' => [
                    ['name' => 'intro', 'type' => 'textarea'],
                    ['name' => 'summary', 'type' => 'textarea'],
                    ['name' => 'author', 'type' => 'relation', 'resource' => 'authors'],
                    ['name' => 'secret', 'type' => 'text', 'abilities' => ['view' => false]],
                ],
            ],
            ['id' => 'home', 'label' => 'Home page', 'fields' => [], 'abilities' => ['create' => false]],
        ], $entries['types']);
        // The fields for some types only aren't the resource's
        $this->assertSame(['title', 'type'], array_column($entries['fields'], 'name'));
        // Without a layout, forms show them where fields() declares them: the app leaves out
        // those of the other types, and adds the type's own fields at the end
        $this->assertSame([
            ['type' => 'field', 'name' => 'title'],
            ['type' => 'field', 'name' => 'location'],
            ['type' => 'field', 'name' => 'intro'],
        ], $entries['form']['layout']);
    }

    public function test_a_type_field_and_filter_show_and_filter_by_type(): void
    {
        $entries = $this->definition();
        $options = [
            ['value' => 'event', 'label' => 'Event'],
            ['value' => 'conference', 'label' => 'Conference'],
            ['value' => 'article', 'label' => 'Article'],
            ['value' => 'home', 'label' => 'Home page'],
        ];

        $this->assertSame(
            ['name' => 'type', 'type' => 'select', 'label' => 'Type', 'notInForm' => true, 'options' => $options],
            collect($entries['fields'])->firstWhere('name', 'type'),
        );
        $this->assertSame(
            ['id' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => $options, 'multiple' => true],
            $entries['list']['filters'][0],
        );

        $this->createEntry('event', ['title' => 'Concert', 'starts_on' => '2026-12-01']);
        $this->createEntry('article', ['title' => 'News']);
        $this->createEntry('home', ['title' => 'Home']);

        $list = $this->getJson(self::API.'/resources/entries?filter[type]=event')->assertOk();
        $this->assertSame(['Concert'], array_column($list->json('data'), 'title'));
        $list->assertJsonPath('data.0.type', 'event');
        $this->getJson(self::API.'/resources/entries?filter[type]=missing')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_an_item_has_its_type_and_the_fields_of_its_type(): void
    {
        $event = $this->createEntry('event', ['starts_on' => '2026-12-01', 'slug' => 'concert', 'summary' => 'Not an event field']);
        $author = Author::query()->create(['name' => 'Ada']);
        $article = $this->createEntry('article', ['summary' => 'A summary', 'secret' => 'hidden', 'author_id' => $author->id]);

        $data = $this->getJson(self::API.'/resources/entries/'.$event->id)->assertOk()->json('data');
        $this->assertSame('event', $data['type']);
        $this->assertSame('2026-12-01', $data['starts_on']);
        $this->assertSame('concert', $data['slug']);
        $this->assertArrayNotHasKey('summary', $data);

        $data = $this->getJson(self::API.'/resources/entries/'.$article->id)->assertOk()->json('data');
        $this->assertSame('article', $data['type']);
        $this->assertSame('A summary', $data['summary']);
        // A relation of the type is a reference
        $this->assertSame(['id' => (string) $author->id, 'label' => 'Ada'], $data['author']);
        // A field of the type the user can't see
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('starts_on', $data);
    }

    public function test_fields_for_some_types_and_types_that_extend_others(): void
    {
        $response = $this->postJson(self::API.'/resources/entries', [
            'type' => 'conference',
            'title' => 'Laracon',
            'location' => 'Montréal',
            'intro' => 'Two days',
            'starts_on' => '2026-12-01',
        ])->assertStatus(422);
        // The fields of the type it extends, and its own
        $this->assertSame(['venue'], array_keys($response->json('errors')));

        $data = $this->postJson(self::API.'/resources/entries', [
            'type' => 'conference',
            'title' => 'Laracon',
            'location' => 'Montréal',
            'intro' => 'Two days',
            'starts_on' => '2026-12-01',
            'venue' => 'Palais des congrès',
        ])->assertCreated()->json('data');
        $this->assertSame(['Montréal', 'Two days', 'Palais des congrès'], [$data['location'], $data['intro'], $data['venue']]);

        // An article has the intro, not the location; the home page neither
        $article = $this->postJson(self::API.'/resources/entries', ['type' => 'article', 'title' => 'News', 'location' => 'Ignored', 'intro' => 'Read'])
            ->assertCreated()
            ->json('data');
        $this->assertSame('Read', $article['intro']);
        $this->assertArrayNotHasKey('location', $article);
        $this->assertNull(Entry::query()->findOrFail($article['id'])->location);

        $home = $this->createEntry('home', ['intro' => 'Not a home page field', 'location' => 'Nor this']);
        $data = $this->patchJson(self::API.'/resources/entries/'.$home->id, ['intro' => 'Ignored'])->assertOk()->json('data');
        $this->assertArrayNotHasKey('intro', $data);
        $this->assertArrayNotHasKey('location', $data);
        $this->assertSame('Not a home page field', $home->refresh()->intro);
    }

    public function test_an_item_asked_for_some_fields_still_has_its_type(): void
    {
        $this->createEntry('event', ['starts_on' => '2026-12-01']);

        $this->getJson(self::API.'/resources/entries?fields=title')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'event')
            ->assertJsonMissingPath('data.0.starts_on');
    }

    public function test_an_item_without_a_type_has_the_fields_of_the_resource(): void
    {
        $entry = Entry::query()->create(['title' => 'Untyped', 'starts_on' => '2026-12-01']);

        $data = $this->getJson(self::API.'/resources/entries/'.$entry->id)->assertOk()->json('data');
        $this->assertArrayNotHasKey('type', $data);
        $this->assertArrayNotHasKey('starts_on', $data);
        $this->assertSame('Untyped', $data['title']);
    }

    public function test_create_saves_the_type_and_the_fields_of_the_type(): void
    {
        $response = $this->postJson(self::API.'/resources/entries', [
            'type' => 'event',
            'title' => 'Concert',
            'starts_on' => '2026-12-01',
            // A field of another type
            'summary' => 'Ignored',
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'event')->assertJsonPath('data.starts_on', '2026-12-01');
        $entry = Entry::query()->findOrFail($response->json('data.id'));
        $this->assertSame('event', $entry->type);
        $this->assertSame('2026-12-01', $entry->starts_on?->format('Y-m-d'));
        $this->assertNull($entry->summary);
    }

    public function test_create_validates_the_fields_of_the_type(): void
    {
        $response = $this->postJson(self::API.'/resources/entries', ['type' => 'event'])->assertStatus(422);

        $response->assertHeader('Content-Type', Problem::CONTENT_TYPE);
        $this->assertEqualsCanonicalizing(['title', 'starts_on'], array_keys($response->json('errors')));
        // An article has no required field of its own; its relation is saved
        $author = Author::query()->create(['name' => 'Ada']);
        $this->postJson(self::API.'/resources/entries', ['type' => 'article', 'title' => 'News', 'author' => (string) $author->id])
            ->assertCreated()
            ->assertJsonPath('data.author', ['id' => (string) $author->id, 'label' => 'Ada']);
    }

    public function test_create_needs_a_type_of_the_resource(): void
    {
        foreach ([[], ['type' => 'missing'], ['type' => ['event']]] as $body) {
            $response = $this->postJson(self::API.'/resources/entries', $body + ['title' => 'New', 'starts_on' => '2026-12-01'])
                ->assertStatus(422)
                ->assertJsonPath('type', Problem::VALIDATION);
            $this->assertSame(['type'], array_keys($response->json('errors')));
        }
        $this->assertSame(0, Entry::query()->count());
    }

    public function test_a_type_that_cant_be_created_is_forbidden(): void
    {
        $this->postJson(self::API.'/resources/entries', ['type' => 'home', 'title' => 'Home'])->assertForbidden();

        $this->assertSame(0, Entry::query()->count());
    }

    public function test_update_ignores_the_type_and_validates_the_fields_of_the_item_type(): void
    {
        $event = $this->createEntry('event', ['starts_on' => '2026-12-01']);

        $this->patchJson(self::API.'/resources/entries/'.$event->id, ['type' => 'article', 'starts_on' => '2026-12-24', 'summary' => 'Ignored'])
            ->assertOk()
            ->assertJsonPath('data.type', 'event')
            ->assertJsonPath('data.starts_on', '2026-12-24');
        $event->refresh();
        $this->assertSame('event', $event->type);
        $this->assertNull($event->summary);

        $this->patchJson(self::API.'/resources/entries/'.$event->id, ['starts_on' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_on']);
    }

    public function test_an_item_of_a_type_that_cant_be_created_is_edited(): void
    {
        $home = $this->createEntry('home', ['title' => 'Home']);

        $this->patchJson(self::API.'/resources/entries/'.$home->id, ['title' => 'Welcome'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Welcome')
            ->assertJsonPath('data.type', 'home');
    }

    public function test_an_item_of_a_type_that_cant_be_created_isnt_duplicated(): void
    {
        $home = $this->createEntry('home', ['title' => 'Home']);
        $event = $this->createEntry('event', ['starts_on' => '2026-12-01', 'slug' => 'concert']);

        $this->getJson(self::API.'/resources/entries/'.$home->id)->assertOk()->assertJsonPath('data.abilities.duplicate', false);
        $this->postJson(self::API.'/resources/entries/'.$home->id.'/duplicate')->assertForbidden();

        // The copy keeps its type, and a free value for the unique slug of the type
        $this->getJson(self::API.'/resources/entries/'.$event->id)->assertOk()->assertJsonPath('data.abilities.duplicate', true);
        $this->postJson(self::API.'/resources/entries/'.$event->id.'/duplicate')
            ->assertCreated()
            ->assertJsonPath('data.type', 'event')
            ->assertJsonPath('data.slug', 'concert-copy');
    }

    public function test_the_fields_of_a_type_check_their_value(): void
    {
        $event = $this->createEntry('event', ['starts_on' => '2026-12-01', 'slug' => 'concert']);

        $this->postJson(self::API.'/resources/entries/fields/slug/check', ['value' => 'concert'])
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('suggestion', 'concert-2');
        $this->postJson(self::API.'/resources/entries/fields/slug/check', ['value' => 'other', 'values' => ['type' => 'event']])
            ->assertOk()
            ->assertJsonPath('available', true);
        // The item's own slug is free
        $this->postJson(self::API.'/resources/entries/fields/slug/check', ['value' => 'concert', 'id' => (string) $event->id])
            ->assertOk()
            ->assertJsonPath('available', true);

        // An item of a type without the field
        $article = $this->createEntry('article');
        $this->postJson(self::API.'/resources/entries/fields/slug/check', ['value' => 'concert', 'id' => (string) $article->id])->assertNotFound();
        $this->postJson(self::API.'/resources/entries/fields/summary/check', ['value' => 'x'])->assertNotFound();
    }

    public function test_a_declared_type_field_and_filter_are_kept(): void
    {
        Panneau::resources([new class extends EntriesResource
        {
            public function id(): string
            {
                return 'custom-entries';
            }

            public function fields(): array
            {
                return [
                    Text::make('title')->required(),
                    Select::make('type')->label('Kind')->options(['event' => 'Event'])->notInForm(),
                ];
            }

            public function layout(): array
            {
                return [LayoutField::make('intro'), LayoutField::make('title')];
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->filters([SelectFilter::make('type')->label('Kind')->options(['event' => 'Event'])]);
            }
        }]);

        $entries = $this->definition('custom-entries');
        $this->assertSame('Kind', collect($entries['fields'])->firstWhere('name', 'type')['label']);
        $this->assertSame(['type'], array_column($entries['list']['filters'], 'id'));
        $this->assertSame('Kind', $entries['list']['filters'][0]['label']);
        // A declared layout is kept
        $this->assertSame(['intro', 'title'], array_column($entries['form']['layout'], 'name'));
    }

    /**
     * @return array<string, array{\Closure(): array<int, mixed>, \Closure(): array<int, mixed>, string}>
     */
    public static function invalidTypes(): array
    {
        $type = fn (string $id, array $fields): ResourceType => new class($id, $fields) extends ResourceType
        {
            /**
             * @param  list<Field>  $ownFields
             */
            public function __construct(private string $ownId, private array $ownFields) {}

            public function id(): string
            {
                return $this->ownId;
            }

            public function fields(): array
            {
                return $this->ownFields;
            }
        };

        return [
            'a field of the resource' => [
                fn (): array => [Text::make('title')],
                fn (): array => [$type('event', [Text::make('title')])],
                'The type "event" of the resource "invalid" has a field "title": the resource has a field of that name.',
            ],
            'a field named type' => [
                fn (): array => [Text::make('title')],
                fn (): array => [$type('event', [Text::make('type')])],
                'The type "event" of the resource "invalid" has a field "type": it holds the type of the item.',
            ],
            'two fields of the same name' => [
                fn (): array => [Text::make('title')],
                fn (): array => [$type('event', [Text::make('date'), Text::make('date')])],
                'The type "event" of the resource "invalid" has a field "date": the type has two fields of that name.',
            ],
            'two types of the same id' => [
                fn (): array => [Text::make('title')],
                fn (): array => [$type('event', []), $type('event', [])],
                'The resource "invalid" has a duplicate type id "event".',
            ],
            'a field for a type the resource doesn\'t have' => [
                fn (): array => [Text::make('title'), Text::make('location')->onlyForTypes('missing')],
                fn (): array => [$type('event', [])],
                'The field "location" of the resource "invalid" names the type "missing", which the resource doesn\'t have.',
            ],
            'a type field for some types' => [
                fn (): array => [Text::make('title'), Select::make('type')->notInForm()->exceptForTypes('event')],
                fn (): array => [$type('event', [])],
                'The field "type" of the resource "invalid" holds the type of the item: it is for every type.',
            ],
            'a type field named like a field for some types' => [
                fn (): array => [Text::make('title'), Text::make('location')->onlyForTypes('event')],
                fn (): array => [$type('event', [Text::make('location')])],
                'The type "event" of the resource "invalid" has a field "location": the resource has a field of that name.',
            ],
            'fields for some types without types' => [
                fn (): array => [Text::make('title'), Text::make('location')->onlyForTypes('event')],
                fn (): array => [],
                'The resource "invalid" has fields for some types only, and no types().',
            ],
            'a type field in forms' => [
                fn (): array => [Text::make('title'), Select::make('type')],
                fn (): array => [$type('event', [])],
                'The field "type" of the resource "invalid" holds the type of the item, set when it is created: make it notInForm().',
            ],
        ];
    }

    /**
     * @param  \Closure(): array<int, mixed>  $fields
     * @param  \Closure(): array<int, mixed>  $types
     */
    #[DataProvider('invalidTypes')]
    public function test_invalid_types_throw_when_the_admin_loads(\Closure $fields, \Closure $types, string $message): void
    {
        Panneau::resources([new class($fields(), $types()) extends EntriesResource
        {
            /**
             * @param  array<int, mixed>  $ownFields
             * @param  array<int, mixed>  $ownTypes
             */
            public function __construct(private array $ownFields, private array $ownTypes) {}

            public function id(): string
            {
                return 'invalid';
            }

            public function fields(): array
            {
                return $this->ownFields;
            }

            public function types(): array
            {
                return $this->ownTypes;
            }
        }]);

        $this->withoutExceptionHandling();
        $this->expectExceptionMessage($message);
        $this->getJson(self::API.'/definition');
    }

    public function test_a_type_can_depend_on_the_user(): void
    {
        $type = new class extends ResourceType
        {
            public function id(): string
            {
                return 'restricted';
            }

            public function fields(): array
            {
                return [];
            }

            public function canCreate(?Authenticatable $user): bool
            {
                return $user?->getAuthIdentifier() === 2;
            }
        };
        Panneau::resources([new class($type) extends EntriesResource
        {
            public function __construct(private ResourceType $restricted) {}

            public function id(): string
            {
                return 'restricted-entries';
            }

            public function fields(): array
            {
                return [Text::make('title')];
            }

            public function types(): array
            {
                return [$this->restricted];
            }
        }]);

        $definition = $this->definition('restricted-entries');
        $this->assertSame(['create' => false], $definition['types'][0]['abilities']);
        // No field for some types only: no layout
        $this->assertArrayNotHasKey('form', $definition);
        $this->postJson(self::API.'/resources/restricted-entries', ['type' => 'restricted', 'title' => 'New'])->assertForbidden();
    }
}
