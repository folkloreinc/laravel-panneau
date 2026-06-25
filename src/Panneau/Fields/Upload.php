<?php

namespace Panneau\Fields;

use Panneau\Support\Field;

class Upload extends Field
{
    protected ?string $endpoint = null;

    protected bool $withButton = false;

    protected static ?string $globalEndpoint = null;

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
            'namePath' => 'name',
            'withButton' => $this->withButton,
            'sizePath' => 'metadata.size',
            'endpoint' => $this->endpoint ?? (self::getEndpoint() ?? route('panneau.upload')),
        ]);
    }

    public function withEndpoint(?string $endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function withButton()
    {
        $this->withButton = true;
        return $this;
    }

    public static function setEndpoint(?string $endpoint)
    {
        self::$globalEndpoint = $endpoint;
    }

    public static function getEndpoint()
    {
        return self::$globalEndpoint;
    }
}
