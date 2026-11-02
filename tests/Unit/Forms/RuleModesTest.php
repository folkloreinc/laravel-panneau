<?php

namespace Panneau\Tests\Unit\Forms;

use Illuminate\Validation\ValidationException;
use Panneau\Fields\Field;
use Panneau\Fields\Media;
use Panneau\Fields\Number;
use Panneau\Fields\Password;
use Panneau\Fields\Range;
use Panneau\Fields\Text;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;

/**
 * Portable rules limited to a mode of the form (folkloreinc/panneau-js#165).
 */
class RuleModesTest extends TestCase
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function errors(array $fields, array $input, FormMode $mode): array
    {
        try {
            Form::validate($this->app['validator'], $fields, $input, new ValidationContext($mode, locales: ['fr', 'en']));
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    public function test_a_rule_limited_to_a_mode_is_in_the_definition(): void
    {
        $this->assertSame(
            [['rule' => 'required', 'mode' => 'create'], ['rule' => 'min', 'value' => 3, 'mode' => 'update', 'message' => 'Too short.']],
            Text::make('code')->required(mode: FormMode::Create)->min(3, 'Too short.', FormMode::Update)->toDefinition()['rules'],
        );
    }

    public function test_required_on_create_accepts_no_value_on_update(): void
    {
        $fields = [Text::make('code')->required(mode: FormMode::Create)];

        $this->assertArrayHasKey('code', $this->errors($fields, [], FormMode::Create));
        $this->assertArrayHasKey('code', $this->errors($fields, ['code' => ''], FormMode::Create));
        foreach ([[], ['code' => null], ['code' => '']] as $input) {
            $this->assertSame([], $this->errors($fields, $input, FormMode::Update));
        }
    }

    public function test_other_rules_apply_in_their_mode_only(): void
    {
        $fields = [Number::make('count')->max(10, mode: FormMode::Update)];

        $this->assertSame([], $this->errors($fields, ['count' => 20], FormMode::Create));
        $this->assertArrayHasKey('count', $this->errors($fields, ['count' => 20], FormMode::Update));
    }

    public function test_defaults_apply_in_the_modes_without_a_rule_of_their_own(): void
    {
        // The ends of a Range: its own min on create, the default one on update
        $fields = [Range::make('rating')->min(10, mode: FormMode::Create)];
        $this->assertArrayHasKey('rating', $this->errors($fields, ['rating' => 5], FormMode::Create));
        $this->assertSame([], $this->errors($fields, ['rating' => 5], FormMode::Update));
        $this->assertArrayHasKey('rating', $this->errors($fields, ['rating' => -50], FormMode::Update));

        // The length of Password::defaults(), in the definition, for the other mode
        $this->assertSame(
            [['rule' => 'required', 'mode' => 'create'], ['rule' => 'min', 'value' => 12, 'mode' => 'update'], ['rule' => 'min', 'value' => 8, 'mode' => 'create']],
            Password::make('password')->min(12, mode: FormMode::Update)->toDefinition()['rules'],
        );
        $this->assertSame(
            [['rule' => 'required', 'mode' => 'create'], ['rule' => 'min', 'value' => 12]],
            Password::make('password')->min(12)->toDefinition()['rules'],
        );
    }

    public function test_localized_media_apply_the_mode_of_each_locale(): void
    {
        $fields = [Media::make('cover')->localized()->required(mode: FormMode::Update)];

        $this->assertSame([], $this->errors($fields, [], FormMode::Create));
        $this->assertSame([], $this->errors($fields, ['cover' => ['fr' => null]], FormMode::Create));
        $this->assertArrayHasKey('cover', $this->errors($fields, [], FormMode::Update));
        $this->assertArrayHasKey('cover.fr', $this->errors($fields, ['cover' => ['fr' => null]], FormMode::Update));
    }

    public function test_localized_fields_apply_the_mode_of_each_locale(): void
    {
        $fields = [Text::make('title')->localized()->required(mode: FormMode::Update)];

        $this->assertSame([], $this->errors($fields, [], FormMode::Create));
        $this->assertArrayHasKey('title.fr', $this->errors($fields, ['title' => ['en' => 'Title']], FormMode::Update));
    }

    public function test_a_rule_replaces_the_one_that_applies_in_the_same_modes(): void
    {
        $rules = fn (Field $field): array => $field->toDefinition()['rules'] ?? [];

        // A rule for every mode replaces the rules of one mode, and the other way around
        $this->assertSame([['rule' => 'required']], $rules(Text::make('a')->required(mode: FormMode::Create)->required()));
        $this->assertSame([['rule' => 'required', 'mode' => 'create']], $rules(Text::make('a')->required()->required(mode: FormMode::Create)));
        // Each mode keeps its own
        $this->assertSame(
            [['rule' => 'max', 'value' => 5, 'mode' => 'create'], ['rule' => 'max', 'value' => 10, 'mode' => 'update']],
            $rules(Text::make('a')->max(5, mode: FormMode::Create)->max(10, mode: FormMode::Update)),
        );
    }

    public function test_is_required_in_a_mode_or_in_every_mode(): void
    {
        $field = Text::make('code')->required(mode: FormMode::Create);

        $this->assertTrue($field->isRequired(FormMode::Create));
        $this->assertFalse($field->isRequired(FormMode::Update));
        $this->assertFalse($field->isRequired());
        $this->assertTrue(Text::make('code')->required()->isRequired());
        $this->assertTrue(Text::make('code')->required(mode: FormMode::Create)->required(mode: FormMode::Update)->isRequired());
    }
}
