<?php

namespace Folklore\Panneau\Reducers;

use Folklore\EloquentJsonSchema\Support\Reducer;
use Folklore\EloquentJsonSchema\Contracts\HasJsonSchema;
use Folklore\EloquentJsonSchema\Node;
use Folklore\EloquentJsonSchema\Support\Utils;

class SlugReducer extends Reducer
{
    protected function getSlugPaths()
    {
        return [
            'data.slug.fr' => 'data.title.fr',
            'data.slug.en' => 'data.title.en',
        ];
    }

    public function set(HasJsonSchema $model, Node $node, $value)
    {
        $slugs = $this->getSlugPaths();
        $slugsPaths = array_keys($slugs);
        if (!in_array($node->path, $slugsPaths)) {
            return $value;
        }
        $slug = Utils::getPath($value, $node->path);
        $text = Utils::getPath($value, $slugs[$node->path]);
        if ((isset($slug) && !empty($slug)) || is_null($text) || empty($text)) {
            return $value;
        }
        $value = Utils::setPath($value, $node->path, str_slug($text));
        return $value;
    }
}
