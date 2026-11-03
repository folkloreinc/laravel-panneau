<?php

namespace Panneau\Definition;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Panneau\Actions\Action;
use Panneau\Auth\Authorizer;
use Panneau\Context\ContextResolver;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Field;
use Panneau\Fields\Group;
use Panneau\Fields\Relation;
use Panneau\Fields\Repeater;
use Panneau\Layout\Node;
use Panneau\Navigation\Navigation;
use Panneau\Resources\Resource;
use Panneau\Resources\ResourceRegistry;
use ReflectionMethod;

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
    private const DEFAULT_COLUMN_TYPES = ['text', 'email', 'url', 'slug', 'number', 'boolean', 'date', 'datetime', 'time', 'select', 'color', 'tel', 'range', 'duration', 'tags', 'country', 'region'];

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly Authorizer $authorizer,
        private readonly Config $config,
        private readonly Application $app,
        private readonly Navigation $navigation,
        private readonly ContextResolver $context,
    ) {}

    /**
     * The definition for a user, in the current locale, with their abilities, and for the values
     * of the current context.
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
        ];
        // Left out when there are none, so that definitions stay as they were
        $pages = $this->navigation->pages($user);
        if ($pages !== []) {
            $definition['pages'] = $pages;
        }
        $menus = $this->menus($user, $pages);
        if ($menus !== []) {
            $definition['menus'] = $menus;
        }
        $definition['auth'] = $this->auth();
        // Without it, the app doesn't call GET /search, which doesn't exist
        $definition['search'] = ['enabled' => (bool) $this->config->get('panneau.search.enabled', true)];
        if ($this->config->get('panneau.uploads.enabled')) {
            // Direct uploads to the disk, as well as files sent through the API.
            $definition['uploads'] = ['mode' => 'presigned'];
        }
        // Left out without a context, so that definitions stay as they were
        $context = $this->context->definition($user);
        if ($context !== null) {
            $definition['context'] = $context;
        }
        $theme = $this->theme();
        if ($theme !== null) {
            $definition['theme'] = $theme;
        }
        $definition['user'] = $user !== null ? $this->user($user) : null;

        return $definition;
    }

    /**
     * The menus of the app shell for a user, by name: `panneau.menus`, then those of
     * `Panneau::menu()`. Resource classes become ids, and references to resources and pages
     * are checked: a typo would leave the entry out of the menu.
     *
     * @param  array<string, array<string, mixed>>  $pages
     * @return array<string, list<array<string, mixed>>>
     */
    private function menus(?Authenticatable $user, array $pages): array
    {
        $menus = [];
        foreach ($this->navigation->menus($user) as $name => $items) {
            $menus[$name] = $this->menuItems($name, $items, $pages);
        }

        return $menus;
    }

    /**
     * Items of a menu, checked, with resource ids. Items of the types of plugins are left as
     * they are.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $pages
     * @return list<array<string, mixed>>
     */
    private function menuItems(string $menu, array $items, array $pages): array
    {
        return array_map(function (array $item) use ($menu, $pages): array {
            switch ($item['type']) {
                case 'resource':
                    $reference = $item['resource'] ?? null;
                    $resource = is_string($reference) ? $this->resources->resolve($reference) : null;
                    if ($resource === null) {
                        throw new LogicException(sprintf('The menu "%s" links to the resource %s, which is not registered.', $menu, json_encode($reference)));
                    }
                    $item['resource'] = $resource->id();
                    break;
                case 'page':
                    $page = $item['page'] ?? null;
                    if (! is_string($page) || ! isset($pages[$page])) {
                        throw new LogicException(sprintf('The menu "%s" links to the page %s, which is not declared in panneau.pages or with Panneau::pages().', $menu, json_encode($page)));
                    }
                    if (($pages[$page]['params'] ?? []) !== []) {
                        throw new LogicException(sprintf('The menu "%s" links to the page "%s", which has params: a page about one thing can\'t be in a menu.', $menu, $page));
                    }
                    break;
                case 'link':
                    if (! is_string($item['url'] ?? null)) {
                        throw new LogicException(sprintf('The menu "%s" has a link without a URL: %s.', $menu, json_encode($item)));
                    }
                    break;
                case 'group':
                    if (isset($item['children']) && is_array($item['children'])) {
                        $item['children'] = $this->menuItems($menu, $item['children'], $pages);
                    }
                    break;
            }

            return $item;
        }, $items);
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
        $name = $this->userProperty($user, 'name');
        $email = $this->userProperty($user, 'email');

        return array_filter([
            'id' => (string) $user->getAuthIdentifier(),
            'name' => is_string($name) ? $name : (is_string($email) ? $email : ''),
            'email' => is_string($email) ? $email : null,
            'abilities' => ['panneau.view' => true],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A property of the user: its attribute (an Eloquent model, `GenericUser`), or
     * its public method without parameters, for users that are entities with
     * methods (`name()`, `email()`), as the users of laravel-folklore.
     */
    private function userProperty(Authenticatable $user, string $property): ?string
    {
        $value = data_get($user, $property);
        if (is_string($value)) {
            return $value;
        }

        if (method_exists($user, $property)) {
            $method = new ReflectionMethod($user, $property);
            if ($method->isPublic() && ! $method->isStatic() && $method->getNumberOfRequiredParameters() === 0) {
                $value = $method->invoke($user);
            }
        }

        return is_string($value) ? $value : null;
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
        foreach (['description' => $resource->description(), 'icon' => $resource->icon(), 'itemTitle' => $resource->itemTitle(), 'itemImage' => $resource->itemImage()] as $key => $value) {
            if ($value !== null) {
                $definition[$key] = $value;
            }
        }
        // Left out when they are the defaults, so that definitions stay as they were
        $open = self::checkedOpen($resource, $resource->open());
        if ($open !== 'panel') {
            $definition['open'] = $open;
        }
        $itemActions = self::checkedItemActions($resource, $resource->itemActions());
        if ($itemActions !== null) {
            $definition['itemActions'] = $itemActions;
        }
        // A duplication that lists a relation the repository can't copy throws here, when the
        // admin loads, rather than on a click
        $this->resources->duplication($resource);
        $definition['fields'] = array_map(fn (Field $field): array => $this->field($field, $user), $fields);

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
        $actions = array_map(
            fn (ActionDeclaration $action): array => $this->action($action, $user, $abilities['viewAny'], $resource),
            $this->resources->actions($resource),
        );
        // A user who can't delete any item (the policy's `deleteAny()`): the built-in `delete`,
        // declared or not, can't run, so lists have no "Delete" (panneau-js decision 0046).
        if (in_array('delete', $itemActions ?? Resource::ITEM_ACTIONS, true)
            && ! $this->authorizer->allowsDeleteAny($user, $resource)) {
            $declared = array_search('delete', array_column($actions, 'id'), true);
            if ($declared === false) {
                $actions[] = ['id' => 'delete', 'kind' => 'server', 'scope' => ['item', 'selection'], 'abilities' => ['run' => false]];
            } else {
                $actions[$declared]['abilities'] = ['run' => false];
            }
        }
        if (count($actions) > 0) {
            $definition['actions'] = $actions;
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
     * What `open()` returns, checked: a typo would leave the app on its default.
     */
    private static function checkedOpen(Resource $resource, mixed $open): string
    {
        if (! is_string($open) || ! in_array($open, Resource::OPENS, true)) {
            throw new LogicException(sprintf('The resource "%s" opens its items with %s: open() returns "%s".', $resource->id(), json_encode($open), implode('" or "', Resource::OPENS)));
        }

        return $open;
    }

    /**
     * What `itemActions()` returns, checked: the app would ignore an id it doesn't know.
     *
     * @return list<string>|null
     */
    private static function checkedItemActions(Resource $resource, mixed $actions): ?array
    {
        if ($actions === null) {
            return null;
        }
        $unknown = is_array($actions) ? array_diff($actions, Resource::ITEM_ACTIONS) : [$actions];
        if (! is_array($actions) || $unknown !== []) {
            throw new LogicException(sprintf('The resource "%s" lists %s in itemActions(), which takes "%s".', $resource->id(), json_encode(array_values($unknown)), implode('", "', Resource::ITEM_ACTIONS)));
        }

        return array_values(array_map(strval(...), $actions));
    }

    /**
     * @return array<string, mixed>
     */
    public function action(ActionDeclaration $action, ?Authenticatable $user = null, bool $canViewResource = true, ?Resource $resource = null): array
    {
        $definition = $action->toDefinition();
        if ($action instanceof Action) {
            $definition['abilities'] = ['run' => $canViewResource && $this->authorizer->allowsActionOnResource($user, $action, $resource)];
        }
        if (isset($definition['fields']) && is_array($definition['fields'])) {
            $definition['fields'] = array_map(fn (array $field): array => $this->nestedField($field), $definition['fields']);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function field(Field $field, ?Authenticatable $user): array
    {
        $definition = $this->resolveFieldReferences($field->toDefinition());
        // What the user can't do with the field (panneau-js decision 0046)
        $abilities = $this->authorizer->fieldAbilities($user, $field);
        if ($abilities !== []) {
            $definition['abilities'] = $abilities;
        }

        return $definition;
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
