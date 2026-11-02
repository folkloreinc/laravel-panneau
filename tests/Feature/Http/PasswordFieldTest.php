<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Orchestra\Testbench\Attributes\WithMigration;
use Panneau\Fields\Email;
use Panneau\Fields\Password;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;

/**
 * A users resource with a password, on the skeleton's `users` table.
 */
#[WithMigration]
class PasswordFieldTest extends HttpTestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'users';
            }

            public function model(): string
            {
                return User::class;
            }

            public function fields(): array
            {
                return [Text::make('name')->required(), Email::make('email')->required(), Password::make('password')];
            }
        }]);
        Panneau::allowWithoutPolicies();
    }

    public function test_the_definition_has_a_secret_text_field(): void
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();

        $this->assertValidDefinition($response->json());
        $users = collect($response->json('resources'))->firstWhere('id', 'users');
        $this->assertSame(
            ['name' => 'password', 'type' => 'text', 'settings' => ['secret' => true], 'rules' => [['rule' => 'min', 'value' => 8]]],
            $users['fields'][2],
        );
    }

    public function test_a_user_is_created_with_a_hashed_password_that_is_never_sent(): void
    {
        $this->postJson(self::API.'/resources/users', ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $response = $this->postJson(self::API.'/resources/users', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret123'])
            ->assertCreated()
            ->assertJsonPath('data.password', null);

        $user = User::query()->findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('secret123', $user->getAuthPassword()));
        $this->getJson(self::API.'/resources/users/'.$user->getKey())->assertOk()->assertJsonPath('data.password', null);
    }

    public function test_an_empty_password_keeps_the_current_one(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret123')])->save();
        $hash = $user->getAuthPassword();

        $this->patchJson(self::API.'/resources/users/'.$user->getKey(), ['name' => 'Ada Lovelace', 'password' => null])->assertOk();
        $this->patchJson(self::API.'/resources/users/'.$user->getKey(), ['password' => ''])->assertOk();
        $this->assertSame($hash, $user->refresh()->getAuthPassword());
        $this->assertSame('Ada Lovelace', $user->name);

        $this->patchJson(self::API.'/resources/users/'.$user->getKey(), ['password' => 'another-secret'])->assertOk();
        $this->assertTrue(Hash::check('another-secret', $user->refresh()->getAuthPassword()));
    }
}
