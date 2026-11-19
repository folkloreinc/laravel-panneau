<?php

namespace Panneau\Tests\Concerns;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Checks data against the protocol's schemas, copied in tests/protocol.
 */
trait ValidatesProtocol
{
    private static ?Validator $protocolValidator = null;

    protected static function protocolValidator(): Validator
    {
        if (self::$protocolValidator === null) {
            $validator = new Validator;
            $validator->setMaxErrors(10);
            $resolver = $validator->resolver();
            $resolver?->registerFile(
                'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/definition.schema.json',
                __DIR__.'/../protocol/definition.schema.json',
            );
            $resolver?->registerFile(
                'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/openapi.json',
                __DIR__.'/../protocol/openapi.json',
            );
            $resolver?->registerFile(
                'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/boot.schema.json',
                __DIR__.'/../protocol/boot.schema.json',
            );
            self::$protocolValidator = $validator;
        }

        return self::$protocolValidator;
    }

    /**
     * Asserts that data matches a JSON Schema, given by URI.
     */
    protected function assertMatchesSchema(mixed $data, string $schema, string $message = ''): void
    {
        $json = json_decode((string) json_encode($data));
        $result = self::protocolValidator()->validate($json, $schema);
        $errors = $result->error() !== null ? (new ErrorFormatter)->format($result->error()) : [];

        $this->assertTrue(
            $result->isValid(),
            trim($message."\n".json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
        );
    }

    /**
     * Asserts that a definition matches definition.schema.json, and that its references are
     * valid, as the protocol's lintDefinition() checks.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function assertValidDefinition(array $definition): void
    {
        $this->assertMatchesSchema($definition, 'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/definition.schema.json');
        $this->assertSame([], self::lintDefinition($definition), 'The definition has invalid references.');
    }

    /**
     * Asserts that a boot config matches boot.schema.json, and that its definition is valid,
     * as the protocol's validateBootConfig() checks.
     *
     * @param  array<string, mixed>  $boot
     */
    protected function assertValidBootConfig(array $boot): void
    {
        $this->assertMatchesSchema($boot, 'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/boot.schema.json');
        if (isset($boot['definition'])) {
            $this->assertValidDefinition($boot['definition']);
        }
    }

    /**
     * Asserts that data matches a component schema of openapi.json.
     */
    protected function assertMatchesComponent(mixed $data, string $component): void
    {
        $this->assertMatchesSchema($data, 'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/openapi.json#/components/schemas/'.$component, $component);
    }

    /**
     * A port of the protocol's lintDefinition().
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    private static function lintDefinition(array $definition): array
    {
        $issues = [];
        $resourceIds = array_column($definition['resources'], 'id');
        $libraries = array_column(array_filter($definition['resources'], fn (array $resource): bool => ($resource['kind'] ?? null) === 'media'), 'id');
        foreach ($definition['resources'] as $index => $resource) {
            $path = 'resources.'.$index;
            $names = array_column($resource['fields'], 'name');
            if (count($names) !== count(array_unique($names))) {
                $issues[] = $path.'.fields: field names must be unique.';
            }
            self::lintLayout($resource['form']['layout'] ?? [], $names, $path.'.form.layout', $issues);
            foreach ($resource['list']['columns'] ?? [] as $columnIndex => $column) {
                if (isset($column['field']) && ! in_array($column['field'], $names, true)) {
                    $issues[] = $path.'.list.columns.'.$columnIndex.': no field '.$column['field'];
                }
            }
            foreach ($resource['actions'] ?? [] as $actionIndex => $action) {
                self::lintLayout($action['layout'] ?? [], array_column($action['fields'] ?? [], 'name'), $path.'.actions.'.$actionIndex.'.layout', $issues);
            }
            if (($resource['kind'] ?? null) === 'media') {
                $file = array_search('file', $names, true);
                if ($file === false) {
                    $issues[] = $path.'.fields: a resource of kind media needs a file field.';
                } elseif ($resource['fields'][$file]['type'] !== 'file' || ($resource['fields'][$file]['multiple'] ?? false) === true) {
                    $issues[] = $path.'.fields.'.$file.': the file field of a media resource must be a single file.';
                }
            }
            foreach ($resource['fields'] as $fieldIndex => $field) {
                if ($field['type'] === 'relation' && ! in_array($field['resource'] ?? null, $resourceIds, true)) {
                    $issues[] = $path.'.fields.'.$fieldIndex.': no resource '.($field['resource'] ?? '(none)');
                }
                if (in_array($field['type'], ['media', 'image'], true)) {
                    if (! isset($field['resource'])) {
                        // Every library of the definition (decision 0027)
                        if (count($libraries) === 0) {
                            $issues[] = $path.'.fields.'.$fieldIndex.': a '.$field['type'].' field needs a resource of kind media.';
                        }
                    } else {
                        foreach ((array) $field['resource'] as $resourceIndex => $id) {
                            if (! in_array($id, $libraries, true)) {
                                $issues[] = $path.'.fields.'.$fieldIndex.'.resource'.(is_array($field['resource']) ? '.'.$resourceIndex : '').': no resource of kind media named '.$id;
                            }
                        }
                    }
                }
            }
            self::lintAggregates($resource, $path, $issues);
        }
        self::lintOverviews($definition, $issues);
        self::lintParents($definition, $issues);

        return $issues;
    }

    /**
     * The `parent` of resources and pages, as `lintDefinition()` checks it (decision 0093): a
     * page of `pages` that a link leads to, or for a page, a resource of the definition, and no
     * chain that comes back to where it started.
     *
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $issues
     */
    private static function lintParents(array $definition, array &$issues): void
    {
        $pages = $definition['pages'] ?? [];
        $checkPage = function (string $page, string $path) use ($pages, &$issues): void {
            if (! isset($pages[$page])) {
                $issues[] = $path.': no page '.$page;
            } elseif ($page === 'home') {
                $issues[] = $path.': the home page is never a parent';
            } elseif (($pages[$page]['params'] ?? []) !== []) {
                $issues[] = $path.': the page '.$page.' has params';
            }
        };
        $parentOf = [];
        foreach ($definition['resources'] as $index => $resource) {
            if (isset($resource['parent'])) {
                $checkPage($resource['parent'], 'resources.'.$index.'.parent');
                $parentOf['resource:'.$resource['id']] = 'page:'.$resource['parent'];
            }
        }
        foreach ($pages as $id => $page) {
            $parent = $page['parent'] ?? null;
            if (is_string($parent)) {
                if ($parent === $id) {
                    $issues[] = 'pages.'.$id.'.parent: a page is not its own parent';
                } else {
                    $checkPage($parent, 'pages.'.$id.'.parent');
                    $parentOf['page:'.$id] = 'page:'.$parent;
                }
            } elseif (is_array($parent)) {
                if (! in_array($parent['resource'], array_column($definition['resources'], 'id'), true)) {
                    $issues[] = 'pages.'.$id.'.parent.resource: no resource '.$parent['resource'];
                }
                $parentOf['page:'.$id] = 'resource:'.$parent['resource'];
            }
        }
        foreach (array_keys($parentOf) as $start) {
            $seen = [$start];
            $current = $parentOf[$start];
            while (isset($parentOf[$current]) && $current !== $start && ! in_array($current, $seen, true)) {
                $seen[] = $current;
                $current = $parentOf[$current];
            }
            if ($current === $start) {
                $issues[] = $start.': its parents come back to it';
            }
        }
    }

    /**
     * The aggregates of a list, as `lintDefinition()` checks them (decision 0084): each one names
     * a field the user can view, `sum` a number or money of one currency, `dates` a date,
     * `groupBy` a value.
     *
     * @param  array<string, mixed>  $resource
     * @param  list<string>  $issues
     */
    private static function lintAggregates(array $resource, string $path, array &$issues): void
    {
        $aggregates = $resource['list']['aggregates'] ?? null;
        if (! is_array($aggregates)) {
            return;
        }
        $fields = [...$resource['fields'], ...array_merge(...array_map(fn (array $type): array => $type['fields'] ?? [], $resource['types'] ?? []))];
        $byName = array_column($fields, null, 'name');
        $checks = [
            'sum' => fn (array $field): bool => in_array($field['type'], ['number', 'range', 'duration', 'money'], true)
                && ($field['type'] !== 'money' || is_string($field['settings']['currency'] ?? null)),
            'dates' => fn (array $field): bool => in_array($field['type'], ['date', 'datetime'], true),
            'groupBy' => fn (array $field): bool => ! in_array($field['type'], ['richtext', 'media', 'image', 'file', 'group', 'repeater', 'json', 'embed', 'location', 'address', 'link', 'daterange', 'money'], true),
        ];
        foreach ($checks as $key => $accepts) {
            foreach ($aggregates[$key] ?? [] as $index => $name) {
                $field = $byName[$name] ?? null;
                if ($field === null || ($field['abilities']['view'] ?? true) === false || ! $accepts($field)) {
                    $issues[] = $path.'.list.aggregates.'.$key.'.'.$index.': '.$name.' can\'t be aggregated there';
                }
            }
        }
    }

    /**
     * The dashboards and the summaries, as `lintDefinition()` checks them (decisions 0064, 0084
     * and 0089): widgets on a resource that exists, with the aggregates of its list, `followsList`
     * only in a summary, `period` only in an overview with a period, on a `date-range` filter, the
     * settings of each type, and the sources of a series and the rows of a table.
     *
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $issues
     */
    private static function lintOverviews(array $definition, array &$issues): void
    {
        $resources = array_column($definition['resources'], null, 'id');
        $home = $definition['pages']['home'] ?? null;
        $homePeriod = (($home['component'] ?? null) === 'dashboard' ? ($home['period'] ?? null) : null) ?? ($definition['dashboard']['period'] ?? null);
        $overviews = [];
        if (isset($definition['dashboard'])) {
            $overviews[] = ['dashboard.widgets', $definition['dashboard']['widgets'], null, $homePeriod !== null];
        }
        foreach ($definition['pages'] ?? [] as $id => $page) {
            if ((isset($page['widgets']) || isset($page['period'])) && $page['component'] !== 'dashboard') {
                $issues[] = 'pages.'.$id.': only a dashboard page has widgets and a period';
            }
            if (isset($page['widgets'])) {
                $overviews[] = ['pages.'.$id.'.widgets', $page['widgets'], null, ($id === 'home' ? $homePeriod : ($page['period'] ?? null)) !== null];
            }
        }
        foreach ($definition['resources'] as $index => $resource) {
            $summary = $resource['summary'] ?? null;
            if ($summary === null) {
                continue;
            }
            if (isset($summary['period'])) {
                $filters = array_column(array_filter($resource['list']['filters'] ?? [], fn (array $filter): bool => $filter['type'] === 'date-range'), 'id');
                $filter = $summary['period']['filter'] ?? ($filters[0] ?? null);
                if (! in_array($filter, $filters, true)) {
                    $issues[] = 'resources.'.$index.'.summary.period: no date-range filter '.($filter ?? '(none)');
                }
            }
            $overviews[] = ['resources.'.$index.'.summary.widgets', $summary['widgets'], $resource['id'], isset($summary['period'])];
        }
        foreach ($overviews as [$path, $widgets, $summaryOf, $hasPeriod]) {
            foreach ($widgets as $index => $widget) {
                $widgetPath = $path.'.'.$index;
                $settings = $widget['settings'] ?? [];
                $on = $settings['resource'] ?? null;
                if (isset($settings['followsList']) && ($summaryOf === null || ($on !== null && $on !== $summaryOf))) {
                    $issues[] = $widgetPath.'.settings.followsList: only the widgets of a summary on its resource follow its list';
                }
                if (isset($settings['period']) && ! $hasPeriod) {
                    $issues[] = $widgetPath.'.settings.period: its overview has no period';
                }
                if (! in_array($widget['type'], ['stat', 'series', 'list', 'breakdown', 'metric', 'table'], true)) {
                    continue;
                }
                foreach (['goodWhen' => ['metric', 'table'], 'points' => ['metric', 'table'], 'by' => ['metric'], 'split' => ['series'], 'sources' => ['series'], 'stacked' => ['series'], 'rows' => ['table']] as $key => $types) {
                    if (isset($settings[$key]) && ! in_array($widget['type'], $types, true)) {
                        $issues[] = $widgetPath.'.settings.'.$key.': only a '.implode(' or a ', $types).' has '.$key;
                    }
                }
                $sourcesKey = $widget['type'] === 'table' ? 'rows' : 'sources';
                if (isset($settings[$sourcesKey]) && in_array($widget['type'], ['series', 'table'], true)) {
                    if ($on !== null) {
                        $issues[] = $widgetPath.'.settings.'.$sourcesKey.': the widget has no resource';

                        continue;
                    }
                    foreach (['query', 'measure', 'target', 'by', 'split'] as $key) {
                        if (isset($settings[$key])) {
                            $issues[] = $widgetPath.'.settings.'.$key.': goes with a resource, not '.$sourcesKey;
                        }
                    }
                    self::lintSources($resources, $widget, $widgetPath, $hasPeriod, $issues);

                    continue;
                }
                if ($on === null) {
                    if (is_string($settings['period'] ?? null)) {
                        $issues[] = $widgetPath.'.settings.period: without a resource, it can only be false';
                    }
                    foreach (['query', 'measure', 'group', 'target', 'points', 'by', 'split'] as $key) {
                        if (isset($settings[$key])) {
                            $issues[] = $widgetPath.'.settings.'.$key.': goes with a resource';
                        }
                    }

                    continue;
                }
                if ($widget['type'] === 'table') {
                    $issues[] = $widgetPath.'.settings.resource: a table can\'t read a resource';

                    continue;
                }
                $resource = $resources[$on] ?? null;
                if ($resource === null) {
                    $issues[] = $widgetPath.'.settings.resource: no resource '.$on;

                    continue;
                }
                $filters = array_column($resource['list']['filters'] ?? [], 'type', 'id');
                if (is_string($settings['period'] ?? null) && ($filters[$settings['period']] ?? null) !== 'date-range') {
                    $issues[] = $widgetPath.'.settings.period: no date-range filter '.$settings['period'];
                }
                $aggregates = $resource['list']['aggregates'] ?? [];
                $measure = $settings['measure'] ?? null;
                $group = $settings['group'] ?? null;
                if ($widget['type'] === 'list' && ($measure !== null || $group !== null)) {
                    $issues[] = $widgetPath.'.settings: a list has no measure or group';
                }
                if (is_string($measure) && str_starts_with($measure, 'sum:') && ! in_array(substr($measure, 4), $aggregates['sum'] ?? [], true)) {
                    $issues[] = $widgetPath.'.settings.measure: '.$measure.' isn\'t in the sum of the aggregates';
                }
                if ($widget['type'] === 'series' && ! self::isPeriodOf($group, $aggregates)) {
                    $issues[] = $widgetPath.'.settings.group: a series needs a period of a date of the aggregates';
                }
                if ($widget['type'] === 'breakdown' && (! is_string($group) || ! in_array($group, $aggregates['groupBy'] ?? [], true))) {
                    $issues[] = $widgetPath.'.settings.group: a breakdown needs a field of the groupBy of the aggregates';
                }
                if ($widget['type'] === 'metric') {
                    if (! isset($resource['list']['aggregates'])) {
                        $issues[] = $widgetPath.'.settings.resource: a metric reads the aggregates of a list that declares none';
                    }
                    if ($group !== null) {
                        $issues[] = $widgetPath.'.settings.group: a metric isn\'t grouped';
                    }
                    if (isset($settings['points']) && ! self::isPeriodOf($settings['points'], $aggregates)) {
                        $issues[] = $widgetPath.'.settings.points: a period of a date of the aggregates';
                    }
                    if (isset($settings['by']) && ! in_array($settings['by'], $aggregates['groupBy'] ?? [], true)) {
                        $issues[] = $widgetPath.'.settings.by: a field of the groupBy of the aggregates';
                    }
                }
                if (isset($settings['split']) && (! in_array($settings['split'], $aggregates['groupBy'] ?? [], true) || ($filters[$settings['split']] ?? 'date-range') === 'date-range')) {
                    $issues[] = $widgetPath.'.settings.split: a field of the groupBy of the aggregates, and a filter of the list';
                }
                if (isset($settings['target']) && ! in_array($widget['type'], ['stat', 'metric'], true)) {
                    $issues[] = $widgetPath.'.settings.target: only a stat and a metric have a target';
                }
            }
        }
    }

    /**
     * Whether a group is a period of a date of the aggregates of a list: `day:created_at`.
     *
     * @param  array<string, mixed>  $aggregates
     */
    private static function isPeriodOf(mixed $group, array $aggregates): bool
    {
        return is_string($group)
            && preg_match('/^(day|week|month):(.+)$/', $group, $matches) === 1
            && in_array($matches[2], $aggregates['dates'] ?? [], true);
    }

    /**
     * The sources of a `series` or the rows of a `table`, as `checkSources()` of
     * `lintDefinition()` checks them (decision 0089).
     *
     * @param  array<string, array<string, mixed>>  $resources
     * @param  array<string, mixed>  $widget
     * @param  list<string>  $issues
     */
    private static function lintSources(array $resources, array $widget, string $path, bool $hasPeriod, array &$issues): void
    {
        $settings = $widget['settings'];
        $isSeries = $widget['type'] === 'series';
        $key = $isSeries ? 'sources' : 'rows';
        $periods = [];
        foreach ($settings[$key] as $index => $source) {
            $sourcePath = $path.'.settings.'.$key.'.'.$index;
            $group = $source[$isSeries ? 'group' : 'points'] ?? ($settings[$isSeries ? 'group' : 'points'] ?? null);
            if (is_string($group) && preg_match('/^(day|week|month):/', $group, $matches) === 1) {
                $periods[$matches[1]] = true;
            }
            $resource = $resources[$source['resource']] ?? null;
            if ($resource === null) {
                $issues[] = $sourcePath.'.resource: no resource '.$source['resource'];

                continue;
            }
            $aggregates = $resource['list']['aggregates'] ?? null;
            if ($aggregates === null) {
                $issues[] = $sourcePath.'.resource: its list declares no aggregates';

                continue;
            }
            $measure = $source['measure'] ?? null;
            if (is_string($measure) && str_starts_with($measure, 'sum:') && ! in_array(substr($measure, 4), $aggregates['sum'] ?? [], true)) {
                $issues[] = $sourcePath.'.measure: '.$measure.' isn\'t in the sum of the aggregates';
            }
            if (($isSeries || $group !== null) && ! self::isPeriodOf($group, $aggregates)) {
                $issues[] = $sourcePath.': its group needs a period of a date of the aggregates';
            }
            $filters = array_column($resource['list']['filters'] ?? [], 'type', 'id');
            $period = array_key_exists('period', $source) ? $source['period'] : ($settings['period'] ?? null);
            if (is_string($period)) {
                if (isset($source['period']) && ! $hasPeriod) {
                    $issues[] = $sourcePath.'.period: its overview has no period';
                }
                if (($filters[$period] ?? null) !== 'date-range') {
                    $issues[] = $sourcePath.'.period: no date-range filter '.$period;
                }
            }
            foreach ([...array_keys($source['query'] ?? []), ...array_keys($source['alert']['query'] ?? [])] as $param) {
                if (preg_match('/^filter\[([^\]]+)\]/', $param, $matches) === 1 && ! isset($filters[$matches[1]])) {
                    $issues[] = $sourcePath.'.query: no filter '.$matches[1];
                }
            }
        }
        if (count($periods) > 1) {
            $issues[] = $path.'.settings.'.$key.': its lines or trends share one period';
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $names
     * @param  list<string>  $issues
     */
    private static function lintLayout(array $nodes, array $names, string $path, array &$issues): void
    {
        foreach ($nodes as $index => $node) {
            $nodePath = $path.'.'.$index;
            if ($node['type'] === 'field' && ! in_array($node['name'] ?? null, $names, true)) {
                $issues[] = $nodePath.': no field '.($node['name'] ?? '(none)');
            }
            if ($node['type'] === 'tabs') {
                foreach ($node['children'] ?? [] as $child) {
                    if ($child['type'] !== 'tab') {
                        $issues[] = $nodePath.': the children of tabs must be tab nodes.';
                    }
                }
            }
            self::lintLayout($node['children'] ?? [], $names, $nodePath.'.children', $issues);
        }
    }
}
