<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Gate;
use Panneau\Dashboard\Summary;
use Panneau\Dashboard\Widget;
use Panneau\Dashboard\WidgetRequest;
use Panneau\Fields\Text;
use Panneau\Http\Problem;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use PHPUnit\Framework\Attributes\DataProvider;
use TestApp\Dashboard\OverdueWidget;
use TestApp\Models\Run;
use TestApp\Panneau\Resources\RunsResource;
use TestApp\Policies\RunPolicy;

/**
 * `GET /dashboard/widgets/{widget}?resource={resource}`: the widgets of the summary of a
 * resource, with the query of its list (panneau-js decision 0084).
 */
class SummaryEndpointTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RunPolicy::$denied = [];
        Gate::policy(Run::class, RunPolicy::class);
        Panneau::resources([RunsResource::class]);
        Panneau::dashboard([Widget::stat('overdue')->using(OverdueWidget::class)]);
    }

    protected function tearDown(): void
    {
        RunPolicy::$denied = [];

        parent::tearDown();
    }

    public function test_a_widget_of_a_summary_gets_the_filters_and_the_search_of_the_list(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/matching?resource=runs')
            ->assertOk()
            ->assertExactJson(['value' => 0, 'help' => '']);
        // As the list reads them: filters it doesn't declare, its sort and its paging don't count
        $this->getJson(self::API.'/dashboard/widgets/matching?resource=runs&filter[status][]=failed&filter[ran_at][from]=2026-10-01&filter[color]=red&q=import&sort=-ran_at&perPage=5')
            ->assertOk()
            ->assertExactJson(['value' => 2, 'help' => 'status,ran_at q=import']);
    }

    public function test_a_widget_that_does_not_follow_the_list_gets_none_of_its_query(): void
    {
        $this->getJson(self::API.'/dashboard/widgets/everything?resource=runs&filter[status][]=failed&q=import')
            ->assertOk()
            ->assertExactJson(['value' => 0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingWidgets(): array
    {
        return [
            'a widget on the resource' => ['failed?resource=runs'],
            'a widget of the home page' => ['overdue?resource=runs'],
            'a widget of a summary on the home page' => ['matching'],
            'a resource without a summary' => ['matching?resource=posts'],
            'an unknown resource' => ['matching?resource=nope'],
        ];
    }

    #[DataProvider('missingWidgets')]
    public function test_a_widget_that_is_not_in_that_summary_of_the_user_is_a_404(string $path): void
    {
        $this->getJson(self::API.'/dashboard/widgets/'.$path)
            ->assertNotFound()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    public function test_the_summary_of_a_resource_the_user_can_not_list_is_a_403(): void
    {
        RunPolicy::$denied = ['viewAny'];

        $this->getJson(self::API.'/dashboard/widgets/matching?resource=runs')
            ->assertForbidden()
            ->assertHeader('Content-Type', Problem::CONTENT_TYPE);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidPlaces(): array
    {
        return [
            'a page and a resource' => ['resource=runs&page=home', 'page', 'A widget is on a page or in a summary, not both.'],
            'an empty resource' => ['resource=', 'resource', 'The resource must not be empty.'],
        ];
    }

    #[DataProvider('invalidPlaces')]
    public function test_a_page_and_a_resource_together_or_an_empty_one_is_a_422(string $query, string $key, string $message): void
    {
        $this->getJson(self::API.'/dashboard/widgets/matching?'.$query)
            ->assertUnprocessable()
            ->assertJsonPath('type', Problem::VALIDATION)
            ->assertJsonPath('errors.'.$key.'.0', $message);
    }

    public function test_a_cached_widget_of_a_summary_is_kept_per_query_of_the_list(): void
    {
        $calls = 0;
        $logs = new class(function (WidgetRequest $request) use (&$calls): array {
            $calls++;

            return ['value' => $calls, 'help' => (string) $request->resource];
        }) extends Resource
        {

            public function __construct(private \Closure $data) {}

            public function id(): string
            {
                return 'logs';
            }

            public function model(): string
            {
                return Run::class;
            }

            public function fields(): array
            {
                return [Text::make('title'), Text::make('status')];
            }

            public function list(ResourceList $list): ResourceList
            {
                return $list->filters([SelectFilter::make('status')->options(['failed' => 'Failed', 'succeeded' => 'Succeeded'])]);
            }

            public function summary(): Summary
            {
                return Summary::make([Widget::stat('cached')->cache(60)->using($this->data)]);
            }
        };
        Panneau::resources([$logs]);

        $this->getJson(self::API.'/dashboard/widgets/cached?resource=logs')->assertJsonPath('value', 1)->assertJsonPath('help', 'logs');
        $this->getJson(self::API.'/dashboard/widgets/cached?resource=logs')->assertJsonPath('value', 1);
        $this->getJson(self::API.'/dashboard/widgets/cached?resource=logs&filter[status]=failed')->assertJsonPath('value', 2);
        $this->getJson(self::API.'/dashboard/widgets/cached?resource=logs&filter[status]=failed')->assertJsonPath('value', 2);
        $this->getJson(self::API.'/dashboard/widgets/cached?resource=logs&q=x')->assertJsonPath('value', 1);
    }
}
