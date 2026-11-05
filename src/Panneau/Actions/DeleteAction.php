<?php

namespace Panneau\Actions;

use Illuminate\Support\Collection;
use Panneau\Contracts\Repositories\Restorable;
use Panneau\Exceptions\ItemInUseException;

/**
 * The built-in `delete` action, on items and selections, which every resource has without
 * declaring it: declare it, or a subclass, to change its label or confirmation. Authorized by
 * the policy's `delete` method. Runs the resource's `deleting` hook before each deletion. Answers dry runs.
 *
 * On a resource with a trash (a `Restorable` repository: a model with `SoftDeletes`), it is
 * undoable: the definition declares it `undoable`, the app runs it without a confirmation, and its
 * result carries `"undo": { "action": "restore", "items": [...] }` with the ids it deleted
 * (panneau-js decision 0063). Without a trash, it keeps its confirmation.
 *
 * An item that the repository can't delete because something else uses it (it throws
 * `ItemInUseException`) is skipped with the reason `in_use`: the others are deleted, and the undo
 * covers only those. Its `deleted` hook isn't called.
 */
class DeleteAction extends Action
{
    public function id(): string
    {
        return 'delete';
    }

    public function label(): string
    {
        return __('Delete');
    }

    public function icon(): ?string
    {
        return 'trash';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function confirm(): Confirm|bool|null
    {
        return true;
    }

    public function supportsDryRun(): bool
    {
        return true;
    }

    /**
     * `restore` while it runs on a repository with a trash.
     */
    public function inverse(): ?string
    {
        return parent::inverse() ?? (
            $this->running() && $this->context()->repository instanceof Restorable ? 'restore' : null
        );
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        $context = $this->context();
        foreach ($items as $item) {
            $context->resource->deleting($item);
            try {
                $context->repository->delete($item);
            } catch (ItemInUseException) {
                $result->skipped($item, 'in_use');

                continue;
            }
            $context->resource->deleted($item);
        }

        return $result->refresh(ActionRefresh::List);
    }
}
