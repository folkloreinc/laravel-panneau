<?php

namespace Panneau\Actions;

use Illuminate\Support\Collection;
use LogicException;
use Panneau\Contracts\Repositories\Restorable;

/**
 * The built-in `restore` action: puts deleted items back from the trash of a resource whose
 * repository is `Restorable` (a model with `SoftDeletes`). It is the undo of the built-in
 * `delete` there, offered nowhere (`"placement": []`), and finds its items among the deleted
 * ones, by their ids. Authorized by the policy's `restore` method, or `delete` without one.
 */
final class RestoreAction extends Action
{
    public function id(): string
    {
        return 'restore';
    }

    public function label(): string
    {
        return __('Restore');
    }

    public function icon(): string
    {
        return 'undo-2';
    }

    /**
     * Offered nowhere: it runs as the undo of `delete`.
     *
     * @return list<ActionPlacement>
     */
    public function placement(): array
    {
        return [];
    }

    public function findsDeletedItems(): bool
    {
        return true;
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        $repository = $this->context()->repository;
        if (! $repository instanceof Restorable) {
            throw new LogicException(sprintf('The repository %s has no trash.', $repository::class));
        }
        foreach ($items as $item) {
            $repository->restore($item);
        }

        return $result->refresh(ActionRefresh::List);
    }
}
