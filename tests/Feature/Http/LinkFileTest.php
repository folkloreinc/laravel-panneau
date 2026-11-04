<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Storage;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Article;
use TestApp\Models\Asset;
use TestApp\Panneau\Resources\ArticlesResource;
use TestApp\Panneau\Resources\DocumentsResource;
use TestApp\Panneau\Resources\ImagesResource;

/**
 * `file` links reference a media of a library, as `media` fields do: ArticlesResource's
 * `download` links to DocumentsResource, with a required label.
 */
class LinkFileTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Panneau::resources([ImagesResource::class, DocumentsResource::class, ArticlesResource::class]);
        Panneau::allowWithoutPolicies();
    }

    private function asset(string $library, string $mime): Asset
    {
        $path = 'assets/'.uniqid();
        Storage::disk('public')->put($path, 'contents');

        return Asset::query()->create([
            'team_id' => 1,
            'library' => $library,
            'name' => 'Terms',
            'type' => str_starts_with($mime, 'image/') ? 'image' : 'document',
            'file' => ['disk' => 'public', 'path' => $path, 'name' => 'terms.pdf', 'mime' => $mime, 'size' => 100],
        ]);
    }

    public function test_the_definition_names_the_libraries_by_id(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $fields = collect(collect($response->json('resources'))->firstWhere('id', 'articles')['fields'])->keyBy('name');
        $this->assertSame(['types' => ['file', 'url'], 'libraries' => ['documents'], 'label' => 'required'], $fields['download']['settings']);
    }

    public function test_a_file_link_gives_its_media_reference_and_its_url(): void
    {
        $document = $this->asset('documents', 'application/pdf');

        $response = $this->postJson(self::API.'/resources/articles', [
            'title' => 'An article',
            'download' => ['type' => 'file', 'file' => (string) $document->id, 'url' => 'https://ignored.example.com', 'label' => ['fr' => 'Conditions']],
        ])->assertCreated();

        $article = Article::query()->findOrFail($response->json('data.id'));
        $this->assertSame(['type' => 'file', 'file' => ['id' => (string) $document->id], 'label' => ['fr' => 'Conditions'], 'newTab' => false], $article->download);
        $response
            ->assertJsonPath('data.download.file.id', (string) $document->id)
            ->assertJsonPath('data.download.file.resource', 'documents');
        // The URL of the file, a temporary one for the private files of documents
        $this->assertIsString($response->json('data.download.url'));
        $this->assertStringContainsString($document->file['path'], $response->json('data.download.url'));
        $this->assertSame($response->json('data.download.file.media.url'), $response->json('data.download.url'));
        $this->assertMatchesComponent($response->json('data.download'), 'LinkValue');
        $this->assertMatchesComponent($response->json('data.download.file'), 'MediaReference');
    }

    public function test_a_file_link_needs_a_media_of_its_libraries_and_a_label(): void
    {
        $image = $this->asset('images', 'image/png');

        $this->postJson(self::API.'/resources/articles', ['title' => 'An article', 'download' => ['type' => 'file', 'label' => ['fr' => '', 'en' => null]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['download.file', 'download.label']);
        // An image is not in the library of documents
        $this->postJson(self::API.'/resources/articles', ['title' => 'An article', 'download' => ['type' => 'file', 'file' => (string) $image->id, 'label' => ['fr' => 'Image']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['download.file']);
    }
}
