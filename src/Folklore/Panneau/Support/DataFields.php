<?php

namespace Folklore\Panneau\Support;

class DataFields extends Fields
{
    public function getPropertiesAsFields()
    {
        $fields = parent::getPropertiesAsFields();
        return array_map(function ($item) {
            return array_merge($item, [
                'name' => 'data.'.$item['name']
            ]);
        }, $fields);
    }
}
