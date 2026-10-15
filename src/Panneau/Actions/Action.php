<?php

namespace Panneau\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Panneau\Actions\Concerns\SerializesDeclaration;
use Panneau\Contracts\ActionDeclaration;
use Panneau\Fields\Field;
use Panneau\Layout\Node;

/**
 * An action that runs on the server, on one item, a selection, or the whole resource. List
 * its class in the resource's `actions()`.
 *
 * Override the methods to declare it; `handle()` does the work.
 */
abstract class Action implements ActionDeclaration
{
    use SerializesDeclaration;

    /**
     * Runs the action. `$items` holds the items the user may run it on (none for a `resource`
     * action), and `$values` the validated parameters. Report the items that fail or are
     * skipped on `$result`; the others count as succeeded.
     *
     * @param  Collection<int, object>  $items
     */
    abstract public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult;

    /**
     * The id, derived from the class name: `PublishPages` and `PublishPagesAction` give
     * `publish-pages`.
     */
    public function id(): string
    {
        return Str::kebab(Str::beforeLast(class_basename($this), 'Action') ?: class_basename($this));
    }

    public function label(): string
    {
        return Str::ucfirst(str_replace('-', ' ', $this->id()));
    }

    public function icon(): ?string
    {
        return null;
    }

    /**
     * @return list<ActionScope>
     */
    public function scope(): array
    {
        return [ActionScope::Item, ActionScope::Selection];
    }

    /**
     * Where the action appears. Null lets the frontend decide from the scope.
     *
     * @return list<ActionPlacement>|null
     */
    public function placement(): ?array
    {
        return null;
    }

    public function destructive(): bool
    {
        return false;
    }

    /**
     * A confirmation, `true` for the default one, or null for none.
     */
    public function confirm(): Confirm|bool|null
    {
        return null;
    }

    /**
     * Parameters, shown in a modal and validated like resource fields.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * @return list<Node>
     */
    public function layout(): array
    {
        return [];
    }

    /**
     * Whether the user may run the action on an item. Null (the default) asks the item's
     * policy: the method named after the action (`publishPages`), or `update`.
     */
    public function authorize(?Authenticatable $user, object $item): ?bool
    {
        return null;
    }

    /**
     * Whether the action is offered at all to the user.
     */
    public function authorizeResource(?Authenticatable $user): bool
    {
        return true;
    }

    public function toDefinition(): array
    {
        $definition = $this->declaration(
            'server',
            $this->label(),
            $this->icon(),
            $this->scope(),
            $this->placement(),
            $this->destructive(),
            $this->confirm(),
        );
        $fields = $this->fields();
        if (count($fields) > 0) {
            $definition['fields'] = array_map(fn (Field $field): array => $field->toDefinition(), $fields);
        }
        $layout = $this->layout();
        if (count($layout) > 0) {
            $definition['layout'] = array_map(fn (Node $node): array => $node->toDefinition(), $layout);
        }

        return $definition;
    }
}
