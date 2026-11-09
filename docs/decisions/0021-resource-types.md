# 0021: Resource types are classes the resource lists, and the item's type picks its fields

- Status: proposed
- Date: 2026-11-09
- Issue: [#138](https://github.com/folkloreinc/laravel-panneau/issues/138), [panneau-js decision 0077](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0077-resource-types.md)

## Context

[panneau-js decision 0077](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0077-resource-types.md) gives a resource `types`: items of one table told apart by their `type`, each type with fields of its own, after those of the resource. An item says its type, a create sends it (`422` without one, `403` for a type that can't be created), and updates ignore it. The backend declares a `select` field `type`, left out of forms, and a `select` filter `type`.

1.x sites declare them as subclasses of `Panneau\Support\ResourceType` listed in the resource's `$types`, with their own `fields()`, `name()` and `$settings['canCreate']`. One audited site has 12 page types over three resources, in its `pages` table. Items are any object, behind a repository ([0003](0003-resources.md)).

## Decision

- **A type is a class.** `Panneau\Resources\ResourceType` is configured by overriding methods, like a resource: `fields()`, `id()` (from the class name: `EventType` gives `event`), `label()`, `description()`, `icon()` and `canCreate(?Authenticatable $user)`. `Resource::types()` lists classes, resolved from the container, or instances. A 1.x type keeps its shape, and the Rector rules convert it.
- **Fields for some types**, as `onlyForTypes()` and `exceptForTypes()` did in 1.x, with the same names. A field of the resource names its types by id or by class, and a class includes the types that extend it. The protocol has no such field: the backend gives it to the `fields` of those types in the definition, before their own, and leaves it out of the resource's. The app needs nothing new.
- **Types extend types.** A type that extends another class inherits its fields with `parent::fields()`, and the rest of its methods. An abstract base holds what several types share, and a field of the resource can name it.
- **The item's type picks its fields.** `Resource::typeOf($item)` reads the type of an item, its `type` attribute by default. Every place that reads the fields of an item reads those of its type: `toItem()`, serialization and hidden fields, validation and saving, media and relation references, slug copies, and field checks. The definition, lists and sorts read the resource's fields.
- **A type adds to the values of its items.** `ResourceType::toItem($item, $values)` gets the values of the resource's `toItem()` and returns them, as the `$jsonResource` of a 1.x type did. The serializer calls it, so it runs when the resource has its own `toItem()` too, before hidden fields and the fields a list asked for are applied: a type can't show what the user can't see.
- **The type is saved as a value.** A create gets the type of the body, checks it, and gives it to `creating()` and the repository in the values, as `type`. A resource that stores it elsewhere maps it in `creating()` and overrides `typeOf()`. An update never changes it: the `type` field is `notInForm`, so its value is ignored.
- **The `type` field and filter are automatic.** A resource with types gets a `select` field `type` after its fields, and a `select` filter `type` first in its list, with the types as options, unless it declares them. A field `type` it declares must be `notInForm()`.
- **Checked when the admin loads.** Type ids are unique, type fields are named apart from the resource's and unique, none is named `type`: a mistake throws when the definition is built, not on a save.
- **A type that can't be created can't be duplicated.** A copy is a create: the item's `duplicate` ability is false for it.
- **Media libraries have no types**: the `type` of their items is their media type. `MediaResource::types()` is final.

## Consequences

- A field for some types keeps its place in forms: a resource with such fields and no `layout()` gets a layout of its fields in the order of `fields()`, which the app prunes for each type, since one layout names the fields of every type (panneau-js decision 0077). The type's own fields come at the end. The item panel ignores layouts: it shows such a field after the resource's. A list column can't name it.
- Shared field names across types are allowed, as the protocol allows them: the fields of an item come from its type, never from the union of the types.
- A list column names a field of the resource only, as in the protocol: a type's field has no column.
- An item without a type, or with a type the resource doesn't have, has the fields of the resource alone, and no `type`.
- The `type` of the definition's field and filter is translated with `__('Type')`.
