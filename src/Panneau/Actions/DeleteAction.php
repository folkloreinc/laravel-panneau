<?php

namespace Panneau\Actions;

use Illuminate\Support\Collection;

/**
 * The built-in `delete` action, on items and selections. Authorized by the policy's
 * `delete` method. Runs the resource's `deleting` hook before each deletion. Answers dry runs.
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

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        $context = $this->context();
        foreach ($items as $item) {
            $context->resource->deleting($item);
            $context->repository->delete($item);
            $context->resource->deleted($item);
        }

        return $result->refresh(ActionRefresh::List);
    }
}
