<?php

namespace Folklore\Panneau\Contracts;

interface Schema
{
    public function setFields($fields);

    public function addField($name, $schema);

    public function getFields();

    public function getFieldsNames();

    public function getSchemaForField($name);

    public function setModel($model);

    public function toArray();
}
