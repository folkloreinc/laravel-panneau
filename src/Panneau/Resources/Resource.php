<?php

namespace Panneau\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Panneau\Actions\Action;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Field;
use Panneau\Fields\Text;
use Panneau\Layout\Node;
use Panneau\Lists\ResourceList;

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
        foreach ($this->fields() as $field) {
            if ($field instanceof Text) {
                return $field->name();
            }
        }

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
     * The list: columns, filters, sorts, search. Only what it declares can be asked for in the
     * query string.
     */
    public function list(ResourceList $list): ResourceList
    {
        return $list;
    }

    /**
     * The actions: `Action` classes, or declarations such as `LinkAction::make()`.
     *
     * @return list<class-string<Action>|ActionDeclaration>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * The scope given to the repository for every operation: list, find, create, update,
     * delete and actions. This is where tenant or ownership isolation goes.
     *
     * @return array<string, mixed>
     */
    public function scope(Request $request): array
    {
        return [];
    }

    /**
     * The values of an item, keyed by field name. Panneau adds `id`, `version` and
     * `abilities`. By default, the declared fields are read from the item, and nothing else.
     *
     * @return array<string, mixed>
     */
    public function toItem(object $item): array
    {
        $values = [];
        foreach ($this->fields() as $field) {
            $values[$field->name()] = $field->resolve($item);
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
        foreach ($this->fields() as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }
}
