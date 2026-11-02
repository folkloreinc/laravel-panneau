<?php

namespace Panneau\Actions;

use Illuminate\Support\Collection;
use LogicException;
use Panneau\Contracts\Repositories\Duplicable;

/**
 * The built-in `duplicate` action, on items. For repositories that are `Duplicable`
 * (`replicate()` with Eloquent), not media libraries. Authorized by the policy's `duplicate`
 * method, or else `create`. Runs the resource's `duplicating` and `duplicated` hooks around
 * each copy.
 */
class DuplicateAction extends Action
{
    public function id(): string
    {
        return 'duplicate';
    }

    public function label(): string
    {
        return __('Duplicate');
    }

    public function icon(): ?string
    {
        return 'copy';
    }

    public function scope(): array
    {
        return [ActionScope::Item];
    }

    public function handle(Collection $items, ActionValues $values, ActionResult $result): ActionResult
    {
        $context = $this->context();
        $repository = $context->repository;
        if (! $repository instanceof Duplicable) {
            throw new LogicException(sprintf('The repository of the resource "%s" can\'t duplicate items.', $context->resource->id()));
        }
        foreach ($items as $item) {
            $context->resource->duplicating($item);
            $copy = $repository->duplicate($item, $context->scope);
            $context->resource->duplicated($item, $copy);
        }

        return $result->refresh(ActionRefresh::List);
    }
}
