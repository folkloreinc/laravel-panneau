<?php

namespace Panneau\Support;

use Illuminate\Contracts\Support\Arrayable;
use Panneau\Contracts\Support\FieldArrayable;
use Panneau\Contracts\Support\FieldsArrayable;

class SchemasCollection extends TypesCollection implements FieldsArrayable
{
    /**
     * Get the collection as fields array
     * @return array
     */
    public function toFieldsArray()
    {
        $array = [];
        foreach ($this->instancesByName() as $name => $schema) {
            if ($schema instanceof FieldArrayable) {
                $array[$name] = $schema->toFieldArray();
            } elseif ($schema instanceof FieldsArrayable) {
                $array[$name] = $schema->toFieldsArray();
            } elseif ($schema instanceof Arrayable) {
                $array[$name] = $schema->toArray();
            } else {
                $array[$name] = $schema;
            }
        }
        return $array;
    }

    public function schema($name)
    {
        return $this->instance($name);
    }

    public function schemas()
    {
        return $this->instances();
    }

    public function schemasByName()
    {
        return $this->instancesByName();
    }
}
