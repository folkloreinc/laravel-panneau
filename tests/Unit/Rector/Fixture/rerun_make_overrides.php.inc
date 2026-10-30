<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use App\Http\Resources\PageResource;
use Panneau\Contracts\Repository;
use Panneau\Contracts\ResourceItem;

class Pages extends Resource
{
    public static $repository = \App\Contracts\Repositories\Pages::class;
    // TODO(panneau-5): makeRepository() still uses the 1.x $repository: move it to repository(), then delete the property
    public function repository(): ?string
    {
        return \App\Contracts\Repositories\Pages::class;
    }

    public static $jsonResource = PageResource::class;

    // TODO(panneau-5): makeJsonResource() still uses the 1.x $jsonResource: move it to toItem(), then delete the property
    public function toItem(object $item): array
    {
        return PageResource::make($item)->resolve();
    }

    // TODO(panneau-5): a 1.x method that 5.x doesn't call: move what it does to repository(), then delete it
    public function makeRepository(): Repository
    {
        return app(static::$repository)->setGlobalQuery(['with_drafts' => true]);
    }

    // TODO(panneau-5): a 1.x method that 5.x doesn't call: move what it does to toItem(), then delete it
    public function makeJsonResource(ResourceItem $item): \JsonSerializable
    {
        $resource = static::$jsonResource;

        return (new $resource($item))->isFull();
    }

    public function fields(): array
    {
        return [];
    }
}

?>
