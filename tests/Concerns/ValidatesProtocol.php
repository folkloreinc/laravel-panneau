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
        }

        return $issues;
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
