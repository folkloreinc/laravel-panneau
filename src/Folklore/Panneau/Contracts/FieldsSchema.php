<?php

namespace Folklore\Panneau\Contracts;

interface FieldsSchema extends Schema
{
    public function setFields($fields);

    public function addField($name, $schema);

    public function hasField($name);

    public function getFields();

    public function getFieldsNames();

    public function getSchemaForField($name);
}
