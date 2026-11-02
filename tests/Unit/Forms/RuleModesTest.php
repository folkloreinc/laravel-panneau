<?php

namespace Panneau\Tests\Unit\Forms;

use Illuminate\Validation\ValidationException;
use Panneau\Fields\Field;
use Panneau\Fields\Number;
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
