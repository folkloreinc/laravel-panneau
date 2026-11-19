<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use LogicException;
use Panneau\Definition\InterfaceLocales;
use Panneau\Fields\Field;
use Panneau\Fields\Link;
use Panneau\Fields\Text;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Media\MediaReferences;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\ResourceRegistry;
use Panneau\Support\Facade as Panneau;
use TestApp\Models\Author;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;

/**
 * What Panneau derives from resources lasts one request (#245): a long-lived process, such as
 * Octane or a queue worker, serves each request in its own locale and for its own user, and
 * serializing outside HTTP, as a tool of an agent does (decision 0022), uses the request it is
 * given, never the one the container holds.
 */
class RequestLifetimeTest extends HttpTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('panneau.interface_locales', ['fr' => 'Français', 'en' => 'English']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([TranslatedPostsResource::class, OwnAuthorsResource::class]);
        $this->app['translator']->addLines(['*.Status' => 'Statut', '*.Draft' => 'Brouillon', '*.Title' => 'Titre'], 'fr');
    }

    public function test_a_list_is_described_in_the_locale_of_each_request(): void
    {
        foreach (['fr' => ['Statut', 'Brouillon', 'Titre'], 'en' => ['Status', 'Draft', 'Title'], 'fr ' => ['Statut', 'Brouillon', 'Titre']] as $locale => [$status, $draft, $title]) {
            $definition = $this->withSession([InterfaceLocales::SESSION_KEY => trim($locale)])
                ->getJson(self::API.'/definition')->assertOk()->json();
            $resource = collect($definition['resources'])->firstWhere('id', 'translated-posts');
            $filter = collect($resource['list']['filters'])->firstWhere('id', 'status');

            $this->assertSame($status, $filter['label'], 'The filter, in '.$locale);
            $this->assertSame($draft, $filter['options'][0]['label'] ?? null, 'Its options, in '.$locale);
            $this->assertSame($title, collect($resource['fields'])->firstWhere('name', 'title')['label'] ?? null, 'The fields, in '.$locale);
        }
    }

    public function test_an_item_is_serialized_without_an_http_request_in_the_scope_of_the_request_it_is_given(): void
    {
        $ada = Author::query()->create(['name' => 'Ada']);
        $grace = Author::query()->create(['name' => 'Grace']);
        $post = $this->createPost(['title' => 'Linked', 'link' => ['type' => 'page', 'page' => ['id' => (string) $grace->id, 'resource' => 'own-authors']]]);
        $registry = $this->app->make(ResourceRegistry::class);
        $resource = $registry->find('translated-posts');
        $this->assertNotNull($resource);
        $serializer = $this->app->make(ItemSerializer::class);
        // The request the container holds is someone else's
        $this->app->instance('request', self::requestOf('Ada'));

        $asGrace = $serializer->serialize($resource, $post, ['link'], references: new MediaReferences($registry, self::requestOf('Grace')));
        $asAda = $serializer->serialize($resource, $post, ['link'], references: new MediaReferences($registry, self::requestOf('Ada')));

        $this->assertSame(['id' => (string) $grace->id, 'resource' => 'own-authors', 'label' => 'Grace'], $asGrace['link']['page']);
        $this->assertSame(['id' => (string) $grace->id, 'resource' => 'own-authors'], $asAda['link']['page']);
        $this->assertNotSame($ada->id, $grace->id);

        // Without the references of a request, no scope is guessed
        try {
            $serializer->serialize($resource, $post, ['link']);
            $this->fail('A link needs the references of a request.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('MediaReferences', $exception->getMessage());
        }
        // An item whose values need no scope is serialized without them
        $this->assertSame('Linked', $serializer->serialize($resource, $post, ['title'])['title']);
    }

    private static function requestOf(string $name): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn (): GenericUser => new GenericUser(['id' => 1, 'name' => $name]));

        return $request;
    }
}

class TranslatedPostsResource extends PostsResource
{
    public function id(): string
    {
        return 'translated-posts';
    }

    public function fields(): array
    {
        return [
            ...array_filter(parent::fields(), fn (Field $field): bool => ! in_array($field->name(), ['title', 'link'], true)),
            Text::make('title')->label(__('Title')),
            Link::make('link')->pages(OwnAuthorsResource::class),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->filters([
            SelectFilter::make('status')->label(__('Status'))->options(['draft' => __('Draft'), 'published' => 'Published']),
        ]);
    }
}

/**
 * Authors who only see themselves: the scope comes from the user of the request.
 */
class OwnAuthorsResource extends AuthorsResource
{
    public function id(): string
    {
        return 'own-authors';
    }

    public function scope(Request $request): array
    {
        return ['name' => $request->user()?->name];
    }
}
