<?php

namespace Panneau\Definition;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Panneau\Actions\Action;
use Panneau\Auth\Authorizer;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Layout\Node;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;

/**
 * Builds the protocol's definition from the registered resources and the config.
 */
final class DefinitionBuilder
{
    /**
     * The version of the protocol this package implements.
     */
    public const PROTOCOL_VERSION = 1;

    /**
     * Field types shown as columns when a list declares none.
     */
    private const DEFAULT_COLUMN_TYPES = ['text', 'email', 'url', 'slug', 'number', 'boolean', 'date', 'datetime', 'time', 'select', 'color', 'tel', 'range', 'duration', 'tags'];

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    /**
     * The definition for a user, in the current locale, with their abilities.
     *
     * @return array<string, mixed>
     */
    public function build(?Authenticatable $user = null): array
    {
        $definition = [
            'protocol' => self::PROTOCOL_VERSION,
            'name' => $this->name(),
            'locale' => $this->app->getLocale(),
            'locales' => $this->locales(),
            'api' => ['baseUrl' => $this->apiBaseUrl()],
            'resources' => array_map(fn (Resource $resource): array => $this->resource($resource, $user), $this->resources->all()),
            'auth' => $this->auth(),
        ];
        // Without it, the app doesn't call GET /search, which doesn't exist
        $definition['search'] = ['enabled' => (bool) $this->config->get('panneau.search.enabled', true)];
        if ($this->config->get('panneau.uploads.enabled')) {
            // Direct uploads to the disk, as well as files sent through the API.
            $definition['uploads'] = ['mode' => 'presigned'];
        }
        $theme = $this->theme();
        if ($theme !== null) {
            $definition['theme'] = $theme;
        }
        $definition['user'] = $user !== null ? $this->user($user) : null;

        return $definition;
    }

    /**
     * The theme of the admin, from `panneau.theme`, in the shape of the protocol's
     * `DefinitionTheme`: null when nothing is set. Null values and empty arrays are left out,
     * so that an empty map isn't sent as a JSON list.
     *
     * @return array<string, mixed>|null
     */
    public function theme(): ?array
    {
        $theme = $this->config->get('panneau.theme');
        if (! is_array($theme)) {
            return null;
        }
        $theme = self::withoutEmptyValues($theme);

        return count($theme) > 0 ? $theme : null;
    }

    /**
     * The `auth` of the definition and of the boot config. The login and logout URLs are those
     * of the site, in `external` mode.
     *
     * @return array{mode: 'external'|'panneau', loginUrl: string|null, logoutUrl: string|null}
     */
    public function auth(): array
    {
        $mode = $this->config->get('panneau.auth.mode') === 'panneau' ? 'panneau' : 'external';
        $loginUrl = $this->config->get('panneau.auth.login_url');
        $logoutUrl = $this->config->get('panneau.auth.logout_url');

        return [
            'mode' => $mode,
            'loginUrl' => $mode === 'external' && is_string($loginUrl) ? $loginUrl : null,
            'logoutUrl' => $mode === 'external' && is_string($logoutUrl) && $logoutUrl !== '' ? $logoutUrl : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function user(Authenticatable $user): array
    {
        $name = data_get($user, 'name');
        $email = data_get($user, 'email');

        return array_filter([
            'id' => (string) $user->getAuthIdentifier(),
            'name' => is_string($name) ? $name : (is_string($email) ? $email : ''),
            'email' => is_string($email) ? $email : null,
            'abilities' => ['panneau.view' => true],
        ], fn (mixed $value): bool => $value !== null);
    }

    public function name(): string
    {
        $name = $this->config->get('panneau.name') ?? $this->config->get('app.name');

        return is_string($name) ? $name : 'Panneau';
    }

    /**
     * The locales of localized fields: `panneau.locales`, or the app's locale.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = $this->config->get('panneau.locales');
        if (is_array($locales) && count($locales) > 0) {
            return array_values(array_map(strval(...), $locales));
        }

        return [$this->app->getLocale()];
    }

    public function apiBaseUrl(): string
    {
        $url = $this->config->get('panneau.api.base_url');

        return is_string($url) ? $url : '/'.trim((string) $this->config->get('panneau.path', 'panneau'), '/').'/api';
    }

    /**
     * A resource in the definition, with the abilities of a user.
     *
     * @return array<string, mixed>
     */
    public function resource(Resource $resource, ?Authenticatable $user = null): array
    {
        $fields = $resource->allFields();
        $definition = ['id' => $resource->id()];
        if ($resource->kind() !== null) {
            $definition['kind'] = $resource->kind();
        }
        $definition['label'] = ['singular' => $resource->singularLabel(), 'plural' => $resource->pluralLabel()];
        foreach (['description' => $resource->description(), 'icon' => $resource->icon(), 'itemTitle' => $resource->itemTitle()] as $key => $value) {
            if ($value !== null) {
                $definition[$key] = $value;
            }
        }
        $definition['fields'] = array_map(fn (Field $field): array => $this->field($field), $fields);

        $layout = $resource->layout();
        if (count($layout) > 0) {
            $definition['form'] = ['layout' => array_map(fn (Node $node): array => $node->toDefinition(), $layout)];
        }

        $list = $this->resources->list($resource)->toDefinition(
            $this->resources->searchable($resource),
            $this->resources->cursorPaginated($resource),
        );
        $list['columns'] ??= $this->defaultColumns($resource);
        if (isset($list['filters']) && is_array($list['filters'])) {
            $list['filters'] = array_map(fn (array $filter): array => $this->resolveResource($filter), $list['filters']);
        }
        $definition['list'] = $list;

        $abilities = $this->authorizer->resourceAbilities($user, $resource);
        $actions = $this->resources->actions($resource);
        if (count($actions) > 0) {
            $definition['actions'] = array_map(
                fn (ActionDeclaration $action): array => $this->action($action, $user, $abilities['viewAny']),
                $actions,
            );
        }
        $definition['abilities'] = $abilities;

        $features = $resource->features();
        $features['search'] = $this->resources->inGlobalSearch($resource);
        $definition['features'] = $features;

        $settings = $resource->settings();
        if (count($settings) > 0) {
            $definition['settings'] = $settings;
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    public function action(ActionDeclaration $action, ?Authenticatable $user = null, bool $canViewResource = true): array
    {
        $definition = $action->toDefinition();
        if ($action instanceof Action) {
            $definition['abilities'] = ['run' => $canViewResource && $this->authorizer->allowsActionOnResource($user, $action)];
        }
        if (isset($definition['fields']) && is_array($definition['fields'])) {
            $definition['fields'] = array_map(fn (array $field): array => $this->nestedField($field), $definition['fields']);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function field(Field $field): array
    {
        return $this->resolveFieldReferences($field->toDefinition());
    }

    /**
     * Turns the resource classes of relation, media and image fields into resource ids (one, or
     * a list for a media field that allows several libraries), in nested fields too.
     *
     * @param  array<array-key, mixed>  $field
     * @return array<array-key, mixed>
     */
    private function resolveFieldReferences(array $field): array
    {
        if (in_array($field['type'] ?? null, ['relation', 'media', 'image'], true)) {
            $field = $this->resolveResource($field);
        }
        if (isset($field['fields']) && is_array($field['fields'])) {
            $field['fields'] = array_map(fn (array $child): array => $this->nestedField($child), $field['fields']);
        }
        if (isset($field['types']) && is_array($field['types'])) {
            $field['types'] = array_map(function (array $type): array {
                $type['fields'] = array_map(fn (array $child): array => $this->nestedField($child), $type['fields'] ?? []);

                return $type;
            }, $field['types']);
        }

        return $field;
    }

    /**
     * A field inside a group, a block or an action: only fields at the top of a resource
     * check their value (`check`), since the check names a field of the resource.
     *
     * @param  array<array-key, mixed>  $field
     * @return array<array-key, mixed>
     */
    private function nestedField(array $field): array
    {
        unset($field['check']);

        return $this->resolveFieldReferences($field);
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return array<array-key, mixed>
     */
    private function resolveResource(array $definition): array
    {
        if (isset($definition['resource']) && is_string($definition['resource'])) {
            $definition['resource'] = $this->resources->idOf($definition['resource']);
        } elseif (isset($definition['resource']) && is_array($definition['resource'])) {
            $definition['resource'] = array_values(array_unique(array_map(
                fn (mixed $resource): mixed => is_string($resource) ? $this->resources->idOf($resource) : $resource,
                $definition['resource'],
            )));
        }

        return $definition;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function withoutEmptyValues(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = self::withoutEmptyValues($value);
            }
            if ($value !== null && $value !== []) {
                $result[$key] = $value;
            }
        }

        return array_is_list($values) ? array_values($result) : $result;
    }

    /**
     * Columns for a list that declares none: the item title, then the first simple fields.
     *
     * @return list<array<string, mixed>>
     */
    private function defaultColumns(Resource $resource): array
    {
        $sorts = $this->resources->list($resource)->getSorts();
        $names = [];
        $title = $resource->itemTitle();
        if ($title !== null) {
            $names[] = $title;
        }
        foreach ($resource->allFields() as $field) {
            if (count($names) >= 4) {
                break;
            }
            if (! $field instanceof Group && ! $field instanceof Repeater && ! $field instanceof Relation
                && in_array($field->type(), self::DEFAULT_COLUMN_TYPES, true) && ! in_array($field->name(), $names, true)) {
                $names[] = $field->name();
            }
        }

        return array_map(
            fn (string $name): array => in_array($name, $sorts, true) ? ['field' => $name, 'sortable' => true] : ['field' => $name],
            $names,
        );
    }
}
