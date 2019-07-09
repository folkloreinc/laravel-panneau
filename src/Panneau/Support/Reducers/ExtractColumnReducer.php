<?php

namespace Panneau\Support\Reducers;

use Folklore\EloquentJsonSchema\Support\Reducer;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\Support\Utils;

abstract class ExtractColumnReducer extends Reducer
{
    abstract protected function getExtractedColumns();

    public function set(HasJsonSchema $model, Node $node, $value)
    {
        $extractedColumns = $this->getExtractedColumns();
        foreach ($extractedColumns as $column => $path) {
            if ($node->path === $path) {
                $model->{$column} = Utils::getPath($value, $node->path);
            }
        }
        return $value;
    }
}
