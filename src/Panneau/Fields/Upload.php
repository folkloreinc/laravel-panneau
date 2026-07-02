<?php

namespace Panneau\Fields;

use Closure;
use Panneau\Support\Field;
use Panneau\Support\Facade as Panneau;

class Upload extends Field
{
    protected static ?Closure $endpointResolver = null;

    protected ?string $endpoint = null;

    protected bool $withButton = false;

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
            'endpoint' =>
                $this->endpoint ??
                (self::$endpointResolver
                    ? (self::$endpointResolver)($this)
                    : panneau_route('upload')),
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
        self::$endpointResolver = fn() => $endpoint;
    }

    public static function setEndpointResolver(callable $resolver)
    {
        self::$endpointResolver = $resolver(...);
    }
}
