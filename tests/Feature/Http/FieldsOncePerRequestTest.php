<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Panneau\Fields\Text;
use Panneau\Resources\FieldCache;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Entry;
use TestApp\Panneau\Resources\Entries\ArticleType;
use TestApp\Panneau\Resources\Entries\ConferenceType;
use TestApp\Panneau\Resources\Entries\EventType;
use TestApp\Panneau\Resources\Entries\HomeType;
use TestApp\Panneau\Resources\EntriesResource;

/**
 * The fields and types of a resource are built once per request (#234): fields that do work
 * while they are built, such as options from the database, don't slow every request down.
 */
class FieldsOncePerRequestTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Panneau::allowWithoutPolicies();
        Panneau::resources([CountedEntriesResource::class]);
        CountedEntriesResource::$fields = 0;
        CountedEntriesResource::$types = 0;
        CountedArticleType::$fields = 0;
        foreach (range(1, 20) as $index) {
            Entry::query()->create(['type' => $index % 2 === 0 ? 'article' : 'event', 'title' => 'Entry '.$index, 'starts_on' => '2026-01-01']);
        }
    }

    public function test_a_list_builds_the_fields_and_types_once(): void
    {
        $this->getJson(self::API.'/resources/counted-entries?perPage=20')->assertOk()->assertJsonCount(20, 'data');

        $this->assertSame(1, CountedEntriesResource::$fields);
        $this->assertSame(1, CountedEntriesResource::$types);
        $this->assertSame(1, CountedArticleType::$fields);
    }

    public function test_an_item_and_the_definition_build_the_fields_and_types_once(): void
    {
        $article = Entry::query()->where('type', 'article')->firstOrFail();

        $this->getJson(self::API.'/resources/counted-entries/'.$article->id)->assertOk();

        $this->assertSame(1, CountedEntriesResource::$fields);
        $this->assertSame(1, CountedArticleType::$fields);

        $this->getJson(self::API.'/definition')->assertOk();

        // Again for the next request
        $this->assertSame(2, CountedEntriesResource::$fields);
        $this->assertSame(2, CountedArticleType::$fields);
    }

    public function test_the_fields_are_built_again_in_another_locale(): void
    {
        $resource = Panneau::resource('counted-entries');
        $this->assertNotNull($resource);

        $resource->allFields();
        $resource->allFields();
        $this->assertSame(1, CountedEntriesResource::$fields);

        $this->app['translator']->addLines(['*.Title' => 'Titre'], 'fr');
        $this->app->setLocale('fr');
        $this->assertSame('Titre', $resource->field('title')?->toDefinition()['label'] ?? null);
        $this->app->setLocale('en');
        $this->assertSame('Title', $resource->field('title')?->toDefinition()['label'] ?? null);

        $this->assertSame(2, CountedEntriesResource::$fields);
    }

    public function test_the_fields_are_built_again_for_another_user_or_after_a_flush(): void
    {
        $resource = Panneau::resource('counted-entries');
        $this->assertNotNull($resource);

        $resource->allFields();
        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com']));
        $resource->allFields();
        $this->assertSame(2, CountedEntriesResource::$fields);

        $resource->flushFields();
        $resource->allFields();
        $this->assertSame(3, CountedEntriesResource::$fields);

        $this->app->make(FieldCache::class)->flush();
        $resource->allFields();
        $this->assertSame(4, CountedEntriesResource::$fields);
    }

    public function test_the_checks_of_the_types_still_throw(): void
    {
        $resource = new class extends EntriesResource
        {
            public function fields(): array
            {
                return [Text::make('title'), Text::make('location')->onlyForTypes('missing')];
            }
        };

        foreach ([1, 2] as $attempt) {
            try {
                $resource->resourceTypes();
                $this->fail('The type "missing" should be refused.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('names the type "missing"', $exception->getMessage());
            }
        }
    }
}

class CountedEntriesResource extends EntriesResource
{
    public static int $fields = 0;

    public static int $types = 0;

    public function fields(): array
    {
        self::$fields++;

        return [
            Text::make('title')->label(__('Title'))->required(),
            Text::make('location')->onlyForTypes(EventType::class),
        ];
    }

    public function types(): array
    {
        self::$types++;

        return [EventType::class, ConferenceType::class, CountedArticleType::class, new HomeType];
    }
}

class CountedArticleType extends ArticleType
{
    public static int $fields = 0;

    public function id(): string
    {
        return 'article';
    }

    public function fields(): array
    {
        self::$fields++;

        return parent::fields();
    }
}
