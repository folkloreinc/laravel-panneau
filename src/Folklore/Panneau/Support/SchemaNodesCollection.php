<?php

namespace Folklore\Panneau\Support;

use Illuminate\Support\Collection;

class SchemaNodesCollection extends Collection
{
    public function makeFromData($data)
    {
        return $this->reduce(function ($collection, $node) use ($data) {
            $paths = $this->getFieldRealPaths($node->path, $data);
            foreach ($paths as $path) {
                $newNode = new SchemaNode();
                $newNode->path = $path;
                $newNode->type = $node->type;
                $newNode->schema = $node->schema;
                $collection->push($newNode);
            }
            return $collection;
        }, new static());
    }

    protected function getFieldRealPaths($path, $data)
    {
        if (sizeof(explode('*', $path)) <= 1) {
            return [$path];
        }
        $dataArray = json_decode(json_encode($data), true);
        $dotKeys = array_keys(array_dot($dataArray));
        $matchingKeys = [];
        $pattern = !empty($path) && $path !== '*' ?
            '/^('.str_replace('\*', '[^\.]+', preg_quote($path)).')/' : '/^(.*)/';
        foreach ($dotKeys as $dotKey) {
            if (preg_match($pattern, $dotKey, $matches)) {
                if (!in_array($matches[1], $matchingKeys)) {
                    $matchingKeys[] = $matches[1];
                }
            }
        }
        return $matchingKeys;
    }
}
