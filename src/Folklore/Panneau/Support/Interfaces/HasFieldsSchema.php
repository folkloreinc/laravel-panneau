<?php

namespace Folklore\Panneau\Support\Interfaces;

interface HasFieldsSchema
{
    public static function schemas();

    public static function schema($name);

    public static function addSchema($name, $schema);

    public static function addSchemas($schemas);

    public function getSchemaName();

    public function getSchema();

    public function validateFieldsAgainstSchema();

    public function prepareFieldsForSaving();

    public function saveFields();

    public function prepareFieldsInAttributes(array $attributes);

    public function attributeHasSchema($key);
}
