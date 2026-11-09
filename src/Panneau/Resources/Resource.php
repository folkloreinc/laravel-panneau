<?php

namespace Panneau\Resources;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Panneau\Actions\Action;
use Panneau\Actions\BuiltInLink;
use Panneau\Context\Context;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Field;
use Panneau\Fields\Select;
use Panneau\Fields\Text;
use Panneau\Layout\Field as LayoutField;
use Panneau\Layout\Node;
use Panneau\Lists\Filters\SelectFilter;
use Panneau\Lists\ResourceList;
use Panneau\Roles\RoleManager;

/**
 * A set of items managed in the admin: its fields, form layout, list and actions.
 *
 * A resource is backed by a repository, not by a model: return a repository class or contract
 * from `repository()`, or an Eloquent model from `model()`. Resources are resolved from the
 * container, and configured by overriding these methods.
 */
abstract class Resource
{
    /**
     * The built-in item actions, in the order the app shows them by default.
     */
    public const ITEM_ACTIONS = ['show', 'edit', 'duplicate', 'delete'];

    /**
     * What an item's title can open: `open()`.
     */
    public const OPENS = ['panel', 'edit'];

    /**
     * The key of an item, and of the body of a create, that holds its type, in a resource with
     * `types()`.
     */
    public const ITEM_TYPE = 'type';

    /**
     * The fields of an item.
     *
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * The id, derived from the class name: `PagesResource` gives `pages`, `BlogPosts` gives
     * `blog-posts`.
     */
    public function id(): string
    {
        $name = class_basename($this);

        return Str::kebab(Str::endsWith($name, 'Resource') && $name !== 'Resource' ? Str::beforeLast($name, 'Resource') : $name);
    }

    public function singularLabel(): string
    {
        return Str::singular($this->pluralLabel());
    }

    public function pluralLabel(): string
    {
        return str_replace('-', ' ', $this->id());
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * What the resource is, in the definition's `kind`: `media` for a media library
     * (`MediaResource`). Null for an ordinary resource.
     */
    public function kind(): ?string
    {
        return null;
    }

    public function icon(): ?string
    {
        return null;
    }

    /**
     * The field used as the title of an item, in lists and pickers. Defaults to the first
     * text field.
     */
    public function itemTitle(): ?string
    {
        foreach ($this->allFields() as $field) {
            if ($field instanceof Text) {
                return $field->name();
            }
        }

        return null;
    }

    /**
     * The field shown under the title of an item, in search results, pickers and the card of a
     * selected relation (panneau-js decision 0067), such as the URL of a page. Its value is text.
     */
    public function itemSubtitle(): ?string
    {
        return null;
    }

    /**
     * The field shown as the image of an item, in the gallery of its list, search results and
     * pickers: a media or image field, or a URL (panneau-js decision 0044).
     */
    public function itemImage(): ?string
    {
        return null;
    }

    /**
     * The repository: a class or contract resolved from the container. It implements
     * `ResourceRepository`, or a contract with a registered adapter.
     *
     * @return class-string|null
     */
    public function repository(): ?string
    {
        return null;
    }

    /**
     * An Eloquent model, used through `EloquentRepository` when `repository()` returns null.
     *
     * @return class-string|null
     */
    public function model(): ?string
    {
        return null;
    }

    /**
     * The class of the items, for resource-level policy abilities (`viewAny`, `create`).
     * Defaults to the model. For Entities, return the Entity contract.
     *
     * @return class-string|null
     */
    public function itemClass(): ?string
    {
        return $this->model();
    }

    /**
     * The form layout. Fields that no node references are appended at the end.
     *
     * @return list<Node>
     */
    public function layout(): array
    {
        return [];
    }

    /**
     * The layout of the forms: `layout()`, or in a resource with fields for some types only
     * (`onlyForTypes()`, `exceptForTypes()`) and no layout, its fields in the order of
     * `fields()`. Forms then show a field for some types where it is declared, rather than after
     * the resource's other fields; each type's own fields come at the end.
     *
     * @internal Override `layout()`.
     *
     * @return list<Node>
     */
    public function formLayout(): array
    {
        $layout = $this->layout();
        if (count($layout) > 0) {
            return $layout;
        }
        $fields = $this->fields();
        if (count(array_filter($fields, self::isForSomeTypes(...))) === 0) {
            return [];
        }

        return array_map(fn (Field $field): Node => LayoutField::make($field->name()), $fields);
    }

    /**
     * The list: columns, filters, sorts, search. Only what it declares can be asked for in the
     * query string.
     */
    public function list(ResourceList $list): ResourceList
    {
        return $list;
    }

    /**
     * The actions: `Action` classes, or declarations such as `LinkAction::make()`. The built-in
     * item actions (`show`, `edit`, `duplicate`, `delete`) need no declaration: an action with
     * their id replaces them, such as `EditAction::make()->url(...)` or a `DeleteAction` with its
     * own confirmation.
     *
     * @return list<class-string<Action>|class-string<BuiltInLink>|ActionDeclaration>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * The built-in item actions the app shows, in their order: `show`, `edit`, `duplicate` and
     * `delete`, each on the items whose ability allows it (`view`, `update`, `duplicate`,
     * `delete`). Null, the default, shows all four; `[]` none.
     *
     * It only hides buttons: the API still answers what the policy allows. To forbid deleting,
     * return false from the policy's `delete()`, which also hides the button.
     *
     * @return list<'show'|'edit'|'duplicate'|'delete'>|null
     */
    public function itemActions(): ?array
    {
        return null;
    }

    /**
     * What an item's title opens, in its list and wherever the app links to the item: `panel`,
     * its details in a panel next to the list, or `edit`, its form. With `edit`, the item's show
     * route redirects to its form, and the app leaves out `show`. Use it when the panel
     * adds nothing to the form.
     *
     * @return 'panel'|'edit'
     */
    public function open(): string
    {
        return 'panel';
    }

    /**
     * The scope given to the repository for every operation: list, find, create, update,
     * delete and actions. This is where tenant or ownership isolation goes, and the current
     * context: `$this->context()->get('contest')`.
     *
     * @return array<string, mixed>
     */
    public function scope(Request $request): array
    {
        return [];
    }

    /**
     * The current context of the request: the values of the params of `Panneau::context()`,
     * checked for the user. Empty without a context.
     */
    protected function context(): Context
    {
        return app(Context::class);
    }

    /**
     * Every field of an item: those of `fields()`, and those the kind of the resource adds,
     * such as the `file` of a media library. Panneau reads the fields of a resource through it.
     *
     * @internal Declare fields in `fields()`.
     *
     * @return list<Field>
     */
    public function allFields(): array
    {
        $fields = $this->fields();
        // The fields for some types only go to those types (onlyForTypes(), exceptForTypes())
        $shared = array_values(array_filter($fields, fn (Field $field): bool => ! self::isForSomeTypes($field)));
        if (count($shared) !== count($fields) && ! $this->hasTypes()) {
            throw new LogicException(sprintf('The resource "%s" has fields for some types only, and no types().', $this->id()));
        }

        return $this->withTypeField($shared);
    }

    /**
     * The types of the items (panneau-js decision 0077): `ResourceType` classes, resolved from the
     * container, or instances. Every item has one, among their ids, set when it is created and
     * stored in its `type` (see `typeOf()`); its fields are those of the resource, then those of
     * its type. The definition then has a `select` field `type`, left out of forms, and the list
     * a `select` filter `type`, unless the resource declares them.
     *
     * @return list<class-string<ResourceType>|ResourceType>
     */
    public function types(): array
    {
        return [];
    }

    /**
     * The type of an item, in a resource with `types()`: its `type` attribute. Override it, with
     * `creating()`, to store the type elsewhere: a new item's values have its type in `type`.
     */
    public function typeOf(object $item): ?string
    {
        $type = data_get($item, self::ITEM_TYPE);

        return is_scalar($type) && (string) $type !== '' ? (string) $type : null;
    }

    /**
     * Whether the resource has types.
     *
     * @internal Declare them in `types()`.
     */
    public function hasTypes(): bool
    {
        return count($this->types()) > 0;
    }

    /**
     * The types of `types()`, resolved and checked: their ids are unique, and their fields are
     * named apart from those of the resource, unique, and never `type`.
     *
     * @internal Declare them in `types()`.
     *
     * @return list<ResourceType>
     */
    public function resourceTypes(): array
    {
        $types = $this->types();
        if (count($types) === 0) {
            return [];
        }
        $names = array_map(fn (Field $field): string => $field->name(), $this->fields());
        $resolved = [];
        foreach ($types as $type) {
            $instance = is_string($type) ? Container::getInstance()->make($type) : $type;
            if (! $instance instanceof ResourceType) {
                throw new InvalidArgumentException(sprintf('%s is not a resource type.', get_debug_type($instance)));
            }
            $id = $instance->id();
            if ($id === '' || isset($resolved[$id])) {
                throw new LogicException(sprintf('The resource "%s" has %s type id "%s".', $this->id(), $id === '' ? 'an empty' : 'a duplicate', $id));
            }
            $own = [];
            foreach ($instance->fields() as $field) {
                $name = $field->name();
                $problem = match (true) {
                    $name === self::ITEM_TYPE => 'it holds the type of the item',
                    in_array($name, $names, true) => 'the resource has a field of that name',
                    isset($own[$name]) => 'the type has two fields of that name',
                    default => null,
                };
                if ($problem !== null) {
                    throw new LogicException(sprintf('The type "%s" of the resource "%s" has a field "%s": %s.', $id, $this->id(), $name, $problem));
                }
                $own[$name] = true;
            }
            $resolved[$id] = $instance;
        }
        $this->checkFieldsForSomeTypes(array_values($resolved));

        return array_values($resolved);
    }

    /**
     * Checks that the types that fields name in `onlyForTypes()` and `exceptForTypes()` are
     * types of the resource: a typo would leave a field out of every type.
     *
     * @param  list<ResourceType>  $types
     */
    private function checkFieldsForSomeTypes(array $types): void
    {
        foreach ($this->fields() as $field) {
            if (! self::isForSomeTypes($field)) {
                continue;
            }
            if ($field->name() === self::ITEM_TYPE) {
                throw new LogicException(sprintf('The field "type" of the resource "%s" holds the type of the item: it is for every type.', $this->id()));
            }
            foreach ([...$field->getOnlyForTypes() ?? [], ...$field->getExceptForTypes() ?? []] as $name) {
                if (count(array_filter($types, fn (ResourceType $type): bool => self::isNamed($type, $name))) === 0) {
                    throw new LogicException(sprintf('The field "%s" of the resource "%s" names the type "%s", which the resource doesn\'t have.', $field->name(), $this->id(), $name));
                }
            }
        }
    }

    /**
     * The fields of the resource's items of a type, after those of `allFields()`: the fields of
     * `fields()` for that type (`onlyForTypes()`, `exceptForTypes()`), then the type's own.
     *
     * @internal Declare fields in `fields()` and in the `fields()` of the types.
     *
     * @return list<Field>
     */
    public function fieldsOfType(ResourceType $type): array
    {
        $forType = array_filter($this->fields(), fn (Field $field): bool => self::isForSomeTypes($field) && self::appliesTo($field, $type));

        return [...array_values($forType), ...$type->fields()];
    }

    private static function isForSomeTypes(Field $field): bool
    {
        return $field->getOnlyForTypes() !== null || $field->getExceptForTypes() !== null;
    }

    /**
     * Whether a field is for the items of a type, by its `onlyForTypes()` and `exceptForTypes()`.
     */
    private static function appliesTo(Field $field, ResourceType $type): bool
    {
        $only = $field->getOnlyForTypes();
        $except = $field->getExceptForTypes() ?? [];
        $named = fn (string $name): bool => self::isNamed($type, $name);

        return ($only === null || count(array_filter($only, $named)) > 0) && count(array_filter($except, $named)) === 0;
    }

    /**
     * Whether a type is named by its id, or by its class or a class it extends.
     */
    private static function isNamed(ResourceType $type, string $name): bool
    {
        return $type->id() === $name || is_a($type, $name);
    }

    /**
     * A type of the resource by id, or null for one it doesn't have.
     *
     * @internal
     */
    public function resourceType(?string $id): ?ResourceType
    {
        if ($id === null) {
            return null;
        }
        foreach ($this->resourceTypes() as $type) {
            if ($type->id() === $id) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The fields of the items of a type: those of `allFields()`, then those of the type
     * (`fieldsOfType()`). Only those of `allFields()` without a type, or for one the resource
     * doesn't have.
     *
     * @internal Declare fields in `fields()` and in the `fields()` of the types.
     *
     * @return list<Field>
     */
    public function typeFields(?string $type): array
    {
        $fields = $this->allFields();
        $found = $this->resourceType($type);

        return $found !== null ? [...$fields, ...$this->fieldsOfType($found)] : $fields;
    }

    /**
     * The fields of an item: those of its type, in a resource with types.
     *
     * @internal
     *
     * @return list<Field>
     */
    public function itemFields(object $item): array
    {
        return $this->hasTypes() ? $this->typeFields($this->typeOf($item)) : $this->allFields();
    }

    /**
     * The fields, with the `select` field `type` of a resource with types, unless they have it.
     * A field `type` they declare is left out of forms (`notInForm()`): the type of an item is
     * set when it is created.
     *
     * @internal
     *
     * @param  list<Field>  $fields
     * @return list<Field>
     */
    protected function withTypeField(array $fields): array
    {
        if (! $this->hasTypes()) {
            return $fields;
        }
        foreach ($fields as $field) {
            if ($field->name() === self::ITEM_TYPE) {
                if (! $field->isNotInForm()) {
                    throw new LogicException(sprintf('The field "type" of the resource "%s" holds the type of the item, set when it is created: make it notInForm().', $this->id()));
                }

                return $fields;
            }
        }

        return [...$fields, Select::make(self::ITEM_TYPE)->label(__('Type'))->options($this->typeOptions())->notInForm()];
    }

    /**
     * The types as the options of a `select`.
     *
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (ResourceType $type): array => ['value' => $type->id(), 'label' => $type->label()],
            $this->resourceTypes(),
        );
    }

    /**
     * The scope the repository receives: `scope()`, the constraints of the kind of the
     * resource, such as the media types of a library, and with the roles module, the items the
     * user owns (`OwnedResource`, decision 0014).
     *
     * @internal Override `scope()`.
     *
     * @return array<string, mixed>
     */
    public function repositoryScope(Request $request): array
    {
        return $this->ownedScope($request, $this->scope($request));
    }

    /**
     * A scope with the owner scope of the roles module, when it is on.
     *
     * @internal
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    protected function ownedScope(Request $request, array $scope): array
    {
        $container = Container::getInstance();
        if (! $container->bound('config') || $container->make('config')->get('panneau.roles.enabled') !== true) {
            return $scope;
        }
        $user = $request->user();

        return $container->make(RoleManager::class)->scope($this, $user instanceof Authenticatable ? $user : null, $scope);
    }

    /**
     * The list, as `list()` configures it, with what the kind of the resource adds, such as the
     * `type` filter of a library.
     *
     * @internal Override `list()`.
     */
    public function makeList(): ResourceList
    {
        $list = $this->list(new ResourceList);
        if (! $this->hasTypes()) {
            return $list;
        }
        foreach ($list->getFilters() as $filter) {
            if ($filter->id() === self::ITEM_TYPE) {
                return $list;
            }
        }

        // A resource with types: a `type` filter first, unless the list declares one
        return $list->filters([
            SelectFilter::make(self::ITEM_TYPE)->label(__('Type'))->options($this->typeOptions())->multiple(),
            ...$list->getFilters(),
        ]);
    }

    /**
     * The values of an item, keyed by field name. Panneau adds `id`, `version` and
     * `abilities`, and `type` in a resource with types. By default, the declared fields are read
     * from the item, with those of its type, and nothing else.
     *
     * @return array<string, mixed>
     */
    public function toItem(object $item): array
    {
        $values = [];
        foreach ($this->itemFields($item) as $field) {
            $values[$field->name()] = $field->resolve($item);
        }
        if ($this->hasTypes()) {
            $values[self::ITEM_TYPE] = $this->typeOf($item);
        }

        return $values;
    }

    /**
     * Features of the resource in the definition: `drafts`, `revisions`, `preview`, `search`.
     *
     * @return array<string, mixed>
     */
    public function features(): array
    {
        return [];
    }

    /**
     * Settings passed as is to the frontend.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [];
    }

    /**
     * A field by name.
     */
    public function field(string $name): ?Field
    {
        foreach ($this->allFields() as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Called before an item is created, with the validated values. Returns the values given
     * to the repository.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function creating(array $values, Request $request): array
    {
        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function created(object $item, array $values, Request $request): void {}

    /**
     * Called before an item is updated, with the validated values. Returns the values given
     * to the repository.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updating(object $item, array $values, Request $request): array
    {
        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updated(object $item, array $values, Request $request): void {}

    /**
     * Called after an item is created or updated.
     *
     * @param  array<string, mixed>  $values
     */
    public function saved(object $item, array $values, Request $request): void {}

    public function deleting(object $item): void {}

    public function deleted(object $item): void {}

    /**
     * Whether items can be duplicated, and what a copy includes: `Duplication::make()`, with
     * the relations to copy. Null, the default: the built-in `duplicate` is off, since only the
     * resource knows what a copy needs (its blocks, its media, its children). The repository
     * also has to be `Duplicable`.
     */
    public function duplication(): ?Duplication
    {
        return null;
    }

    /**
     * Called before the copy of an item is saved, with the copy: change its values there (a
     * title, a status, `published` false), and apply the invariants of `creating()`, such as the
     * author being the current user. Unique slugs are already free (`-copy`, `-copy-2`…).
     */
    public function duplicating(object $item, object $copy, Request $request): void {}

    /**
     * Called once the copy of an item is saved, in the same transaction: copy relations there,
     * which the repository doesn't copy.
     */
    public function duplicated(object $item, object $copy, Request $request): void {}
}
