<?php

namespace Panneau\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Panneau\Fields\Field;

/**
 * A type of the items of a resource (panneau-js decision 0077): items of one table told apart by
 * their `type`, each type with fields of its own, after those of the resource. A resource lists
 * its types in `types()`.
 *
 * Types are resolved from the container, and configured by overriding these methods.
 */
abstract class ResourceType
{
    /**
     * The fields of the type's items, after those of the resource. Their names differ from
     * those of the resource's fields, and none is named `type`.
     *
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * The id, which items store as their `type`, derived from the class name: `EventType` gives
     * `event`, `HomePageType` gives `home-page`.
     */
    public function id(): string
    {
        $name = class_basename($this);
        foreach (['ResourceType', 'Type'] as $suffix) {
            if (Str::endsWith($name, $suffix) && $name !== $suffix) {
                $name = Str::beforeLast($name, $suffix);

                break;
            }
        }

        return Str::kebab($name);
    }

    /**
     * The name of the type: the title of its create form, and its choice when creating.
     */
    public function label(): string
    {
        return Str::ucfirst(str_replace('-', ' ', $this->id()));
    }

    /**
     * A line under the label, in the choice of a type.
     */
    public function description(): ?string
    {
        return null;
    }

    public function icon(): ?string
    {
        return null;
    }

    /**
     * The values of an item of this type: those of the resource's `toItem()`, which has the
     * type's fields, with what the type adds or changes, such as a value computed from them.
     * Values the user can't see, and those a list didn't ask for, are left out after.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function toItem(object $item, array $values): array
    {
        return $values;
    }

    /**
     * Whether a user can create items of this type, on top of the resource's `create` ability.
     * False for a singleton, such as the home page: the app doesn't offer it, and the API
     * refuses to create one. Its items can still be edited.
     */
    public function canCreate(?Authenticatable $user): bool
    {
        return true;
    }
}
