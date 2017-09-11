<?php

namespace Folklore\Panneau\Support;

use Illuminate\Support\Collection;

class SchemaNodesCollection extends Collection
{
    public function prependPath($path)
    {
        return $this->each(function ($model) use ($path) {
            $model->prependPath($path);
        });
    }

    public function fromPath($path)
    {
        return $this->reduce(function ($collection, $node) use ($path) {
            if ($node->isInPath($path)) {
                $node->removePath($path);
                $collection->push($node);
            }
            return $collection;
        }, new self());
    }
}
