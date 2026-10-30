<?php

namespace App\Panneau\Resources\Pages;

use App\Panneau\Fields\Credits;
use App\Panneau\Fields\Image;
use App\Panneau\Fields\Sites;

class Landing extends Home
{
    public function fields(): array
    {
        return [
            Credits::make('credits')->withTransLabel('fields.credits'),
            Image::make('image')->withButton()->isRequired(),
            Sites::make('sites')->withTransLabel('fields.sites'),
        ];
    }
}

?>
-----
<?php

namespace App\Panneau\Resources\Pages;

use App\Panneau\Fields\Credits;
use App\Panneau\Fields\Image;
use App\Panneau\Fields\Sites;

// TODO(panneau-5): a 1.x resource type: resource types are not built yet in 5.x
class Landing extends Home
{
    public function fields(): array
    {
        return [
            Credits::make('credits')->label(__('fields.credits')),
            Image::make('image')->required(),
            Sites::make('sites')->label(__('fields.sites')),
        ];
    }
}

?>
