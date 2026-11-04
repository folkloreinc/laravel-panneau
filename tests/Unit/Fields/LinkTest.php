<?php

namespace Panneau\Tests\Unit\Fields;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Panneau\Fields\Field;
use Panneau\Fields\Link;
use Panneau\Forms\Form;
use Panneau\Forms\ValidationContext;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class LinkTest extends TestCase
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $fields, array $input, ?ValidationContext $context = null): array
    {
        return Form::validate($this->app['validator'], $fields, $input, $context ?? new ValidationContext(locales: ['fr', 'en']));
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function errors(array $fields, array $input, ?ValidationContext $context = null): array
    {
        try {
            $this->validate($fields, $input, $context);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    public function test_the_definition(): void
    {
        $link = Link::make('cta')
            ->pages('pages', 'events')
            ->types(['page', 'url', ['id' => 'anchor', 'label' => 'Anchor', 'pattern' => '^#[\w-]+$', 'placeholder' => '#program', 'other' => 'dropped']])
            ->libraries(['documents'])
            ->newTab('external')
            ->linkLabel('required')
            ->label('Call to action');

        $this->assertSame([
            'name' => 'cta',
            'type' => 'link',
            'label' => 'Call to action',
            'resource' => ['pages', 'events'],
            'settings' => [
                'types' => ['page', 'url', ['id' => 'anchor', 'label' => 'Anchor', 'pattern' => '^#[\w-]+$', 'placeholder' => '#program']],
                'libraries' => ['documents'],
                'newTab' => 'external',
                'label' => 'required',
            ],
        ], $link->toDefinition());
        $this->assertSame(['name' => 'cta', 'type' => 'link', 'resource' => 'pages'], Link::make('cta')->pages('pages')->toDefinition());
    }

    public function test_the_default_types(): void
    {
        $this->assertSame([['id' => 'url'], ['id' => 'email'], ['id' => 'tel']], Link::make('cta')->getTypes());
        $this->assertSame([['id' => 'page'], ['id' => 'url'], ['id' => 'email'], ['id' => 'tel']], Link::make('cta')->pages('pages')->getTypes());
        // A page type without pages is left out, as clients do
        $this->assertSame([['id' => 'url'], ['id' => 'file']], Link::make('cta')->types(['page', 'url', 'file'])->getTypes());
    }

    /**
     * @return array<string, array{list<string|array<string, string>>}>
     */
    public static function invalidTypes(): array
    {
        return [
            'unknown name' => [['link']],
            'without an id' => [[['label' => 'Anchor']]],
            'without a label' => [[['id' => 'anchor']]],
            'a built-in id' => [[['id' => 'url', 'label' => 'URL']]],
            'twice' => [['url', 'url']],
        ];
    }

    /**
     * @param  list<string|array<string, string>>  $types
     */
    #[DataProvider('invalidTypes')]
    public function test_types_are_checked(array $types): void
    {
        $this->expectException(InvalidArgumentException::class);
        Link::make('cta')->types($types);
    }

    public function test_settings_are_checked(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Link::make('cta')->newTab('always');
    }

    public function test_labels_are_checked(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Link::make('cta')->linkLabel('hidden');
    }

    public function test_only_the_parts_of_the_type_are_kept(): void
    {
        $fields = [Link::make('cta')];

        $this->assertSame(
            ['cta' => ['type' => 'url', 'url' => 'https://example.com', 'label' => ['fr' => 'Voir', 'en' => 'See'], 'newTab' => true]],
            $this->validate($fields, ['cta' => [
                'type' => 'url',
                'url' => ' https://example.com ',
                'page' => '12',
                'file' => 'med_1',
                'label' => ['fr' => ' Voir ', 'en' => 'See', 'de' => 'Sehen'],
                'newTab' => true,
                'extra' => true,
            ]]),
        );
        // newTab is always there
        $this->assertSame(['cta' => ['type' => 'email', 'url' => 'mailto:info@example.com', 'newTab' => false]], $this->validate($fields, ['cta' => ['type' => 'email', 'url' => 'mailto:info@example.com', 'label' => ['fr' => '']]]));
        $this->assertSame(['cta' => null], $this->validate($fields, ['cta' => null]));
    }

    public function test_new_tabs(): void
    {
        $never = [Link::make('cta')->newTab('never')];
        $external = [Link::make('cta')->newTab('external')->types(['url', 'email', ['id' => 'path', 'label' => 'Path']])];

        $this->assertFalse($this->validate($never, ['cta' => ['type' => 'url', 'url' => 'https://example.com', 'newTab' => true]])['cta']['newTab']);
        $this->assertTrue($this->validate($external, ['cta' => ['type' => 'url', 'url' => 'https://example.com', 'newTab' => false]])['cta']['newTab']);
        $this->assertFalse($this->validate($external, ['cta' => ['type' => 'email', 'url' => 'mailto:a@example.com', 'newTab' => true]])['cta']['newTab']);
        $this->assertTrue($this->validate($external, ['cta' => ['type' => 'path', 'url' => '//cdn.example.com/a']])['cta']['newTab']);
        $this->assertFalse($this->validate($external, ['cta' => ['type' => 'path', 'url' => '/about']])['cta']['newTab']);
    }

    public function test_pages_of_several_resources_name_their_resource(): void
    {
        $fields = [Link::make('cta')->pages('pages', 'events')];
        $context = new ValidationContext(relationExists: fn (string $resource, string $id): bool => $id !== '404');

        $this->assertSame(
            ['cta' => ['type' => 'page', 'page' => ['id' => '12', 'resource' => 'events'], 'newTab' => false]],
            $this->validate($fields, ['cta' => ['type' => 'page', 'page' => ['id' => 12, 'resource' => 'events', 'label' => 'Sent back'], 'url' => 'https://ignored.example.com']], $context),
        );
        foreach ([['id' => '12'], '12', ['id' => '12', 'resource' => 'posts'], ['id' => '404', 'resource' => 'pages'], ['resource' => 'pages'], null] as $page) {
            $this->assertSame(['cta.page'], array_keys($this->errors($fields, ['cta' => ['type' => 'page', 'page' => $page]], $context)));
        }
        // With one resource, an id is enough
        $this->assertSame(
            ['cta' => ['type' => 'page', 'page' => ['id' => '12', 'resource' => 'pages'], 'newTab' => false]],
            $this->validate([Link::make('cta')->pages('pages')], ['cta' => ['type' => 'page', 'page' => '12']], $context),
        );
    }

    public function test_a_required_label(): void
    {
        $fields = [Link::make('cta')->linkLabel('required')];
        $link = ['type' => 'url', 'url' => 'https://example.com'];

        $this->assertSame(['cta.label'], array_keys($this->errors($fields, ['cta' => $link])));
        $this->assertSame(['cta.label'], array_keys($this->errors($fields, ['cta' => $link + ['label' => ['fr' => ' ', 'en' => null]]])));
        $this->assertSame(['cta.label.en'], array_keys($this->errors($fields, ['cta' => $link + ['label' => ['fr' => 'Voir', 'en' => str_repeat('a', 256)]]])));
        $this->assertSame([], $this->errors($fields, ['cta' => $link + ['label' => ['en' => 'See']]]));
        // Without labels, they are dropped
        $this->assertSame(['cta' => $link + ['newTab' => false]], $this->validate([Link::make('cta')->linkLabel('none')], ['cta' => $link + ['label' => ['fr' => 'Voir']]]));
    }

    public function test_a_localized_link_has_a_label_per_locale(): void
    {
        $fields = [Link::make('cta')->localized()->linkLabel('required')];

        $this->assertSame(
            ['cta.en.url', 'cta.en.label'],
            array_keys($this->errors($fields, ['cta' => [
                'fr' => ['type' => 'url', 'url' => 'https://example.com/fr', 'label' => 'Voir'],
                'en' => ['type' => 'url', 'url' => 'example.com/en'],
            ]])),
        );
        $this->assertSame(
            ['cta' => ['fr' => ['type' => 'url', 'url' => 'https://example.com/fr', 'label' => 'Voir', 'newTab' => false], 'en' => null]],
            $this->validate($fields, ['cta' => ['fr' => ['type' => 'url', 'url' => 'https://example.com/fr', 'label' => ' Voir '], 'en' => null]]),
        );
    }

    public function test_file_links_are_checked_as_media(): void
    {
        $fields = [Link::make('cta')->types(['file'])];
        $context = new ValidationContext(media: fn (?string $library, string $id): ?array => $id === 'pdf' ? ['type' => 'document', 'mime' => 'application/pdf'] : null);

        $this->assertSame(
            ['cta' => ['type' => 'file', 'file' => ['id' => 'pdf'], 'newTab' => false]],
            $this->validate($fields, ['cta' => ['type' => 'file', 'file' => ['id' => 'pdf', 'label' => 'Sent back', 'media' => []], 'url' => 'https://ignored.example.com']], $context),
        );
        $this->assertSame(['cta.file'], array_keys($this->errors($fields, ['cta' => ['type' => 'file', 'file' => 'missing']], $context)));
        $this->assertSame(['cta.file'], array_keys($this->errors($fields, ['cta' => ['type' => 'file']], $context)));
    }
}
