<?php

namespace Folklore\Panneau\Observers;

use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;

class HasFieldsSchemaObserver
{
    public function saving(HasFieldsSchemaInterface $model)
    {
        $model->validateFieldsAgainstSchema();
        $model->prepareFieldsForSaving();
    }

    public function saved(HasFieldsSchemaInterface $model)
    {
        $model->saveFields();
    }
}