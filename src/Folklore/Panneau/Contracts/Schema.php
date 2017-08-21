<?php

namespace App\Mediatheque\Contracts;

interface Schema
{
    public function setFields($fields);

    public function addField($name, $schema);

    public function getFields();

    public function getFieldsNames();

    public function getFieldSchema($name);

    public function setModel($model);

    public function toArray();
}
