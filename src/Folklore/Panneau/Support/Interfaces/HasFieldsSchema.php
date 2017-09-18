<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasFieldsSchema
{
    public static function fieldsSchemas();

    public static function fieldsSchema($name);

    public static function addFieldsSchema($name, $schema);

    public static function addFieldsSchemas($schemas);

    public function getFieldsSchemaName();

    public function getFieldsSchema();

    public function validateFieldsAgainstSchema();

    public function saveFields();

    public function getFieldsValue();

    public function fieldsToArray();

    public function attributeIsField($key);
}
