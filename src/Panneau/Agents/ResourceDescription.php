<?php

namespace Panneau\Agents;

use InvalidArgumentException;
use Panneau\Resources\Resource;

/**
 * The compact description of resources that the `describe` tool gives an agent (decision 0025):
 * what an agent needs to read and write items, built from the definition of the user, so that
 * what they can't see or do isn't in it. Nothing of the UI: no layout, columns, icons or
 * placements. Pure: an array in, an array out.
 */
final class ResourceDescription
{
    public const PARTS = ['fields', 'list', 'actions'];

    private const BUILT_IN_LABELS = ['delete' => 'Delete', 'restore' => 'Restore', 'duplicate' => 'Duplicate'];

    /**
     * The resources the user can list, from the `resources` of their definition.
     *
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    public function resources(array $definition): array
    {
        $resources = [];
        foreach (self::listOf($definition['resources'] ?? []) as $resource) {
            if (($resource['abilities']['viewAny'] ?? false) !== true) {
                continue;
            }
            $resources[] = self::withoutEmpty([
                'id' => $resource['id'] ?? null,
                'label' => $resource['label'] ?? null,
                'description' => $resource['description'] ?? null,
                'abilities' => [
                    'viewAny' => true,
                    'create' => ($resource['abilities']['create'] ?? false) === true,
                ],
            ]);
        }

        return $resources;
    }

    /**
     * A resource of the definition of the user, with the parts asked for. The built-in actions
     * depend on the resource, not only on the definition: `$hasTrash` and `$duplicable` are
     * those of `ResourceRegistry`. `$blockTypeSets` are those of the definition: a repeater
     * that names its block types with `typeSet` (panneau-js decision 0094) gets them back.
     *
     * @param  array<string, mixed>  $resource
     * @param  list<string>  $parts
     * @param  array<array-key, mixed>  $blockTypeSets
     * @return array<string, mixed>
     */
    public function resource(array $resource, array $parts = self::PARTS, bool $hasTrash = false, bool $duplicable = false, array $blockTypeSets = []): array
    {
        $unknown = array_diff($parts, self::PARTS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown part "%s" of a resource description: it takes "%s".', implode('", "', $unknown), implode('", "', self::PARTS)));
        }

        $description = [
            'id' => $resource['id'] ?? null,
            'label' => $resource['label'] ?? null,
            'description' => $resource['description'] ?? null,
            'abilities' => $resource['abilities'] ?? null,
        ];
        if (in_array('fields', $parts, true)) {
            $description['fields'] = $this->fields($resource['fields'] ?? [], $blockTypeSets);
            $description['types'] = array_map(fn (array $type): array => self::withoutEmpty([
                'id' => $type['id'] ?? null,
                'label' => $type['label'] ?? null,
                'description' => $type['description'] ?? null,
                'fields' => $this->fields($type['fields'] ?? [], $blockTypeSets),
                'create' => ($type['abilities']['create'] ?? true) === false ? false : null,
            ]), self::listOf($resource['types'] ?? []));
        }
        if (in_array('list', $parts, true)) {
            $description['list'] = $this->list(is_array($resource['list'] ?? null) ? $resource['list'] : []);
        }
        if (in_array('actions', $parts, true)) {
            $description['actions'] = $this->actions($resource, $hasTrash, $duplicable, $blockTypeSets);
        }

        return self::withoutEmpty($description);
    }

    /**
     * The fields the user can see, with what an agent needs to write their values: nested
     * fields of groups and repeaters the same way.
     *
     * @param  array<array-key, mixed>  $blockTypeSets
     * @return list<array<string, mixed>>
     */
    private function fields(mixed $fields, array $blockTypeSets): array
    {
        $described = [];
        foreach (self::listOf($fields) as $field) {
            if (($field['abilities']['view'] ?? true) === false) {
                continue;
            }
            $types = $field['types'] ?? (is_string($field['typeSet'] ?? null) ? ($blockTypeSets[$field['typeSet']] ?? []) : []);
            $described[] = self::withoutEmpty([
                'name' => $field['name'] ?? null,
                'type' => $field['type'] ?? null,
                'label' => $field['label'] ?? null,
                'help' => $field['help'] ?? null,
                'localized' => ($field['localized'] ?? false) === true ?: null,
                'required' => self::required($field['rules'] ?? []),
                // A computed value, a field the user can't change, or one the form doesn't send
                'readOnly' => (($field['readOnly'] ?? false) === true
                    || ($field['notInForm'] ?? false) === true
                    || ($field['abilities']['update'] ?? true) === false) ?: null,
                'createOnly' => ($field['createOnly'] ?? false) === true ?: null,
                'updateOnly' => ($field['updateOnly'] ?? false) === true ?: null,
                'multiple' => ($field['multiple'] ?? false) === true ?: null,
                'options' => self::options($field['options'] ?? []),
                'resource' => $field['resource'] ?? null,
                'fields' => isset($field['fields']) ? $this->fields($field['fields'], $blockTypeSets) : null,
                'types' => array_map(fn (array $type): array => self::withoutEmpty([
                    'id' => $type['id'] ?? null,
                    'label' => $type['label'] ?? null,
                    'fields' => $this->fields($type['fields'] ?? [], $blockTypeSets),
                ]), self::listOf($types)),
            ]);
        }

        return $described;
    }

    /**
     * Filters and sorts of the list.
     *
     * @param  array<array-key, mixed>  $list
     * @return array<string, mixed>
     */
    private function list(array $list): array
    {
        return self::withoutEmpty([
            'filters' => array_map(fn (array $filter): array => self::withoutEmpty([
                'id' => $filter['id'] ?? null,
                'type' => $filter['type'] ?? null,
                'label' => $filter['label'] ?? null,
                'multiple' => ($filter['multiple'] ?? false) === true ?: null,
                'options' => self::options($filter['options'] ?? []),
                'resource' => $filter['resource'] ?? null,
            ]), self::listOf($list['filters'] ?? [])),
            'sorts' => $list['sorts'] ?? null,
            'defaultSort' => $list['defaultSort'] ?? null,
            'search' => ($list['search'] ?? false) === true ?: null,
        ]);
    }

    /**
     * The server actions the user can run, and the built-in ones the definition doesn't
     * declare: link and client actions run in the app, not by an agent.
     *
     * @param  array<array-key, mixed>  $resource
     * @param  array<array-key, mixed>  $blockTypeSets
     * @return list<array<string, mixed>>
     */
    private function actions(array $resource, bool $hasTrash, bool $duplicable, array $blockTypeSets): array
    {
        $declared = self::listOf($resource['actions'] ?? []);
        $ids = array_column($declared, 'id');
        $itemActions = is_array($resource['itemActions'] ?? null) ? $resource['itemActions'] : Resource::ITEM_ACTIONS;
        $builtIn = [];
        if (in_array('delete', $itemActions, true) && ! in_array('delete', $ids, true)) {
            $builtIn[] = ['id' => 'delete', 'kind' => 'server', 'scope' => ['item', 'selection'], 'undoable' => $hasTrash];
        }
        if ($hasTrash && ! in_array('restore', $ids, true)) {
            $builtIn[] = ['id' => 'restore', 'kind' => 'server', 'scope' => ['item', 'selection']];
        }
        if ($duplicable && in_array('duplicate', $itemActions, true) && ! in_array('duplicate', $ids, true)
            && ($resource['abilities']['create'] ?? false) === true) {
            $builtIn[] = ['id' => 'duplicate', 'kind' => 'server', 'scope' => ['item']];
        }

        $actions = [];
        foreach ([...$declared, ...$builtIn] as $action) {
            if (($action['kind'] ?? null) !== 'server' || ($action['abilities']['run'] ?? true) === false) {
                continue;
            }
            // The built-in delete of `DefinitionBuilder` has only its id
            $id = $action['id'] ?? null;
            $destructive = ($action['destructive'] ?? false) === true || $id === 'delete';
            $undoable = ($action['undoable'] ?? false) === true;
            $actions[] = self::withoutEmpty([
                'id' => $id,
                'label' => $action['label'] ?? self::BUILT_IN_LABELS[$id] ?? null,
                'scope' => $action['scope'] ?? null,
                'fields' => $this->fields($action['fields'] ?? [], $blockTypeSets),
                'destructive' => $destructive ?: null,
                'undoable' => $undoable ?: null,
                // The built-in delete always answers a dry run
                'dryRun' => (($action['dryRun'] ?? false) === true || $id === 'delete') ?: null,
                'confirmation' => (isset($action['confirm']) && $action['confirm'] !== false) || ($destructive && ! $undoable) ?: null,
            ]);
        }

        return $actions;
    }

    /**
     * `true` for a `required` rule, or the mode it applies in (`create` or `update`).
     */
    private static function required(mixed $rules): bool|string|null
    {
        foreach (self::listOf($rules) as $rule) {
            if (($rule['rule'] ?? null) === 'required') {
                return is_string($rule['mode'] ?? null) ? $rule['mode'] : true;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function options(mixed $options): array
    {
        return array_map(fn (array $option): array => self::withoutEmpty([
            'value' => $option['value'] ?? null,
            'label' => $option['label'] ?? null,
        ]), self::listOf($options));
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private static function listOf(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_array(...))) : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function withoutEmpty(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
