<?php

namespace Panneau\Fields;

use Closure;
use InvalidArgumentException;
use Panneau\Forms\Form;
use Panneau\Forms\RuleSet;
use Panneau\Forms\SaveContext;
use Panneau\Forms\ValidationContext;

/**
 * Who did what: a list of credits, each one a person and their roles (panneau-js decision 0053).
 *
 * ```php
 * Credits::make('credits')
 *     ->resource(PeopleResource::class)   // the people; without, every person is a name
 *     ->roles(RolesResource::class)       // the roles; without, every role is a localized name
 *     ->featured()                        // the star of the credits to put forward
 *     ->fields([Textarea::make('description')->localized()]),
 * ```
 *
 * - **A credit** is `{ "person", "roles", "featured" }`, with the values of the `fields()` of the
 *   credit next to them. `person`, `roles` and `featured` are reserved names.
 * - **A person** is an item of `resource()`, sent as its id or `{ "id" }`, or a name,
 *   `{ "name": "Jane Doe" }`, the same in every locale. Names are allowed next to the items
 *   unless `names(false)`, and are the only kind of person without `resource()`.
 * - **A role** is an item of `roles()`, sent as its id or `{ "id" }`, or without `roles()` a name
 *   in each locale, `{ "name": { "fr": "Musique", "en": "Music" } }`. A field doesn't mix them.
 * - **Validation.** The ids must exist in their resource, within its scope, as for relations.
 *   `min()` and `max()` count the credits, and `required()` needs one.
 * - **The repository receives** the list, or null when it is empty: `{ "person": { "id": "12" } }`
 *   or `{ "person": { "name": "Jane Doe" } }`, `roles` as `{ "id" }` or `{ "name" }`, and
 *   `featured: true` only for the credits put forward. Labels are never kept. With Eloquent, the
 *   attribute needs a JSON column with the `array` cast.
 * - **Items** give the people and the roles of the items as references, `{ "id", "label" }`,
 *   with the `itemTitle` of their resource; an item that is gone keeps its `{ "id" }`.
 */
class Credits extends Field
{
    /**
     * The keys of a credit that its fields can't have.
     */
    public const RESERVED = ['person', 'roles', 'featured'];

    /**
     * The longest name of a person or of a role, in characters.
     */
    public const MAX_NAME_LENGTH = 255;

    protected ?string $resource = null;

    protected ?string $roles = null;

    protected bool $names = true;

    protected bool $featured = false;

    /**
     * @var list<Field>
     */
    protected array $fields = [];

    public function type(): string
    {
        return 'credits';
    }

    /**
     * The resource of the people: its class, or its id. Without it, every person is a name.
     */
    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * The resource of the people, as given to `resource()`.
     */
    public function peopleResource(): ?string
    {
        return $this->resource;
    }

    /**
     * The resource of the roles (`settings.roles`): its class, or its id. Without it, roles
     * are names in each locale.
     */
    public function roles(string $resource): static
    {
        $this->roles = $resource;

        return $this->settings(['roles' => $resource]);
    }

    /**
     * The resource of the roles, as given to `roles()`.
     */
    public function rolesResource(): ?string
    {
        return $this->roles;
    }

    /**
     * Whether a person can be a name next to the items of `resource()` (`settings.names`).
     * Defaults to true. Without `resource()`, every person is a name, whatever it says.
     */
    public function names(bool $names = true): static
    {
        $this->names = $names;
        if ($names) {
            unset($this->settings['names']);
        } else {
            $this->settings['names'] = false;
        }

        return $this;
    }

    /**
     * Whether a person can be a name.
     */
    public function allowsNames(): bool
    {
        return $this->resource === null || $this->names;
    }

    /**
     * Shows the star of `featured` on each credit (`settings.featured`).
     */
    public function featured(bool $featured = true): static
    {
        $this->featured = $featured;
        if ($featured) {
            $this->settings['featured'] = true;
        } else {
            unset($this->settings['featured']);
        }

        return $this;
    }

    /**
     * The other fields of each credit, as the fields of a repeater: a description, a note.
     *
     * @param  list<Field>  $fields
     *
     * @throws InvalidArgumentException When a field has a reserved name.
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $field) {
            if (in_array($field->name(), self::RESERVED, true)) {
                throw new InvalidArgumentException(sprintf(
                    'The fields of a credit can\'t be named "%s": %s are the credit\'s own.',
                    $field->name(),
                    implode(', ', self::RESERVED),
                ));
            }
        }
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    protected function typeDefinition(): array
    {
        $definition = [];
        if ($this->resource !== null) {
            $definition['resource'] = $this->resource;
        }
        if ($this->fields !== []) {
            $definition['fields'] = array_map(fn (Field $field): array => $field->toDefinition(), $this->fields);
        }

        return $definition;
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array', 'list'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        $rules->add($path.'.*', ['array']);
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $index => $credit) {
            if (! is_array($credit)) {
                continue;
            }
            $prefix = $path.'.'.$index.'.';
            $rules->add($prefix.'person', ['required', $this->personRule($context)]);
            $rules->add($prefix.'roles', ['nullable', 'array', 'list']);
            $rules->add($prefix.'roles.*', ['required', $this->roleRule($context)]);
            $rules->add($prefix.'featured', ['nullable', 'boolean']);
            Form::rules($this->fields, $credit, $context->nested(), $prefix, $rules);
        }
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $credits = [];
        foreach ($value as $credit) {
            if (! is_array($credit)) {
                continue;
            }
            $extracted = [
                'person' => $this->extractPerson($credit['person'] ?? null),
                'roles' => $this->extractRoles($credit['roles'] ?? null, $context),
            ];
            if (filter_var($credit['featured'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $extracted['featured'] = true;
            }
            $credits[] = [...$extracted, ...Form::extract($this->fields, $credit, $context->nested())];
        }

        return $credits === [] ? null : $credits;
    }

    /**
     * Prepares the fields of each credit. The credits replace the previous ones, so every field
     * of a credit is also told that its values at `{path}.*` may be gone.
     */
    protected function prepareValue(mixed $value, string $path, SaveContext $context): mixed
    {
        if (is_array($value) && $this->fields !== []) {
            foreach ($value as $index => $credit) {
                if (is_array($credit)) {
                    $value[$index] = Form::prepare($this->fields, $credit, $context, $path.'.'.$index.'.', nested: true);
                }
            }
        }
        $this->prepareAbsent($path, $context);

        return $value;
    }

    public function prepareAbsent(string $path, SaveContext $context): void
    {
        foreach ($this->fields as $field) {
            $field->prepareAbsent($path.'.*.'.$field->name(), $context);
        }
    }

    /**
     * The credits read from an item, in the protocol's shape, without labels: the serializer
     * of items adds those of the people and roles that are items.
     */
    public function serialize(mixed $value): mixed
    {
        if (! is_iterable($value)) {
            return $value;
        }
        $credits = [];
        foreach ($value as $credit) {
            if (! is_array($credit) && ! is_object($credit)) {
                continue;
            }
            $roles = data_get($credit, 'roles');
            $serialized = [
                'person' => self::entryOf(data_get($credit, 'person')),
                'roles' => is_iterable($roles) ? self::entries($roles) : [],
            ];
            if (data_get($credit, 'featured') === true) {
                $serialized['featured'] = true;
            }
            foreach ($this->fields as $field) {
                $serialized[$field->name()] = $field->serialize(data_get($credit, $field->name()));
            }
            $credits[] = $serialized;
        }

        return $credits === [] ? null : $credits;
    }

    /**
     * The ids of the people and of the roles in a serialized value, to find their items at once.
     *
     * @internal Read by `CreditReferences`.
     *
     * @return array{people: list<string>, roles: list<string>}
     */
    public static function referencedIds(mixed $value): array
    {
        $ids = ['people' => [], 'roles' => []];
        if (! is_array($value)) {
            return $ids;
        }
        foreach ($value as $credit) {
            if (! is_array($credit)) {
                continue;
            }
            $person = $credit['person'] ?? null;
            if (is_array($person) && is_string($person['id'] ?? null)) {
                $ids['people'][] = $person['id'];
            }
            foreach (is_array($credit['roles'] ?? null) ? $credit['roles'] : [] as $role) {
                if (is_array($role) && is_string($role['id'] ?? null)) {
                    $ids['roles'][] = $role['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * An id, from an id or an object with an `id`.
     */
    public static function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }
        if (is_int($value) || is_string($value) && $value !== '') {
            return (string) $value;
        }

        return null;
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function personRule(ValidationContext $context): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context): void {
            if ($value === null || $value === '' || $value === []) {
                // The required rule says so
                return;
            }
            if (self::isName($value)) {
                if (! $this->allowsNames()) {
                    $fail(__('The :attribute must be an item id.'));
                } elseif (self::nameOf($value['name']) === null) {
                    $fail(__('The :attribute must have a name of at most :max characters.', ['max' => self::MAX_NAME_LENGTH]));
                }

                return;
            }
            $id = self::idOf($value);
            if ($id === null) {
                $fail(__($this->allowsNames() ? 'The :attribute must be an item id or a name.' : 'The :attribute must be an item id.'));
            } elseif ($this->resource === null) {
                $fail(__('The :attribute must be a name.'));
            } elseif ($context->relationExists !== null && ! ($context->relationExists)($this->resource, $id)) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function roleRule(ValidationContext $context): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context): void {
            if ($value === null || $value === '' || $value === []) {
                return;
            }
            if ($this->roles === null) {
                if (! self::isName($value) || $this->localizedNameOf($value['name'], $context) === null) {
                    $fail(__('The :attribute must have a name in a locale, of at most :max characters.', ['max' => self::MAX_NAME_LENGTH]));
                }

                return;
            }
            $id = self::isName($value) ? null : self::idOf($value);
            if ($id === null) {
                $fail(__('The :attribute must be an item id.'));
            } elseif ($context->relationExists !== null && ! ($context->relationExists)($this->roles, $id)) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    /**
     * @return array{id: string}|array{name: string}|null
     */
    private function extractPerson(mixed $value): ?array
    {
        if (self::isName($value)) {
            $name = self::nameOf($value['name']);

            return $name !== null ? ['name' => $name] : null;
        }
        $id = self::idOf($value);

        return $id !== null ? ['id' => $id] : null;
    }

    /**
     * @return list<array{id: string}|array{name: array<string, string>}>
     */
    private function extractRoles(mixed $value, ValidationContext $context): array
    {
        $roles = [];
        foreach (is_array($value) ? $value : [] as $role) {
            if ($this->roles === null) {
                $name = self::isName($role) ? $this->localizedNameOf($role['name'], $context) : null;
                if ($name !== null) {
                    $roles[] = ['name' => $name];
                }
            } else {
                $id = self::isName($role) ? null : self::idOf($role);
                if ($id !== null) {
                    $roles[] = ['id' => $id];
                }
            }
        }

        return $roles;
    }

    /**
     * Whether a person or a role is a name: an object with a `name` and without an `id`.
     *
     * @phpstan-assert-if-true array{name: mixed} $value
     */
    private static function isName(mixed $value): bool
    {
        return is_array($value) && array_key_exists('name', $value) && ($value['id'] ?? null) === null;
    }

    /**
     * The name of a person, trimmed, or null when it isn't a name.
     */
    private static function nameOf(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }
        $name = trim($name);

        return $name !== '' && mb_strlen($name) <= self::MAX_NAME_LENGTH ? $name : null;
    }

    /**
     * The name of a role in each locale, trimmed and without the empty ones, or null when it
     * isn't one. With the locales of the definition, only those are kept.
     *
     * @return array<string, string>|null
     */
    private function localizedNameOf(mixed $name, ValidationContext $context): ?array
    {
        if (! is_array($name) || $name === [] || array_is_list($name)) {
            return null;
        }
        $localized = [];
        foreach ($name as $locale => $text) {
            if (! is_string($locale) || ($text !== null && ! is_string($text))) {
                return null;
            }
            $text = trim((string) $text);
            if (mb_strlen($text) > self::MAX_NAME_LENGTH) {
                return null;
            }
            if ($text !== '' && ($context->locales === [] || in_array($locale, $context->locales, true))) {
                $localized[$locale] = $text;
            }
        }

        return $localized !== [] ? $localized : null;
    }

    /**
     * A person or a role kept in an item: `{ "id" }` or `{ "name" }`, without anything else.
     *
     * @return array{id: string}|array{name: mixed}|null
     */
    private static function entryOf(mixed $entry): ?array
    {
        $id = self::idOf(is_object($entry) ? data_get($entry, 'id') : $entry);
        if ($id !== null) {
            return ['id' => $id];
        }
        $name = is_array($entry) || is_object($entry) ? data_get($entry, 'name') : null;
        if (is_object($name)) {
            $name = (array) $name;
        }

        return is_string($name) || is_array($name) ? ['name' => $name] : null;
    }

    /**
     * @param  iterable<mixed>  $entries
     * @return list<array{id: string}|array{name: mixed}>
     */
    private static function entries(iterable $entries): array
    {
        $serialized = [];
        foreach ($entries as $entry) {
            $entry = self::entryOf($entry);
            if ($entry !== null) {
                $serialized[] = $entry;
            }
        }

        return $serialized;
    }
}
