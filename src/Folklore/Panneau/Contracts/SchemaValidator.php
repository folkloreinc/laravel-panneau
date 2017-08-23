<?php

namespace Folklore\Panneau\Contracts;

interface SchemaValidator
{
    public function validate($attribute, $value, $parameters, $validator);

    public function getMessages();
}
