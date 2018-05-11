<?php

namespace Folklore\Panneau\Schemas\Fields;

use Folklore\Panneau\Support\Field;

class Blocks extends Field
{
    protected function type()
    {
        return 'array';
    }

    protected function items()
    {
        return field('block');
    }

    public function getAttributes()
    {
        $attributes = parent::getAttributes();
        $types = array_get($attributes, 'types', null);
        if (is_null($types)) {
            $attributes['types'] = $this->getBlockTypes();
        }
        return $attributes;
    }

    protected function getBlockTypes()
    {
        return array_values(array_map(function ($item) {
            return $item->toFieldsArray();
        }, panneau()->getBlocks()));
    }
}
