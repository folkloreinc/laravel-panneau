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
        $locales = panneau()->locales();
        $columns = [];
        foreach ($locales as $locale) {
            $columns['data.slug.'.$locale] = 'data.title.'.$locale;
        }
        return $columns;
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
        $slug = $this->createSlug($text, $model, $node, $value);
        $value = Utils::setPath($value, $node->path, $slug);
        return $value;
    }

    protected function createSlug($text, $model, $node, $value)
    {
        return str_slug($text);
    }
}
