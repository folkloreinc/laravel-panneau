<?php

namespace Panneau\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Panneau\Tests\TestCase;
use TestApp\Repositories\RecordingRepository;
use TestApp\Resources\CustomPostsResource;
use TestApp\Resources\PostsResource;

class CloneTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Resources must be set before the routes are registered, for the custom controller routes
        $app['panneau']->resources([PostsResource::class, CustomPostsResource::class]);
    }

    public function setUp(): void
    {
        parent::setUp();

        RecordingRepository::$calls = [];
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    public function testCloneReturnsTheCopyAsJson()
    {
        $this->postJson('/panneau/posts/1/clone')
            ->assertSuccessful()
            ->assertJsonPath('data.id', '2')
            ->assertJsonPath('data.title', 'Title');

        $this->assertCount(1, RecordingRepository::$calls);
        $this->assertEquals('create', RecordingRepository::$calls[0][0]);
        $this->assertArrayNotHasKey('id', RecordingRepository::$calls[0][1]);
        $this->assertEquals('Title', RecordingRepository::$calls[0][1]['title']);
    }

    public function testCloneRedirectsToTheEditPage()
    {
        $this->post('/panneau/posts/1/clone')->assertRedirect('/panneau/posts/2/edit');
    }

    public function testCloneRedirectsToTheEditPageOfResourceWithController()
    {
        $this->post('/panneau/customPosts/1/clone')->assertRedirect('/panneau/customPosts/2/edit');
    }

    public function testCloneMissingItem()
    {
        $this->postJson('/panneau/posts/3/clone')->assertNotFound();

        $this->assertEquals([], RecordingRepository::$calls);
    }
}
