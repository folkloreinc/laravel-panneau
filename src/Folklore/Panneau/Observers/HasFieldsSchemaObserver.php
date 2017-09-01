<?php

namespace Folklore\Panneau\Observers;

use Folklore\Panneau\Support\Interfaces\HasFieldsSchema as HasFieldsSchemaInterface;

class HasFieldsSchemaObserver
{
    public function saving(HasFieldsSchemaInterface $model)
    {
        // @TODO $this->callFieldsReducersSaving();
        $model->validateFieldsAgainstSchema();
        $model->prepareFieldsForSaving();
    }

    public function saved(HasFieldsSchemaInterface $model)
    {
        $model->saveFields();
    }
}
