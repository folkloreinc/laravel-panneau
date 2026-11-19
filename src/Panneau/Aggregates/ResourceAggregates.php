<?php

namespace Panneau\Aggregates;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\Repositories\Aggregates;
use Panneau\Contracts\Repositories\ReportsInMemoryAggregates;
use Panneau\Exceptions\TooManyItemsToAggregateException;
use Panneau\Fields\Field;
use Panneau\Fields\Relation;
use Panneau\Query\ResourceQuery;
use Panneau\Resources\ItemSerializer;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * The aggregates of the lists (panneau-js decision 0084): what a list declares, checked, what a
 * user can aggregate, the parameters of `GET /resources/{resource}/aggregate`, and its answers in
 * the shape of a widget, as `aggregateItems()` of `@panneau/protocol` computes them.
 *
 * @internal
 */
final class ResourceAggregates
{
    /**
     * The most points of a `series`: a year of days. A longer range keeps its latest periods.
     */
    public const MAX_POINTS = 366;

    /**
     * The most parts of a `breakdown`: the rest is its `other`.
     */
    public const MAX_PARTS = 10;

    /**
     * Field types whose values add up: numbers, and the amounts of `money` of one currency.
     */
    private const SUMMABLE_TYPES = ['number', 'range', 'duration', 'money'];

    /**
     * Field types whose days group the items.
     */
    private const DATE_TYPES = ['date', 'datetime'];

    /**
     * Field types whose values are structures, which don't group the items.
     */
    private const UNGROUPABLE_TYPES = ['richtext', 'media', 'image', 'file', 'group', 'repeater', 'json', 'embed', 'location', 'address', 'link', 'daterange', 'money'];

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly ItemSerializer $serializer,
    ) {}

    /**
     * The aggregates the list of a resource declares, checked: each one names a field of the
     * resource of the right type, the repository implements `Aggregates`, and it doesn't
     * aggregate the field in memory unless the list allows it (`aggregates(inMemory:)`, decision
     * 0024). Null without them.
     *
     * @return array{sum: list<string>, groupBy: list<string>, dates: list<string>}|null
     */
    public function declared(Resource $resource): ?array
    {
        $list = $this->resources->list($resource);
        $aggregates = $list->getAggregates();
        if ($aggregates === null) {
            return null;
        }
        $repository = $this->resources->repository($resource);
        if (! $repository instanceof Aggregates) {
            throw new LogicException(sprintf('The list of "%s" declares aggregates, but its repository %s doesn\'t implement %s.', $resource->id(), $repository::class, Aggregates::class));
        }
        foreach ($aggregates as $key => $names) {
            foreach ($names as $name) {
                $field = $this->field($resource, $name);
                if ($field === null) {
                    throw new LogicException(sprintf('The aggregates of "%s" name the field "%s" in %s, which the resource doesn\'t have.', $resource->id(), $name, $key));
                }
                $problem = match ($key) {
                    'sum' => $this->summable($field),
                    'dates' => in_array($field->type(), self::DATE_TYPES, true) ? null : 'must be a date or a datetime to be grouped by period',
                    'groupBy' => in_array($field->type(), self::UNGROUPABLE_TYPES, true) ? sprintf('is a %s, whose values don\'t group the items', $field->type()) : null,
                };
                if ($problem !== null) {
                    throw new LogicException(sprintf('The field "%s" of "%s" in the aggregates %s %s.', $name, $resource->id(), $key, $problem));
                }
                if ($repository instanceof ReportsInMemoryAggregates && ! in_array($name, $list->getAggregatesInMemory(), true) && $this->inMemory($repository, $key, $name)) {
                    throw new LogicException(sprintf('The field "%s" of "%s" in the aggregates %s is aggregated in memory by %s, over every matching item: aggregate a column, or allow it with aggregates(inMemory: [\'%s\']), up to panneau.aggregates.max_in_memory items.', $name, $resource->id(), $key, $repository::class, $name));
                }
            }
        }

        return $aggregates;
    }

    /**
     * Whether a repository aggregates a field of the aggregates in memory: summed, grouped by its
     * values, or by the periods of its dates.
     *
     * @param  'sum'|'groupBy'|'dates'  $key
     */
    private function inMemory(ReportsInMemoryAggregates $repository, string $key, string $name): bool
    {
        return match ($key) {
            'sum' => $repository->aggregatesInMemory(Measure::sum($name)),
            'groupBy' => $repository->aggregatesInMemory(Measure::count(), Group::field($name)),
            'dates' => $repository->aggregatesInMemory(Measure::count(), Group::period(Interval::Day, $name)),
        };
    }

    /**
     * The aggregates a user can ask for: those of the list, without the fields they can't see.
     * Null without any.
     *
     * @return array{sum: list<string>, groupBy: list<string>, dates: list<string>}|null
     */
    public function forUser(Resource $resource, ?Authenticatable $user): ?array
    {
        $aggregates = $this->declared($resource);
        if ($aggregates === null) {
            return null;
        }
        $visible = fn (string $name): bool => $this->authorizer->canSeeField($user, $this->field($resource, $name) ?? throw new LogicException);
        $aggregates = [
            'sum' => array_values(array_filter($aggregates['sum'], $visible)),
            'groupBy' => array_values(array_filter($aggregates['groupBy'], $visible)),
            'dates' => array_values(array_filter($aggregates['dates'], $visible)),
        ];

        return $aggregates['sum'] === [] && $aggregates['groupBy'] === [] && $aggregates['dates'] === [] ? null : $aggregates;
    }

    /**
     * The aggregates of a list in the definition, for a user: the keys that have fields.
     *
     * @return array<string, list<string>>|null
     */
    public function definition(Resource $resource, ?Authenticatable $user): ?array
    {
        $aggregates = $this->forUser($resource, $user);

        return $aggregates !== null ? array_filter($aggregates, fn (array $names): bool => $names !== []) : null;
    }

    /**
     * Reads `measure` and `group` of the query string against the aggregates of the user, as
     * `readAggregateQuery()` of `@panneau/protocol` does: `measure` defaults to `count`, a `sum:`
     * names a field of `sum`, a period a field of `dates`, another group a field of `groupBy`.
     *
     * @param  array<array-key, mixed>  $parameters
     * @param  array{sum: list<string>, groupBy: list<string>, dates: list<string>}  $aggregates
     * @return array{Measure, Group|null}
     *
     * @throws ValidationException With errors keyed by `measure` or `group`.
     */
    public function read(array $parameters, array $aggregates): array
    {
        $errors = [];
        $rawMeasure = $parameters['measure'] ?? Measure::COUNT;
        $measure = is_string($rawMeasure) ? Measure::parse($rawMeasure) : null;
        if ($measure === null) {
            $errors['measure'] = [__('The measure must be `count` or `sum:{field}`.')];
        } elseif ($measure->field !== null && ! in_array($measure->field, $aggregates['sum'], true)) {
            $errors['measure'] = [__('The field `:field` can\'t be summed.', ['field' => $measure->field])];
        }

        $group = null;
        if (array_key_exists('group', $parameters)) {
            $rawGroup = $parameters['group'];
            $group = is_string($rawGroup) ? Group::parse($rawGroup) : null;
            if ($group === null) {
                $errors['group'] = [__('The group must be `{day|week|month}:{date field}` or a field.')];
            } elseif ($group->isPeriod() && ! in_array($group->field, $aggregates['dates'], true)) {
                $errors['group'] = [__('The field `:field` isn\'t a date that can be grouped.', ['field' => $group->field])];
            } elseif (! $group->isPeriod() && ! in_array($group->field, $aggregates['groupBy'], true)) {
                $errors['group'] = [__('The items can\'t be grouped by the field `:field`.', ['field' => $group->field])];
            }
        }
        if ($errors !== [] || $measure === null) {
            throw ValidationException::withMessages($errors);
        }

        return [$measure, $group];
    }

    /**
     * The aggregate of the items of a query, in the shape of a widget: a `stat` without a group,
     * a `series` by a period, a `breakdown` by a field.
     *
     * - A series has a point per period, at 0 without items, from the period of the `from` bound
     *   of the `date-range` filter whose id is the date field to the period of its `to` bound, or
     *   without a bound, of the first or the last item: at most `MAX_POINTS`, the latest. Its
     *   `total` is the sum of the points.
     * - A breakdown has a part per value, the largest first (equal parts in the order of the
     *   options, then of their labels), at most `MAX_PARTS`, the rest in `other`. Each part has
     *   its `key`, and its label: that of its option for a `select`, with its tone, that of the
     *   related item for a `relation`.
     * - An aggregate that the repository would compute in memory over too many items
     *   (`TooManyItemsToAggregateException`) is a `ValidationException`, keyed by `measure` or
     *   `group`.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function aggregate(Resource $resource, Request $request, ResourceQuery $query, Measure $measure, ?Group $group): array
    {
        $repository = $this->resources->repository($resource);
        if (! $repository instanceof Aggregates) {
            throw new LogicException(sprintf('The repository of "%s" doesn\'t implement %s.', $resource->id(), Aggregates::class));
        }
        try {
            $result = $repository->aggregate($query, $measure, $group);
        } catch (TooManyItemsToAggregateException $exception) {
            // On the field that is aggregated in memory: the measure, or else the group
            $key = $group === null || ($repository instanceof ReportsInMemoryAggregates && $repository->aggregatesInMemory($measure)) ? 'measure' : 'group';

            throw ValidationException::withMessages([$key => [__('Too many items to aggregate (:count, at most :max): narrow the filters of the list.', ['count' => $exception->count, 'max' => $exception->max])]]);
        }
        if ($group === null) {
            return ['value' => $result->total];
        }
        if ($group->interval !== null) {
            return $this->series($group->interval, $result, $query->filter($group->field));
        }

        return $this->breakdown($request, $this->field($resource, $group->field), $result);
    }

    /**
     * @return array{period: string, points: list<array{date: string, value: int|float}>, total: int|float}
     */
    private function series(Interval $interval, AggregateResult $result, mixed $range): array
    {
        $values = [];
        foreach ($result->periods as $day => $value) {
            $start = $interval->startOf((string) $day);
            $values[$start] = ($values[$start] ?? 0) + $value;
        }
        ksort($values);
        $days = array_keys($values);
        $bound = fn (string $key): ?string => is_array($range) && is_string($range[$key] ?? null) && $range[$key] !== '' ? substr($range[$key], 0, 10) : null;
        $from = $bound('from') ?? ($days[0] ?? null) ?? $bound('to');
        $to = $bound('to') ?? ($days !== [] ? $days[count($days) - 1] : null) ?? $bound('from');
        $points = [];
        $total = 0;
        if ($from !== null && $to !== null) {
            foreach ($interval->periods($from, $to, self::MAX_POINTS) as $date) {
                $value = $values[$date] ?? 0;
                $points[] = ['date' => $date, 'value' => $value];
                $total += $value;
            }
        }

        return ['period' => $interval->value, 'points' => $points, 'total' => $total];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdown(Request $request, ?Field $field, AggregateResult $result): array
    {
        $definition = $field?->toDefinition() ?? [];
        $options = is_array($definition['options'] ?? null) ? array_values($definition['options']) : [];
        $isRelation = $field instanceof Relation;
        $parts = [];
        foreach ($result->parts as ['key' => $key, 'value' => $value]) {
            if ($key === '') {
                $key = null;
            }
            if ($isRelation && $key !== null) {
                $key = (string) $key;
            }
            $id = json_encode($key);
            $parts[$id] = ['key' => $key, 'value' => ($parts[$id]['value'] ?? 0) + $value];
        }
        $order = function (mixed $key) use ($options): int {
            foreach ($options as $index => $option) {
                if (is_array($option) && array_key_exists('value', $option) && self::same($option['value'], $key)) {
                    return $index;
                }
            }

            return PHP_INT_MAX;
        };
        $labeled = [];
        foreach ($parts as $part) {
            $labeled[] = [...$part, 'label' => $this->label($part['key'], $field, $options), 'order' => $order($part['key'])];
        }
        usort($labeled, fn (array $a, array $b): int => $b['value'] <=> $a['value']
            ?: $a['order'] <=> $b['order']
            ?: strnatcasecmp($a['label'], $b['label']));
        $shown = array_slice($labeled, 0, self::MAX_PARTS);
        $rest = array_slice($labeled, self::MAX_PARTS);
        if ($field instanceof Relation) {
            $this->relationLabels($shown, $field, $request);
        }
        $items = array_map(function (array $part) use ($field, $options): array {
            $item = ['key' => $part['key'], 'label' => $part['label'], 'value' => $part['value']];
            $tone = $field?->type() === 'select' && $part['key'] !== null ? self::option($options, $part['key'])['tone'] ?? null : null;
            if (is_string($tone)) {
                $item['tone'] = $tone;
            }

            return $item;
        }, $shown);
        $answer = ['items' => $items, 'total' => $result->total];
        if ($rest !== []) {
            $answer['other'] = array_sum(array_column($rest, 'value'));
        }

        return $answer;
    }

    /**
     * The label of a value: that of its option for a `select`, yes or no for a `boolean`, "None"
     * without a value, the value itself otherwise. A relation gets the labels of its related
     * items later, for the parts shown only.
     *
     * @param  list<mixed>  $options
     */
    private function label(mixed $key, ?Field $field, array $options): string
    {
        if ($key === null) {
            return __('None');
        }
        if ($field?->type() === 'select') {
            $label = self::option($options, $key)['label'] ?? null;
            if (is_string($label)) {
                return $label;
            }
        }
        if (is_bool($key)) {
            return $key ? __('Yes') : __('No');
        }

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * The labels of the parts of a relation: the titles of their related items, found within the
     * scope of their resource. A related item that isn't found keeps its id.
     *
     * @param  list<array{key: mixed, value: int|float, label: string, order: int}>  $parts
     */
    private function relationLabels(array &$parts, Relation $field, Request $request): void
    {
        $name = $field->relatedResource();
        $related = $name !== null ? $this->resources->resolve($name) : null;
        if ($related === null) {
            return;
        }
        $repository = $this->resources->repository($related);
        $scope = $related->repositoryScope($request);
        foreach ($parts as $index => $part) {
            if (! is_string($part['key'])) {
                continue;
            }
            $item = $repository->find($part['key'], $scope);
            $label = $item !== null ? ($this->serializer->reference($related, $item)['label'] ?? null) : null;
            if (is_array($label)) {
                $label = $label[app()->getLocale()] ?? collect($label)->first(fn (mixed $localized): bool => is_string($localized) && $localized !== '');
            }
            if (is_string($label) && $label !== '') {
                $parts[$index]['label'] = $label;
            }
        }
    }

    /**
     * Why a field can't be summed, or null.
     */
    private function summable(Field $field): ?string
    {
        if (! in_array($field->type(), self::SUMMABLE_TYPES, true)) {
            return sprintf('is a %s: only number, range, duration and money fields can be summed', $field->type());
        }
        $currency = $field->toDefinition()['settings']['currency'] ?? null;

        return $field->type() === 'money' && ! is_string($currency)
            ? 'is a money field of several currencies: amounts add up only in one currency()'
            : null;
    }

    /**
     * A field of the resource, or of one of its types, by name.
     */
    private function field(Resource $resource, string $name): ?Field
    {
        foreach ($resource->allFields() as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }
        foreach ($resource->resourceTypes() as $type) {
            foreach ($resource->fieldsOfType($type) as $field) {
                if ($field->name() === $name) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * The option of a value, among the options of a field.
     *
     * @param  list<mixed>  $options
     * @return array<array-key, mixed>|null
     */
    private static function option(array $options, mixed $key): ?array
    {
        foreach ($options as $option) {
            if (is_array($option) && array_key_exists('value', $option) && self::same($option['value'], $key)) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Whether two values are the same as strings, as the protocol compares the values of options.
     */
    private static function same(mixed $a, mixed $b): bool
    {
        $string = fn (mixed $value): ?string => match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => null,
        };

        return $string($a) !== null && $string($a) === $string($b);
    }
}
