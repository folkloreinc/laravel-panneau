<?php

namespace TestApp\Resources;

use Illuminate\Http\Request;
use Panneau\Contracts\ResourceItem;
use Panneau\Fields\Text;
use Panneau\Support\Resource;
use TestApp\Http\Resources\PageResource;
use TestApp\Repositories\RecordingRepository;

class RestrictedResource extends Resource
{
    public static $repository = RecordingRepository::class;

    public static $jsonResource = PageResource::class;

    public static $deniedAbilities = [];

    public static $checks = [];

    public function fields(): array
    {
        return [Text::make('title')];
    }

    public function authorize(string $ability, Request $request, ?ResourceItem $item = null): bool
    {
        static::$checks[] = [$ability, ! is_null($item) ? $item->id() : null];

        return ! in_array($ability, static::$deniedAbilities);
    }
}
