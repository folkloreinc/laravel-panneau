<?php

namespace Panneau\Tests\Unit\Fields;

use Closure;
use Panneau\Fields\Slug;
use Panneau\Forms\FieldCheck;
use Panneau\Forms\Form;
use Panneau\Forms\FormMode;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;

class SlugTest extends TestCase
{
    /**
     * @param  list<string>  $taken
     * @param  array<string, mixed>  $values
     * @param  list<array{string, ?Closure}>  $lookups
     */
    private function fieldCheck(string $value, array $taken = [], array $values = [], ?string $id = null, ?string $locale = null, array &$lookups = []): FieldCheck
    {
        return new FieldCheck($value, $locale, $id, $values, function (string $candidate, ?Closure $constrain) use ($taken, &$lookups): bool {
            $lookups[] = [$candidate, $constrain];

            return in_array($candidate, $taken, true);
        });
    }

    public function test_a_slug_checks_with_unique_url_or_disabled_when(): void
    {
        $this->assertSame(['name' => 'slug', 'type' => 'slug', 'from' => 'title'], Slug::make('slug')->from('title')->toDefinition());
        $this->assertFalse(Slug::make('slug')->hasCheck());

        $this->assertSame(
            ['name' => 'slug', 'type' => 'slug', 'localized' => true, 'from' => 'title', 'check' => true],
            Slug::make('slug')->from('title')->localized()->unique()->toDefinition(),
        );
        $this->assertTrue(Slug::make('slug')->url(fn (string $slug): string => '/'.$slug)->hasCheck());
        $this->assertTrue(Slug::make('slug')->disabledWhen(fn (): bool => false)->hasCheck());
        $this->assertSame(['name' => 'slug', 'type' => 'slug', 'check' => true], Slug::make('slug')->unique(fn () => null)->toDefinition());
    }

    public function test_unique_keeps_its_closure(): void
    {
        $scope = fn (): null => null;
        $slug = Slug::make('slug')->unique($scope);

        $this->assertTrue($slug->isUnique());
        $this->assertSame($scope, $slug->uniqueScope());
        $this->assertFalse(Slug::make('slug')->isUnique());
    }

    public function test_a_taken_slug_gets_the_first_free_suggestion(): void
    {
        $scope = fn (): null => null;
        $lookups = [];
        $answer = Slug::make('slug')->unique($scope)->check($this->fieldCheck('ete', ['ete', 'ete-2'], lookups: $lookups));

        $this->assertSame(['available' => false, 'suggestion' => 'ete-3', 'prefix' => null, 'url' => null, 'disabled' => false], $answer);
        $this->assertSame([['ete', $scope], ['ete-2', $scope], ['ete-3', $scope]], $lookups);
    }

    public function test_suggestions_are_bounded(): void
    {
        $taken = ['ete', ...array_map(fn (int $number): string => 'ete-'.$number, range(2, 1 + Slug::SUGGESTIONS))];
        $lookups = [];

        $answer = Slug::make('slug')->unique()->check($this->fieldCheck('ete', $taken, lookups: $lookups));

        $this->assertFalse($answer['available']);
        $this->assertNull($answer['suggestion']);
        $this->assertCount(1 + Slug::SUGGESTIONS, $lookups);
    }

    public function test_a_suggestion_does_not_double_a_trailing_dash(): void
    {
        $this->assertSame('ete-2', Slug::make('slug')->unique()->check($this->fieldCheck('ete-', ['ete-']))['suggestion']);
    }

    public function test_a_slug_without_unique_is_never_looked_up(): void
    {
        $lookups = [];
        $answer = Slug::make('slug')->url(fn (string $slug): string => '/'.$slug)->check($this->fieldCheck('ete', ['ete'], lookups: $lookups));

        $this->assertTrue($answer['available']);
        $this->assertSame([], $lookups);
    }

    public function test_the_url_gets_the_slug_its_locale_the_values_and_the_id(): void
    {
        $received = null;
        $slug = Slug::make('slug')->url(function (string $slug, ?string $locale, array $values, ?string $id) use (&$received): string {
            $received = [$slug, $locale, $values, $id];

            return 'https://example.com/'.$locale.'/'.$slug;
        });

        $answer = $slug->check($this->fieldCheck('ete', values: ['parent' => '7'], id: '12', locale: 'fr'));

        $this->assertSame(['ete', 'fr', ['parent' => '7'], '12'], $received);
        $this->assertSame('https://example.com/fr/', $answer['prefix']);
        $this->assertSame('https://example.com/fr/ete', $answer['url']);
    }

    public function test_no_prefix_when_the_url_does_not_end_with_the_slug(): void
    {
        $answer = Slug::make('slug')->url(fn (string $slug): string => '/'.rawurlencode($slug).'.html')->check($this->fieldCheck('été'));

        $this->assertNull($answer['prefix']);
        $this->assertSame('/%C3%A9t%C3%A9.html', $answer['url']);
        $this->assertNull(Slug::make('slug')->url(fn (): ?string => null)->check($this->fieldCheck('ete'))['url']);
    }

    public function test_disabled_when(): void
    {
        $received = null;
        $lookups = [];
        $slug = Slug::make('slug')->unique()->url(fn (string $slug): string => '/'.$slug)
            ->disabledWhen(function (array $values, ?string $id) use (&$received): bool {
                $received = [$values, $id];

                return ($values['handle'] ?? null) === 'home';
            });

        $this->assertSame(
            ['available' => true, 'suggestion' => null, 'prefix' => null, 'url' => null, 'disabled' => true],
            $slug->check($this->fieldCheck('ete', ['ete'], ['handle' => 'home'], '1', lookups: $lookups)),
        );
        $this->assertSame([['handle' => 'home'], '1'], $received);
        $this->assertSame([], $lookups);
        $this->assertFalse($slug->check($this->fieldCheck('ete', ['ete'], ['handle' => 'about']))['disabled']);
    }

    public function test_saving_checks_unique_slugs_per_locale(): void
    {
        $lookups = [];
        $context = new ValidationContext(
            mode: FormMode::Create,
            locales: ['fr', 'en'],
            valueExists: function (string $field, string $value, ?string $locale, ?Closure $constrain) use (&$lookups): bool {
                $lookups[] = [$field, $value, $locale];

                return $value === 'ete';
            },
        );
        $scope = fn (): null => null;
        $rules = Form::rules([Slug::make('slug')->localized()->unique($scope)], ['slug' => ['fr' => 'ete', 'en' => 'summer']], $context)->rules();

        // The uniqueness rule comes last, per locale; the whole value has none
        $this->assertCount(2, $rules['slug']);
        $failed = [];
        foreach (['slug.fr' => 'ete', 'slug.en' => 'ete', 'slug.de' => null] as $path => $value) {
            if (! isset($rules[$path])) {
                continue;
            }
            $rule = $rules[$path][array_key_last($rules[$path])];
            $this->assertInstanceOf(Closure::class, $rule);
            $rule($path, $value, function () use (&$failed, $path): void {
                $failed[] = $path;
            });
        }

        $this->assertSame([['slug', 'ete', 'fr'], ['slug', 'ete', 'en']], $lookups);
        $this->assertSame(['slug.fr', 'slug.en'], $failed);
    }

    public function test_saving_checks_nothing_without_a_lookup_or_in_nested_values(): void
    {
        $slug = Slug::make('slug')->unique();
        $context = new ValidationContext(valueExists: fn (): bool => true);

        $this->assertCount(2, Form::rules([$slug], ['slug' => 'a'], new ValidationContext)->rules()['slug']);
        $this->assertCount(3, Form::rules([$slug], ['slug' => 'a'], $context)->rules()['slug']);
        $this->assertCount(2, Form::rules([$slug], ['slug' => 'a'], $context, 'seo.')->rules()['seo.slug']);
        $this->assertNull($context->nested()->valueExists);
    }
}
