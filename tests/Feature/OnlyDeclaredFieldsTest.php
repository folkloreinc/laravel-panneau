<?php

namespace Panneau\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Panneau\Tests\TestCase;
use TestApp\Repositories\RecordingRepository;
use TestApp\Resources\ArticlesResource;

class OnlyDeclaredFieldsTest extends TestCase
{
    protected $data = [
        'title' => 'Title',
        'meta' => ['description' => 'Description'],
        'body' => 'Body',
        'subtitle' => 'Subtitle',
        'slug' => 'slug',
        'type' => 'feature',
        'is_admin' => true,
        'user_id' => 99,
    ];

    protected $declaredData = [
        'title' => 'Title',
        'meta' => ['description' => 'Description'],
        'body' => 'Body',
        'subtitle' => 'Subtitle',
        'slug' => 'slug',
        'type' => 'feature',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        RecordingRepository::$calls = [];
        ArticlesResource::$onlyDeclaredFields = null;
        $this->app['panneau']->resources([ArticlesResource::class]);
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    protected function tearDown(): void
    {
        ArticlesResource::$onlyDeclaredFields = null;

        parent::tearDown();
    }

    public function test_all_data_is_saved_by_default()
    {
        $this->postJson('/panneau/articles', $this->data)->assertSuccessful();
        $this->putJson('/panneau/articles/1', $this->data)->assertSuccessful();

        $this->assertEquals(
            [['create', $this->data], ['update', '1', $this->data]],
            RecordingRepository::$calls
        );
    }

    public function test_only_declared_fields_are_saved_when_enabled_in_config()
    {
        config()->set('panneau.only_declared_fields', true);

        $this->postJson('/panneau/articles', $this->data)->assertSuccessful();
        $this->putJson('/panneau/articles/1', array_merge($this->data, ['id' => '3']))
            ->assertSuccessful();

        $this->assertEquals(
            [['create', $this->declaredData], ['update', '1', $this->declaredData]],
            RecordingRepository::$calls
        );
    }

    public function test_only_declared_fields_are_saved_when_enabled_on_resource()
    {
        ArticlesResource::$onlyDeclaredFields = true;

        $this->postJson('/panneau/articles', $this->data)->assertSuccessful();

        $this->assertEquals([['create', $this->declaredData]], RecordingRepository::$calls);
    }

    public function test_resource_setting_overrides_config()
    {
        config()->set('panneau.only_declared_fields', true);
        ArticlesResource::$onlyDeclaredFields = false;

        $this->postJson('/panneau/articles', $this->data)->assertSuccessful();

        $this->assertEquals([['create', $this->data]], RecordingRepository::$calls);
    }
}
