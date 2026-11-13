<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Panneau\Contracts\EmbedResolver;
use Panneau\Http\Problem;

class EmbedEndpointTest extends HttpTestCase
{
    private const VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'www.youtube.com/oembed*' => Http::response([
                'type' => 'video',
                'version' => '1.0',
                'title' => 'A video',
                'provider_name' => 'YouTube',
                'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
                'width' => 200,
                'height' => 113,
            ]),
            'soundcloud.com/oembed*' => Http::response(['type' => 'rich', 'title' => 'A sound', 'html' => '<iframe></iframe>']),
            'vimeo.com/api/oembed.json*' => Http::response(null, 404),
            'www.dailymotion.com/services/oembed*' => Http::response(['type' => 'video', 'title' => 'Midnight Sun | Iceland', 'provider_name' => 'Dailymotion', 'html' => '<iframe></iframe>']),
            'www.tiktok.com/oembed*' => Http::response(['version' => '1.0', 'type' => 'video', 'html' => '<iframe></iframe>', 'width' => '325']),
        ]);
    }

    public function test_a_url_of_a_provider_gives_its_embed(): void
    {
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))
            ->assertOk()
            ->assertExactJson(['data' => [
                'url' => self::VIDEO,
                'type' => 'video',
                'title' => 'A video',
                'provider' => 'YouTube',
                'thumbnailUrl' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
                'width' => 200,
                'height' => 113,
            ]]);

        // Only the provider's endpoint is requested, with the URL as a parameter
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.youtube.com/oembed?')
            && $request['url'] === self::VIDEO);
    }

    public function test_the_duration_comes_with_the_providers_that_give_it(): void
    {
        // A client without setUp()'s answers, whose Vimeo is a 404
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'www.youtube.com/oembed*' => Http::response(['type' => 'video', 'title' => 'A video', 'provider_name' => 'YouTube']),
            'vimeo.com/api/oembed.json*' => Http::response([
                'type' => 'video',
                'title' => 'The New Vimeo Player (You Know, For Videos)',
                'provider_name' => 'Vimeo',
                'html' => '<iframe src="https://player.vimeo.com/video/76979871"></iframe>',
                'width' => 1920,
                'height' => 1080,
                'duration' => 62,
            ]),
        ]);

        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://vimeo.com/76979871']))
            ->assertOk()
            ->assertJsonPath('data.duration', 62)
            ->assertJsonPath('data.provider', 'Vimeo');
        // YouTube's oEmbed gives none
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))
            ->assertOk()
            ->assertJsonMissingPath('data.duration');
    }

    public function test_dailymotion_and_tiktok_are_providers_by_default(): void
    {
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://dai.ly/x7tgad0']))
            ->assertOk()
            ->assertJsonPath('data.provider', 'Dailymotion')
            ->assertJsonPath('data.type', 'video');
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://www.tiktok.com/@scout2015/video/6718335390845095173']))
            ->assertOk()
            ->assertJsonPath('data.provider', 'TikTok')
            ->assertJsonPath('data.width', 325);
    }

    public function test_the_type_of_a_provider_replaces_the_oembed_type(): void
    {
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://soundcloud.com/artist/track']))
            ->assertOk()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.provider', 'SoundCloud');
    }

    public function test_answers_are_cached(): void
    {
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertOk();
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_urls_of_other_sites_are_never_requested(): void
    {
        foreach (['https://example.com/video', 'http://127.0.0.1/admin', 'https://www.youtube.com.example.com/watch'] as $url) {
            $this->getJson(self::API.'/embeds?'.http_build_query(['url' => $url]))
                ->assertStatus(422)
                ->assertHeader('Content-Type', Problem::CONTENT_TYPE)
                ->assertJsonPath('errors.url.0', "This URL can't be embedded.");
        }

        Http::assertNothingSent();
    }

    public function test_a_provider_that_fails_is_a_422(): void
    {
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://vimeo.com/1']))
            ->assertStatus(422)
            ->assertJsonPath('errors.url.0', "This URL can't be embedded.");
    }

    public function test_the_url_is_required_and_must_be_a_url(): void
    {
        $this->getJson(self::API.'/embeds')->assertStatus(422)->assertJsonValidationErrors('url');
        $this->getJson(self::API.'/embeds?url=javascript:alert(1)')->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_the_resolver_can_be_replaced(): void
    {
        $this->app->instance(EmbedResolver::class, new class implements EmbedResolver
        {
            public function resolve(string $url): ?array
            {
                return ['url' => $url, 'type' => 'link', 'title' => 'Our own'];
            }
        });

        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => 'https://example.com/post']))
            ->assertOk()
            ->assertExactJson(['data' => ['url' => 'https://example.com/post', 'type' => 'link', 'title' => 'Our own']]);
    }

    public function test_guests_are_refused(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertUnauthorized();
    }

    public function test_embeds_are_rate_limited_for_each_user(): void
    {
        $this->app['config']->set('panneau.embeds.rate_limit', 2);

        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertOk();
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertOk();
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->actingAs(new GenericUser(['id' => 2, 'name' => 'Grace']));
        $this->getJson(self::API.'/embeds?'.http_build_query(['url' => self::VIDEO]))->assertOk();
    }
}
