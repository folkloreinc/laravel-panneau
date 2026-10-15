<?php

namespace Panneau\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Panneau\Tests\TestCase;
use TestApp\Repositories\RecordingRepository;
use TestApp\Resources\RestrictedResource;

class ResourceAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingRepository::$calls = [];
        RestrictedResource::$deniedAbilities = [];
        RestrictedResource::$checks = [];
        $this->app['panneau']->resources([RestrictedResource::class]);
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    protected function tearDown(): void
    {
        RestrictedResource::$deniedAbilities = [];
        RestrictedResource::$checks = [];

        parent::tearDown();
    }

    public function test_resource_is_asked_for_each_action()
    {
        $this->getJson('/panneau/restricted')->assertSuccessful();
        $this->getJson('/panneau/restricted/1')->assertSuccessful();
        $this->postJson('/panneau/restricted', ['title' => 'Title'])->assertSuccessful();
        $this->putJson('/panneau/restricted/1', ['title' => 'Title'])->assertSuccessful();
        $this->deleteJson('/panneau/restricted/1')->assertSuccessful();

        $this->assertEquals(
            [['viewAny', null], ['view', '1'], ['create', null], ['update', '1'], ['delete', '1']],
            RestrictedResource::$checks
        );
    }

    public function test_edit_delete_and_duplicate_pages_check_their_ability()
    {
        $this->getJson('/panneau/restricted/1/edit')->assertSuccessful();
        $this->getJson('/panneau/restricted/1/delete')->assertSuccessful();
        $this->getJson('/panneau/restricted/1/duplicate')->assertSuccessful();

        $this->assertEquals(
            [['update', '1'], ['view', '1'], ['delete', '1'], ['view', '1'], ['clone', '1'], ['view', '1']],
            RestrictedResource::$checks
        );
    }

    public function test_denied_actions_return403()
    {
        RestrictedResource::$deniedAbilities = ['viewAny', 'view', 'create', 'update', 'delete', 'clone'];

        $this->getJson('/panneau/restricted')->assertForbidden();
        $this->getJson('/panneau/restricted/create')->assertForbidden();
        $this->postJson('/panneau/restricted', ['title' => 'Title'])->assertForbidden();
        $this->getJson('/panneau/restricted/1')->assertForbidden();
        $this->getJson('/panneau/restricted/1/edit')->assertForbidden();
        $this->putJson('/panneau/restricted/1', ['title' => 'Title'])->assertForbidden();
        $this->getJson('/panneau/restricted/1/delete')->assertForbidden();
        $this->deleteJson('/panneau/restricted/1')->assertForbidden();
        $this->getJson('/panneau/restricted/1/duplicate')->assertForbidden();
        $this->postJson('/panneau/restricted/1/clone')->assertForbidden();

        $this->assertEquals([], RecordingRepository::$calls);
    }

    public function test_missing_item_returns404_before_authorization()
    {
        RestrictedResource::$deniedAbilities = ['update'];

        $this->getJson('/panneau/restricted/2/edit')->assertNotFound();

        $this->assertEquals([], RestrictedResource::$checks);
    }
}
