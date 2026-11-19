<?php

namespace Panneau\Tests\Feature\Definition;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use LogicException;
use Panneau\Dashboard\Widget;
use Panneau\Definition\DefinitionBuilder;
use Panneau\Fields\DateTime;
use Panneau\Fields\Field;
use Panneau\Fields\Money;
use Panneau\Fields\Number;
use Panneau\Fields\Relation;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Lists\ResourceList;
use Panneau\Navigation\Page;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Models\Run;
use TestApp\Models\TaxedRun;
use TestApp\Panneau\Resources\AuthorsResource;
use TestApp\Panneau\Resources\PostsResource;
use TestApp\Panneau\Resources\RunsResource;
use TestApp\Panneau\Resources\TagsResource;
use TestApp\Repositories\Adapters\ArrayRepository;

/**
 * The aggregates of the lists, and the widgets that read them, in the definition (panneau-js
 * decision 0084).
 */
class AggregatesTest extends TestCase
{
    use ValidatesProtocol;

    protected function setUp(): void
    {
        parent::setUp();

        Panneau::resources([RunsResource::class, AuthorsResource::class, PostsResource::class, TagsResource::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(?Authenticatable $user = null): array
    {
        return $this->app->make(DefinitionBuilder::class)->build($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(string $id): array
    {
        return collect($this->definition()['resources'])->firstWhere('id', $id);
    }

    public function test_the_aggregates_of_a_list_without_the_fields_the_user_can_not_see(): void
    {
        $this->assertSame([
            'sum' => ['cost', 'tokens', 'amount'],
            'groupBy' => ['status', 'tool', 'retried', 'author', 'watchers', 'labels'],
            'dates' => ['ran_at', 'day'],
        ], $this->resource('runs')['list']['aggregates']);
        $this->assertArrayNotHasKey('aggregates', $this->resource('posts')['list']);
        $this->assertValidDefinition($this->definition());
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $sum
     * @param  list<string>  $groupBy
     * @param  list<string>  $dates
     * @param  list<string>  $inMemory
     * @param  bool|class-string<Run>  $model  An Eloquent model (`Run` for true), or an array.
     */
    private static function resourceWith(array $fields, array $sum = [], array $groupBy = [], array $dates = [], bool|string $model = true, array $inMemory = []): Resource
    {
        return new class($fields, $sum, $groupBy, $dates, $model, $inMemory) extends Resource
        {
            /**
             * @param  list<Field>  $declared
             * @param  list<string>  $sum
             * @param  list<string>  $groupBy
             * @param  list<string>  $dates
             * @param  list<string>  $inMemory
             */
            public function __construct(private array $declared, private array $sum, private array $groupBy, private array $dates, private bool|string $eloquent, private array $inMemory) {}

            public function id(): string
            {
                return 'logs';
            }

            public function model(): ?string
            {
                return is_string($this->eloquent) ? $this->eloquent : ($this->eloquent ? Run::class : null);
            }

            public function repository(): ?string
            {
                return $this->eloquent !== false ? null : ArrayRepository::class;
            }

            public function fields(): array
            {
                return $this->declared;
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->aggregates($this->sum, $this->groupBy, $this->dates, $this->inMemory);
            }
        };
    }

    /**
     * @return array<string, array{resource, string}>
     */
    public static function invalidAggregates(): array
    {
        return [
            'a field that does not exist' => [self::resourceWith([Number::make('cost')], sum: ['price']), 'The aggregates of "logs" name the field "price" in sum, which the resource doesn\'t have.'],
            'a sum of text' => [self::resourceWith([Text::make('title')], sum: ['title']), 'The field "title" of "logs" in the aggregates sum is a text: only number, range, duration and money fields can be summed.'],
            'a sum of several currencies' => [self::resourceWith([Money::make('amount')->currencies(['CAD', 'USD'])], sum: ['amount']), 'The field "amount" of "logs" in the aggregates sum is a money field of several currencies: amounts add up only in one currency().'],
            'a date that is text' => [self::resourceWith([Text::make('title')], dates: ['title']), 'The field "title" of "logs" in the aggregates dates must be a date or a datetime to be grouped by period.'],
            'a group of money' => [self::resourceWith([Money::make('amount')->currency('CAD')], groupBy: ['amount']), 'The field "amount" of "logs" in the aggregates groupBy is a money, whose values don\'t group the items.'],
            // Aggregated in memory, over every matching item, without inMemory (decision 0024)
            'a group by a BelongsToMany relation' => [self::resourceWith([Relation::make('watchers')->resource(AuthorsResource::class)->multiple()], groupBy: ['watchers']), 'The field "watchers" of "logs" in the aggregates groupBy is aggregated in memory by Panneau\Repositories\EloquentRepository, over every matching item: aggregate a column, or allow it with aggregates(inMemory: [\'watchers\']), up to panneau.aggregates.max_in_memory items.'],
            'a group by a column cast to an array' => [self::resourceWith([Select::make('labels')->options(['urgent' => 'Urgent'])->multiple(), Text::make('tool')], groupBy: ['tool', 'labels'], inMemory: ['tool']), 'The field "labels" of "logs" in the aggregates groupBy is aggregated in memory'],
            'a sum of an accessor' => [self::resourceWith([Number::make('taxed_tokens')], sum: ['taxed_tokens'], model: TaxedRun::class), 'The field "taxed_tokens" of "logs" in the aggregates sum is aggregated in memory'],
            'a repository without aggregates' => [self::resourceWith([Text::make('title')], groupBy: ['title'], model: false), 'The list of "logs" declares aggregates, but its repository TestApp\Repositories\Adapters\ArrayRepository doesn\'t implement Panneau\Contracts\Repositories\Aggregates.'],
        ];
    }

    #[DataProvider('invalidAggregates')]
    public function test_invalid_aggregates_throw_when_the_definition_is_built(Resource $resource, string $message): void
    {
        Panneau::resources([$resource]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }

    public function test_the_list_allows_aggregates_in_memory(): void
    {
        Panneau::resources([self::resourceWith(
            [Relation::make('watchers')->resource(AuthorsResource::class)->multiple(), Select::make('labels')->options(['urgent' => 'Urgent'])->multiple(), Number::make('taxed_tokens'), DateTime::make('ran_at')],
            sum: ['taxed_tokens'],
            groupBy: ['watchers', 'labels'],
            dates: ['ran_at'],
            model: TaxedRun::class,
            inMemory: ['taxed_tokens', 'watchers', 'labels'],
        )]);

        $this->assertSame(['sum' => ['taxed_tokens'], 'groupBy' => ['watchers', 'labels'], 'dates' => ['ran_at']], $this->resource('logs')['list']['aggregates']);
    }

    public function test_aggregates_in_memory_are_aggregates_of_the_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The aggregates of a list compute in memory "watchers", which they don\'t sum, group by or have as a date.');

        (new ResourceList)->aggregates(groupBy: ['status'], inMemory: ['watchers']);
    }

    public function test_aggregates_need_a_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The aggregates of a list need a field to sum, to group by, or a date.');

        (new ResourceList)->aggregates();
    }

    public function test_widgets_on_the_aggregates_of_a_resource(): void
    {
        Panneau::pages([Page::dashboard('activity')->widgets([
            Widget::series('per-day')->label('Runs per day')->resource(RunsResource::class, ['filter[status][]' => 'failed'])->per('day', 'ran_at'),
            Widget::breakdown('by-status')->resource('runs')->by('status')->sum('tokens'),
            Widget::stat('cost')->resource('runs')->sum('amount')->target(100000),
            // Fields the user can't see: left out for them
            Widget::stat('margin')->resource('runs')->sum('margin'),
            Widget::breakdown('by-author')->resource('runs')->by('author'),
        ])]);

        $definition = $this->definition();
        $this->assertSame([
            ['id' => 'per-day', 'type' => 'series', 'label' => 'Runs per day', 'settings' => ['resource' => 'runs', 'query' => ['filter[status][]' => 'failed'], 'group' => 'day:ran_at']],
            ['id' => 'by-status', 'type' => 'breakdown', 'settings' => ['resource' => 'runs', 'measure' => 'sum:tokens', 'group' => 'status']],
            ['id' => 'cost', 'type' => 'stat', 'settings' => ['resource' => 'runs', 'measure' => 'sum:amount', 'target' => 100000]],
            ['id' => 'by-author', 'type' => 'breakdown', 'settings' => ['resource' => 'runs', 'group' => 'author']],
        ], $definition['pages']['activity']['widgets']);
        $this->assertValidDefinition($definition);
    }

    /**
     * @return array<string, array{Widget, string}>
     */
    public static function invalidWidgets(): array
    {
        return [
            'a series without periods' => [Widget::series('a')->resource('runs'), 'The series "a" on "runs" needs per(): the days, weeks or months of a date.'],
            'a series of a date that is not declared' => [Widget::series('a')->resource('runs')->per('week', 'created_at'), 'The series "a" groups "runs" by "created_at", which isn\'t in the dates of the aggregates of its list.'],
            'a breakdown without parts' => [Widget::breakdown('a')->resource('runs')->sum('tokens'), 'The breakdown "a" on "runs" needs by(): the field whose values are its parts.'],
            'a breakdown by a field that is not declared' => [Widget::breakdown('a')->resource('runs')->by('title'), 'The breakdown "a" groups "runs" by "title", which isn\'t in the groupBy of the aggregates of its list.'],
            'a sum that is not declared' => [Widget::stat('a')->resource('runs')->sum('title'), 'The stat "a" sums "title" of "runs", which isn\'t in the sum of the aggregates of its list.'],
            'a resource without aggregates' => [Widget::series('a')->resource('posts')->per('day', 'published_at'), 'The series "a" reads the aggregates of "posts", whose list declares none: add aggregates() to its list.'],
            'a measure without a resource' => [Widget::stat('a')->using(OverdueWidget::class)->target(10), 'The widget "a" measures a resource (count(), sum(), per(), by(), points(), split() or target()) without one: give it a resource().'],
        ];
    }

    #[DataProvider('invalidWidgets')]
    public function test_invalid_widgets_on_aggregates_throw_when_the_definition_is_built(Widget $widget, string $message): void
    {
        Panneau::dashboard([$widget]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        $this->definition();
    }
}
