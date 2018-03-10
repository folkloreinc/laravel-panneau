<?php

namespace Folklore\Panneau\Support;

use Folklore\EloquentJsonSchema\Support\Reducer;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\Support\Utils;
use App\Schemas\Pages\PageSchema;

abstract class ExtractColumnReducer extends Reducer
{
    abstract public function getExtractedPaths();

    public function set(HasJsonSchema $model, Node $node, $value)
    {
        $extractedPaths = $this->getExtractedPaths();
        if (array_key_exists($node->path, $extractedPaths)) {
            $columnName = $extractedPaths[$node->path];
            $model->{$columnName} = Utils::getPath($value, $node->path);
        }
        return $value;
    }
}
