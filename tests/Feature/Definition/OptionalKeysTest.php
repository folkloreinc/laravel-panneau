<?php

namespace Panneau\Tests\Feature\Definition;

use InvalidArgumentException;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\BlockType;
use Panneau\Fields\Repeater;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Layout\Field;
use Panneau\Layout\Section;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\Filters\TextFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use TestApp\Models\Entry;

/**
 * The optional keys of the definition of `@panneau/*` 5.0.0-alpha.51 (#295): the status of items,
 * the language switch of the form, primary filters, and the description and title of block types.
 */
class OptionalKeysTest extends TestCase
{
    use ValidatesProtocol;

    /**
     * @return array<string, mixed>
     */
    private function resource(string $class): array
    {
        $this->app['config']->set('panneau.locales', ['fr', 'en']);
        Panneau::resources([$class]);
        $definition = $this->app->make(DefinitionBuilder::class)->build();
        $this->assertValidDefinition($definition);

        return $definition['resources'][0];
    }

    public function test_none_of_them_is_sent_by_default(): void
    {
        $articles = $this->resource(PlainArticlesResource::class);

        $this->assertArrayNotHasKey('itemStatus', $articles);
        $this->assertArrayNotHasKey('form', $articles);
        foreach ($articles['list']['filters'] as $filter) {
            $this->assertArrayNotHasKey('primary', $filter);
        }
        $this->assertSame(['id' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'text', 'type' => 'textarea']]], $articles['fields'][2]['types'][0]);
    }

    public function test_the_status_of_items(): void
    {
        $this->assertSame('state', $this->resource(ArticlesResource::class)['itemStatus'] ?? null);
    }

    public function test_the_language_switch_of_the_form(): void
    {
        $this->assertSame(['languageSwitch' => true], $this->resource(ArticlesResource::class)['form']);
    }

    public function test_the_language_switch_goes_with_the_layout(): void
    {
        $form = $this->resource(ArticlesWithLayoutResource::class)['form'];

        $this->assertTrue($form['languageSwitch']);
        $this->assertCount(1, $form['layout']);
    }

    public function test_primary_filters(): void
    {
        $filters = $this->resource(ArticlesResource::class)['list']['filters'];

        $this->assertSame(['state' => true, 'q' => false, 'slug' => null], array_combine(
            array_column($filters, 'id'),
            array_map(fn (array $filter): ?bool => $filter['primary'] ?? null, $filters),
        ));
    }

    public function test_the_description_and_the_title_of_block_types(): void
    {
        $quote = $this->resource(ArticlesResource::class)['fields'][2]['types'][0];

        $this->assertSame('A quote, with its author', $quote['description']);
        $this->assertSame('{author}: {text}', $quote['itemTitle']);
    }

    public function test_the_title_of_a_block_type_is_not_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The itemTitle of the block type "quote" is empty.');

        BlockType::make('quote', 'Quote')->itemTitle('');
    }
}

class PlainArticlesResource extends Resource
{
    public function id(): string
    {
        return 'articles';
    }

    public function model(): string
    {
        return Entry::class;
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized(),
            Select::make('state')->options(['draft' => 'Draft', 'published' => 'Published']),
            Repeater::make('blocks')->types([static::quote()]),
        ];
    }

    protected static function quote(): BlockType
    {
        return BlockType::make('quote', 'Quote', [Textarea::make('text')]);
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->filters([
            SelectFilter::make('state')->options(['draft' => 'Draft', 'published' => 'Published']),
            TextFilter::make('q'),
            TextFilter::make('slug'),
        ]);
    }
}

class ArticlesResource extends PlainArticlesResource
{
    public function itemStatus(): string
    {
        return 'state';
    }

    public function languageSwitch(): bool
    {
        return true;
    }

    protected static function quote(): BlockType
    {
        return parent::quote()->description('A quote, with its author')->itemTitle('{author}: {text}');
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->filters([
            SelectFilter::make('state')->options(['draft' => 'Draft', 'published' => 'Published'])->primary(),
            TextFilter::make('q')->primary(false),
            TextFilter::make('slug'),
        ]);
    }
}

class ArticlesWithLayoutResource extends ArticlesResource
{
    public function layout(): array
    {
        return [Section::make('Content', [Field::make('title'), Field::make('state'), Field::make('blocks')])];
    }
}
