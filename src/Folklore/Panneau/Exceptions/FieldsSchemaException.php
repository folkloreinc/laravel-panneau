<?php

namespace Folklore\Panneau\Exceptions;

use \RuntimeException;

class JsonSchemaException extends RuntimeException
{
    protected $schemaErrors;

    public function __construct($schemaErrors) {
        parent::__construct('Error(s) while validating the JSON content: '.$this->getDetailedMessage($schemaErrors));
        $this->schemaErrors = $schemaErrors;
    }

    public function getSchemaErrors()
    {
        return $this->schemaErrors;
    }

    protected function getDetailedMessage($schemaErrors)
    {
        $msg = '';
        foreach ($schemaErrors as $key => $value) {
            if (!empty($msg)) $msg .= ' | ';

            if (is_string($value)) {
                $msg .= '"'.$key.'":'.$value;
            } else {
                $msg .= $this->getDetailedMessage($value);
            }
        }
        return $msg;
    }
}
