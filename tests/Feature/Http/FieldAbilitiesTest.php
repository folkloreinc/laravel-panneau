<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Panneau\Fields\Json;
use Panneau\Fields\Text;
use Panneau\Resources\Resource;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\Concerns\ValidatesProtocol;
use TestApp\Models\Report;
use TestApp\Panneau\Actions\ArchiveReports;
use TestApp\Policies\ReportPolicy;

/**
 * Abilities of fields, and the `deleteAny()` and `runAction()` of policies (panneau-js decision
 * 0046): the definition says what the user can do, and the API agrees.
 */
class FieldAbilitiesTest extends HttpTestCase
{
    use ValidatesProtocol;

    /**
     * Gate abilities granted by the current test.
     *
     * @var list<string>
     */
    private static array $granted = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$granted = [];
        ReportPolicy::$denied = [];
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::define('reports.code.update', fn (Authenticatable $user): bool => in_array('reports.code.update', self::$granted, true));
        Gate::define('reports.sections.view', fn (Authenticatable $user): bool => in_array('reports.sections.view', self::$granted, true));
        Panneau::resources([new class extends Resource
        {
            public function id(): string
            {
                return 'reports';
            }

            public function model(): string
            {
                return Report::class;
            }

            public function fields(): array
            {
                return [
                    Text::make('title')->required(),
                    Text::make('terms')->canUpdate('reports.code.update'),
                    Json::make('sections')->canSee('reports.sections.view'),
                    Text::make('cover')->canSee(fn (?Authenticatable $user): bool => $user?->getAuthIdentifier() === 2),
                ];
            }

            public function actions(): array
            {
                return [ArchiveReports::class];
            }
        }]);
    }

    protected function tearDown(): void
    {
        ReportPolicy::$denied = [];

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function reports(): array
    {
        $response = $this->getJson(self::API.'/definition')->assertOk();
        $this->assertValidDefinition($response->json());

        return collect($response->json('resources'))->firstWhere('id', 'reports');
    }

    private function createReport(string $title = 'Q3'): Report
    {
        return Report::query()->create(['title' => $title, 'terms' => ['a'], 'sections' => ['b'], 'cover' => ['c']]);
    }

    public function test_the_definition_gives_the_abilities_the_user_lacks(): void
    {
        $fields = collect($this->reports()['fields'])->keyBy('name');
        $this->assertArrayNotHasKey('abilities', $fields['title']);
        $this->assertSame(['update' => false], $fields['terms']['abilities']);
        $this->assertSame(['view' => false], $fields['sections']['abilities']);
        $this->assertSame(['view' => false], $fields['cover']['abilities']);

        self::$granted = ['reports.code.update', 'reports.sections.view'];
        $fields = collect($this->reports()['fields'])->keyBy('name');
        $this->assertArrayNotHasKey('abilities', $fields['terms']);
        $this->assertArrayNotHasKey('abilities', $fields['sections']);
    }

    public function test_items_leave_out_the_values_of_hidden_fields(): void
    {
        $report = $this->createReport();

        $item = $this->getJson(self::API.'/resources/reports/'.$report->getKey())->assertOk()->json('data');
        $this->assertArrayHasKey('terms', $item);
        $this->assertArrayNotHasKey('sections', $item);
        $this->assertArrayNotHasKey('cover', $item);
        $listed = $this->getJson(self::API.'/resources/reports')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('sections', $listed);

        self::$granted = ['reports.sections.view'];
        $this->getJson(self::API.'/resources/reports/'.$report->getKey())->assertOk()->assertJsonPath('data.sections', ['b']);
    }

    public function test_saving_ignores_the_values_of_fields_the_user_cant_see_or_edit(): void
    {
        $report = $this->createReport();

        $this->patchJson(self::API.'/resources/reports/'.$report->getKey(), ['title' => 'Q4', 'terms' => 'x', 'sections' => ['y']])
            ->assertOk()
            ->assertJsonPath('data.title', 'Q4');
        $report->refresh();
        $this->assertSame(['a'], $report->terms);
        $this->assertSame(['b'], $report->sections);

        $created = $this->postJson(self::API.'/resources/reports', ['title' => 'Q1', 'sections' => ['z']])->assertCreated();
        $this->assertNull(Report::query()->findOrFail($created->json('data.id'))->sections);

        self::$granted = ['reports.code.update'];
        $this->patchJson(self::API.'/resources/reports/'.$report->getKey(), ['terms' => 'x'])->assertOk();
        $this->assertSame('x', $report->refresh()->terms);
    }

    public function test_a_policy_that_refuses_delete_any_hides_the_built_in_delete(): void
    {
        $report = $this->createReport();
        $this->assertNotContains('delete', array_column($this->reports()['actions'], 'id'));

        ReportPolicy::$denied = ['deleteAny'];
        $delete = collect($this->reports()['actions'])->firstWhere('id', 'delete');
        $this->assertSame(['id' => 'delete', 'kind' => 'server', 'scope' => ['item', 'selection'], 'abilities' => ['run' => false]], $delete);
        $this->postJson(self::API.'/resources/reports/actions/delete', ['selection' => ['ids' => [(string) $report->getKey()]]])->assertForbidden();
        $this->assertNotNull($report->fresh());
    }

    public function test_a_policy_with_run_action_answers_every_action(): void
    {
        $locked = $this->createReport('Locked');
        $open = $this->createReport();

        $archive = collect($this->reports()['actions'])->firstWhere('id', 'archive-reports');
        $this->assertSame(['run' => true], $archive['abilities']);
        $this->getJson(self::API.'/resources/reports/'.$locked->getKey())->assertJsonPath('data.abilities.archive-reports', false);
        $this->getJson(self::API.'/resources/reports/'.$open->getKey())->assertJsonPath('data.abilities.archive-reports', true);

        ReportPolicy::$denied = ['run:archive-reports'];
        $archive = collect($this->reports()['actions'])->firstWhere('id', 'archive-reports');
        $this->assertSame(['run' => false], $archive['abilities']);
        $this->postJson(self::API.'/resources/reports/actions/archive-reports', ['selection' => ['ids' => [(string) $open->getKey()]]])->assertForbidden();
    }
}
