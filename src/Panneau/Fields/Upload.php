<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Upload extends Field
{
    protected $endpoint = null;

    protected $withButton = false;

    protected $withFind = false;

    protected static $globalEndpoint = null;

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'upload';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'withButton' => $this->withButton,
            'withFind' => $this->withFind,
            'namePath' => 'name',
            'sizePath' => 'metadata.size',
            'endpoint' => $this->endpoint ?? (self::$globalEndpoint ?? route('panneau.upload')),
        ]);
    }

    public function withEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function withButton()
    {
        $this->withButton = true;
        return $this;
    }

    public function withFind()
    {
        $this->withFind = true;
        return $this;
    }

    public static function setEndpoint($endpoint)
    {
        self::$globalEndpoint = $endpoint;
    }
}
