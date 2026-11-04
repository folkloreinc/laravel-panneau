<?php

namespace Panneau\Tests\Feature\Http;

use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Author;
use TestApp\Models\Post;

/**
 * The `address` and `link` fields of PostsResource (panneau-js decisions 0054 and 0055): at the
 * top of the resource, and in its `details` group.
 */
class AddressAndLinkFieldsTest extends HttpTestCase
{
    use ValidatesProtocol;

    public function test_the_definition_has_the_settings_and_the_pages_of_the_fields(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $fields = collect(collect($response->json('resources'))->firstWhere('id', 'posts')['fields'])->keyBy('name');
        $this->assertSame(['name' => 'address', 'type' => 'address', 'settings' => ['country' => 'CA', 'preferred' => ['CA', 'US']]], $fields['address']);
        // The resource of the pages, by id
        $this->assertSame(['name' => 'link', 'type' => 'link', 'resource' => 'authors'], $fields['link']);
        $details = collect($fields['details']['fields'])->keyBy('name');
        $this->assertSame(['countries' => ['CA', 'US'], 'required' => ['street', 'city', 'region', 'postalCode', 'country'], 'point' => true], $details['venue']['settings']);
        $this->assertSame([
            'types' => ['url', ['id' => 'anchor', 'label' => 'Anchor', 'pattern' => '^#[\\w-]+$']],
            'newTab' => 'external',
            'label' => 'none',
        ], $details['website']['settings']);
    }

    public function test_an_address_is_normalized(): void
    {
        $post = $this->createPost();

        $response = $this->patchJson(self::API.'/resources/posts/'.$post->id, ['address' => [
            'street' => ' 1234 Rue Saint-Denis ',
            'unit' => '',
            'city' => 'Montréal',
            'region' => 'Québec',
            'postalCode' => 'h2x1y4',
            'extra' => 'dropped',
        ]])->assertOk();

        // The country of the field, the code of the region, and the postal code of the country
        $address = ['street' => '1234 Rue Saint-Denis', 'city' => 'Montréal', 'region' => 'QC', 'postalCode' => 'H2X 1Y4', 'country' => 'CA'];
        $response->assertJsonPath('data.address', $address);
        $this->assertMatchesComponent($response->json('data.address'), 'AddressValue');
        $this->assertSame($address, Post::query()->findOrFail($post->id)->address);

        // An address without parts, or with its country alone, is no address
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['address' => ['street' => '', 'city' => null, 'country' => 'US']])
            ->assertOk()
            ->assertJsonPath('data.address', null);
    }

    public function test_the_parts_of_an_address_are_checked_on_their_paths(): void
    {
        $post = $this->createPost();

        // Conformance: update.parts
        $this->patchJson(self::API.'/resources/posts/'.$post->id, [
            'address' => ['street' => '1234 Main Street', 'city' => 'Montreal', 'region' => 'QC', 'postalCode' => '12345', 'country' => 'CA'],
            'link' => ['type' => 'email', 'url' => 'info@example.com', 'newTab' => false],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address.postalCode', 'link.url'])
            ->assertJsonMissingValidationErrors(['address.street', 'address']);

        // The parts that every address has, and a country among those of the field
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['address' => ['postalCode' => 'H2X 1Y4', 'country' => 'ZZ']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address.street', 'address.city', 'address.country'])
            ->assertJsonMissingValidationErrors(['address.postalCode']);

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['details' => ['venue' => [
            'street' => '350 Fifth Avenue',
            'city' => 'New York',
            'postalCode' => '1000',
            'country' => 'FR',
            'point' => ['lat' => 100, 'lng' => 0],
        ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['details.venue.region', 'details.venue.country', 'details.venue.point.lat'])
            // France's postal codes aren't checked
            ->assertJsonMissingValidationErrors(['details.venue.postalCode']);
    }

    public function test_an_address_keeps_its_point_with_point(): void
    {
        $post = $this->createPost();

        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['details' => ['venue' => [
            'street' => '350 Fifth Avenue',
            'city' => 'New York',
            'region' => 'new york',
            'postalCode' => '10118 0110',
            'country' => 'us',
            'point' => ['lat' => '40.748', 'lng' => -73.985, 'zoom' => 12],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.details.venue', [
                'street' => '350 Fifth Avenue',
                'city' => 'New York',
                'region' => 'NY',
                'postalCode' => '10118-0110',
                'country' => 'US',
                'point' => ['lat' => 40.748, 'lng' => -73.985],
            ]);
        // Not with the address of the top, which has no point()
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['address' => ['street' => '1 Rue', 'city' => 'Laval', 'point' => ['lat' => 1, 'lng' => 2]]])
            ->assertOk()
            ->assertJsonPath('data.address', ['street' => '1 Rue', 'city' => 'Laval', 'country' => 'CA']);
    }

    public function test_a_page_link_gives_its_reference_and_its_url(): void
    {
        $author = Author::query()->create(['name' => 'Ada']);
        $post = $this->createPost();

        $response = $this->patchJson(self::API.'/resources/posts/'.$post->id, ['link' => [
            'type' => 'page',
            'page' => (string) $author->id,
            // Ignored: the backend resolves it
            'url' => 'https://elsewhere.example.com',
            'label' => ['fr' => ' Lire ', 'en' => '', 'de' => 'Lesen'],
            'newTab' => true,
        ]])->assertOk();

        $this->assertSame(
            ['type' => 'page', 'page' => ['id' => (string) $author->id, 'resource' => 'authors'], 'label' => ['fr' => 'Lire'], 'newTab' => true],
            Post::query()->findOrFail($post->id)->link,
        );
        $response->assertJsonPath('data.link', [
            'type' => 'page',
            'page' => ['id' => (string) $author->id, 'resource' => 'authors', 'label' => 'Ada'],
            'label' => ['fr' => 'Lire'],
            'newTab' => true,
            'url' => 'https://example.com/authors/'.$author->id,
        ]);
        $this->assertMatchesComponent($response->json('data.link'), 'LinkValue');

        // A page that doesn't exist, and the parts of a type
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['link' => ['type' => 'page', 'page' => '999']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['link.page']);
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['link' => ['type' => 'page', 'page' => ['id' => (string) $author->id, 'resource' => 'tags']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['link.page']);
        $this->patchJson(self::API.'/resources/posts/'.$post->id, ['link' => ['type' => 'file', 'url' => 'https://example.com']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['link.type']);
    }

    public function test_links_of_the_other_types(): void
    {
        $post = $this->createPost();
        $link = fn (array $link) => $this->patchJson(self::API.'/resources/posts/'.$post->id, ['link' => $link]);

        $link(['type' => 'email', 'url' => ' mailto:info@example.com?subject=Hello ', 'page' => '1', 'newTab' => false])
            ->assertOk()
            ->assertJsonPath('data.link', ['type' => 'email', 'url' => 'mailto:info@example.com?subject=Hello', 'newTab' => false]);
        $link(['type' => 'tel', 'url' => 'tel:+15145550199'])
            ->assertOk()
            ->assertJsonPath('data.link', ['type' => 'tel', 'url' => 'tel:+15145550199', 'newTab' => false]);
        $link(['type' => 'url', 'url' => 'https://example.com/a?b=c', 'newTab' => '1'])
            ->assertOk()
            ->assertJsonPath('data.link.newTab', true);

        foreach ([
            ['type' => 'url', 'url' => 'example.com'],
            ['type' => 'url', 'url' => 'javascript:alert(1)'],
            ['type' => 'url', 'url' => ''],
            ['type' => 'email', 'url' => 'mailto:not an address'],
            ['type' => 'tel', 'url' => 'tel:514 555-0199'],
            ['type' => 'tel', 'url' => '+15145550199'],
        ] as $invalid) {
            $link($invalid)->assertStatus(422)->assertJsonValidationErrors(['link.url']);
        }
        $link(['url' => 'https://example.com'])->assertStatus(422)->assertJsonValidationErrors(['link.type']);
    }

    public function test_new_tabs_and_types_of_the_site(): void
    {
        $post = $this->createPost();
        $website = fn (array $link) => $this->patchJson(self::API.'/resources/posts/'.$post->id, ['details' => ['website' => $link]]);

        // Set by the type with newTab('external'), without a label with linkLabel('none')
        $website(['type' => 'url', 'url' => 'https://example.com', 'label' => ['fr' => 'Site'], 'newTab' => false])
            ->assertOk()
            ->assertJsonPath('data.details.website', ['type' => 'url', 'url' => 'https://example.com', 'newTab' => true]);
        $website(['type' => 'anchor', 'url' => '#program', 'newTab' => true])
            ->assertOk()
            ->assertJsonPath('data.details.website', ['type' => 'anchor', 'url' => '#program', 'newTab' => false]);

        $website(['type' => 'anchor', 'url' => 'program'])->assertStatus(422)->assertJsonValidationErrors(['details.website.url']);
        $website(['type' => 'email', 'url' => 'mailto:info@example.com'])->assertStatus(422)->assertJsonValidationErrors(['details.website.type']);
    }
}
