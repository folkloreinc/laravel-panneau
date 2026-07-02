<?php

namespace Panneau\Fields;

use Closure;
use Panneau\Support\Field;

class Item extends Field
{
    protected static ?Closure $requestUrlResolver = null;

    protected ?string $requestUrl = null;

    protected $canCreate = false;

    protected $canEdit = false;

    public function type(): string
    {
        return 'object';
    }

    public function component(): string
    {
        return 'item';
    }

    public function attributes(): ?array
    {
        return array_merge(parent::attributes(), [
            'requestUrl' => $this->requestUrl ?? (static::$requestUrlResolver ? (static::$requestUrlResolver)($this) : null),
            'canEdit' => $this->canEdit,
            'canCreate' => $this->canCreate,
        ]);
    }

    public function canCreate()
    {
        $this->canCreate = true;
        return $this;
    }

    public function cannotCreate()
    {
        $this->canCreate = false;
        return $this;
    }

    public function canEdit()
    {
        $this->canEdit = true;
        return $this;
    }

    public function cannotEdit()
    {
        $this->canEdit = false;
        return $this;
    }

    public function withRequestUrl(string $requestUrl)
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    public static function setRequestUrlResolver(callable $resolver)
    {
        static::$requestUrlResolver = $resolver(...);
    }
}
