<?php

namespace Panneau\Tests\Unit\Fields;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Panneau\Fields\Field;
use Panneau\Fields\Password;
use Panneau\Fields\Text;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;

class PasswordTest extends TestCase
{
    protected function tearDown(): void
    {
        PasswordRule::$defaultCallback = null;

        parent::tearDown();
    }

    /**
     * The values the repository receives.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function save(array $fields, array $input, FormMode $mode = FormMode::Create): array
    {
        $values = Form::validate($this->app['validator'], $fields, $input, new ValidationContext($mode));

        return Form::prepare($fields, $values, new SaveContext($mode));
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function errors(array $fields, array $input, FormMode $mode = FormMode::Create): array
    {
        try {
            $this->save($fields, $input, $mode);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    public function test_the_definition_is_a_secret_text_field(): void
    {
        $definition = Password::make('password')->label('Password')->toDefinition();

        $this->assertSame([
            'name' => 'password',
            'type' => 'text',
            'label' => 'Password',
            'settings' => ['secret' => true],
            'rules' => [['rule' => 'min', 'value' => 8]],
        ], $definition);
    }

    public function test_the_length_comes_from_the_default_password_rules(): void
    {
        PasswordRule::defaults(fn (): PasswordRule => PasswordRule::min(12)->letters());

        $this->assertSame([['rule' => 'min', 'value' => 12]], Password::make('password')->toDefinition()['rules']);
        $this->assertArrayHasKey('password', $this->errors([Password::make('password')], ['password' => '123456789012']));
        $this->assertSame([], $this->errors([Password::make('password')], ['password' => 'abcdefghijkl']));
    }

    public function test_the_password_is_never_sent(): void
    {
        $this->assertNull(Password::make('password')->resolve((object) ['password' => Hash::make('secret123')]));
    }

    public function test_it_is_required_to_create_the_item_only(): void
    {
        $fields = [Text::make('name'), Password::make('password')];

        $this->assertArrayHasKey('password', $this->errors($fields, ['name' => 'Ada']));
        $this->assertArrayHasKey('password', $this->errors($fields, ['name' => 'Ada', 'password' => '']));
        $this->assertArrayHasKey('password', $this->errors($fields, ['name' => 'Ada', 'password' => 'short']));
        $this->assertSame([], $this->errors([Text::make('name'), Password::make('password')->optional()], ['name' => 'Ada']));
        // An update without a password keeps the current one: the repository receives none
        $this->assertSame(['name' => 'Ada'], $this->save($fields, ['name' => 'Ada', 'password' => null], FormMode::Update));
        $this->assertSame(['name' => 'Ada'], $this->save($fields, ['name' => 'Ada', 'password' => ''], FormMode::Update));
        $this->assertArrayHasKey('password', $this->errors($fields, ['password' => 'short'], FormMode::Update));
    }

    public function test_the_repository_receives_the_hash(): void
    {
        $values = $this->save([Password::make('password')], ['password' => 'secret123']);

        $this->assertNotSame('secret123', $values['password']);
        $this->assertTrue(Hash::check('secret123', $values['password']));

        $values = $this->save([Password::make('password')->hashed(false)], ['password' => 'secret123'], FormMode::Update);
        $this->assertSame('secret123', $values['password']);
    }
}
