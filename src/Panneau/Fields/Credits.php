<?php

namespace Panneau\Fields;

use Panneau\Forms\Condition;

/**
 * Credits (panneau-js decision 0065): a compound field, `credits` with `multiple`, whose items
 * are a person, their roles, and whether the credit is featured. The `credits` plugin of
 * `@panneau/fields` lays them out and summarizes them in lists; without it, clients show a
 * repeater.
 *
 * - `name`: the name of the person, as text. With `people()`, `person` is a relation to the
 *   resource of the people, and `name` is for a person who isn't in it (`names(false)` removes it);
 * - `roles`: text, or with `roles()`, a multiple relation to the resource of the roles;
 * - `featured`: a boolean.
 *
 * ```php
 * Credits::make('credits')->people(PeopleResource::class)->roles(RolesResource::class);
 * ```
 *
 * `append()` adds fields to each credit (a description). Credits are kept as the field's value (a
 * JSON column with the `array` cast), or in rows of their own by the project's repository.
 */
class Credits extends Repeater
{
    private ?string $people = null;

    private bool $names = true;

    private ?string $roles = null;

    /**
     * @var list<Field>
     */
    private array $appended = [];

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->fields = $this->buildFields();
    }

    public function type(): string
    {
        return 'credits';
    }

    /**
     * Credits name people of this resource (its id or class): `person` is a relation to it.
     */
    public function people(string $resource): static
    {
        $this->people = $resource;
        $this->fields = $this->buildFields();

        return $this;
    }

    /**
     * Whether a credit can name a person who isn't in the resource of `people()`. Defaults to
     * `true`.
     */
    public function names(bool $names = true): static
    {
        $this->names = $names;
        $this->fields = $this->buildFields();

        return $this;
    }

    /**
     * The roles are items of this resource (its id or class), instead of text.
     */
    public function roles(string $resource): static
    {
        $this->roles = $resource;
        $this->fields = $this->buildFields();

        return $this;
    }

    /**
     * Adds fields to each credit, after `featured`.
     *
     * @param  list<Field>  $fields
     */
    public function append(array $fields): static
    {
        $this->appended = [...$this->appended, ...$fields];
        $this->fields = $this->buildFields();

        return $this;
    }

    /**
     * @return list<Field>
     */
    private function buildFields(): array
    {
        $fields = [];
        if ($this->people !== null) {
            $fields[] = Relation::make('person')->resource($this->people)->label(__('Person'));
        }
        if ($this->people === null || $this->names) {
            $name = Text::make('name')->label(__('Name'));
            $fields[] = $this->people !== null
                ? $name->visibleWhen(Condition::field('person')->filled(false))
                : $name;
        }
        $fields[] = $this->roles !== null
            ? Relation::make('roles')->resource($this->roles)->multiple()->label(__('Roles'))
            : Text::make('roles')->label(__('Roles'));
        $fields[] = Boolean::make('featured')->label(__('Featured'));

        return [...$fields, ...$this->appended];
    }
}
