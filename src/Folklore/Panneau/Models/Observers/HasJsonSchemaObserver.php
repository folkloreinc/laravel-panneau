<?php

namespace Folklore\Panneau\Models\Observers;

use Folklore\Panneau\Support\Interfaces\HasJsonSchema as HasJsonSchemaInterface;

class HasJsonSchemaObserver
{
    public function saving(HasJsonSchemaInterface $model)
    {
        $model->validateAndExtractJsonSchemas();
    }
}
