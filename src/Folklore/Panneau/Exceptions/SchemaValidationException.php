<?php

namespace Folklore\Panneau\Exceptions;

use \RuntimeException;

class SchemaValidationException extends RuntimeException
{
    protected $schemaErrors;

    public function __construct($schemaErrors)
    {
        parent::__construct('Error(s) while validating the schema:'.PHP_EOL.$this->getDetailedMessage($schemaErrors));
        $this->schemaErrors = $schemaErrors;
    }

    public function getSchemaErrors()
    {
        return $this->schemaErrors;
    }

    protected function getDetailedMessage($schemaErrors)
    {
        $lines = [];
        foreach ($schemaErrors as $key => $value) {
            $messages = (array)$value;
            foreach ($messages as $message) {
                $lines[] = '['.$key.']: '.$message;
            }
        }
        return implode(PHP_EOL, $lines);
    }
}
