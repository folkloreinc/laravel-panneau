<?php

namespace Panneau\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
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
     * A run on more items than this carries no `undo`: the app confirms such runs before they
     * start (panneau-js decision 0063).
     */
    public const UNDO_LIMIT = 100;

    private ?ActionContext $context = null;

    /**
     * The action that undoes this one, set by `undoneBy()` or declared by a subclass.
     */
    protected ?string $undoneBy = null;

    /**
     * The parameters of the action that undoes this one.
     *
     * @var array<string, mixed>
     */
    protected array $undoParams = [];

    /**
     * Whether the action is offered nowhere (`"placement": []`), but can still be run, as the
     * undo of another action.
     */
    protected bool $hidden = false;

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
     * Whether the action answers dry runs: a request with `dryRun` counts the items it would
     * run on and those it would skip, without running it. Lists ask before they confirm an
     * action on every item matching a query.
     *
     * A dry run only checks each item's abilities (`authorize()`), so only opt in when
     * `handle()` doesn't skip or fail items for other reasons.
     */
    public function supportsDryRun(): bool
    {
        return false;
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
     * Declares the action that undoes this one: an action of the same resource, run on the items
     * that succeeded, with these parameters. The action is then `undoable`: the app runs it
     * without a confirmation (unless it sets one), and its result carries the `undo`, up to
     * `UNDO_LIMIT` items. Only for an action whose inverse doesn't depend on each item's previous
     * value: "Archive" and "Unarchive", not "Change the status".
     *
     * @param  array<string, mixed>  $params
     */
    public function undoneBy(string $action, array $params = []): static
    {
        $this->undoneBy = $action;
        $this->undoParams = $params;

        return $this;
    }

    /**
     * The id of the action that undoes this one, or null when it can't be undone.
     */
    public function inverse(): ?string
    {
        return $this->undoneBy;
    }

    /**
     * The parameters of the action that undoes this one.
     *
     * @return array<string, mixed>
     */
    public function inverseParams(): array
    {
        return $this->undoParams;
    }

    /**
     * Offers the action nowhere (`"placement": []`): it can still be run, as the undo of another
     * action, by the same abilities.
     */
    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Whether the action finds its items in the trash of the repository (`restore`).
     */
    public function findsDeletedItems(): bool
    {
        return false;
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

    /**
     * Whether the action runs on items: its scope has `item` or `selection`.
     */
    public function appliesToItems(): bool
    {
        return in_array(ActionScope::Item, $this->scope(), true)
            || in_array(ActionScope::Selection, $this->scope(), true);
    }

    /**
     * Whether the action runs on the whole resource, without items.
     */
    public function appliesToResource(): bool
    {
        return in_array(ActionScope::Resource, $this->scope(), true);
    }

    /**
     * What the action runs in: the resource, its repository and scope, the request and the
     * user. Available in `handle()`.
     */
    public function context(): ActionContext
    {
        if ($this->context === null) {
            throw new LogicException(sprintf('The action %s is not running.', static::class));
        }

        return $this->context;
    }

    /**
     * Whether the action is running: its context is set.
     */
    protected function running(): bool
    {
        return $this->context !== null;
    }

    /**
     * @internal
     */
    public function setContext(ActionContext $context): void
    {
        $this->context = $context;
    }

    public function toDefinition(): array
    {
        $definition = $this->declaration(
            'server',
            $this->label(),
            $this->icon(),
            $this->scope(),
            $this->hidden ? [] : $this->placement(),
            $this->destructive(),
            $this->confirm(),
        );
        if ($this->supportsDryRun()) {
            $definition['dryRun'] = true;
        }
        if ($this->inverse() !== null) {
            $definition['undoable'] = true;
        }
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
