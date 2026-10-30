<?php

namespace App\Panneau\Resources;

use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Authors extends Resource
{
    public static $repository = \App\Repositories\Authors::class;

    public function translationsNamespace(): ?string
    {
        return 'panneau.authors';
    }

    public function fields(): array
    {
        return [
            Text::make('name')->withTransLabel($this->translationsNamespace().'.name'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources;

use Panneau\Resources\Resource;
use Panneau\Fields\Text;

class Authors extends Resource
{
    public function repository(): ?string
    {
        return \App\Repositories\Authors::class;
    }

    // TODO(panneau-5): 5.x never calls translationsNamespace(): set the labels with __() on the fields and the resource, then delete it
    public function translationsNamespace(): ?string
    {
        return 'panneau.authors';
    }

    public function fields(): array
    {
        return [
            Text::make('name')->label(__($this->translationsNamespace().'.name')),
        ];
    }
}

?>
