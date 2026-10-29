<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Items;
use Panneau\Fields\Fields;
use Panneau\Support\Field;
use Panneau\Support\LocalizedField;
use Panneau\Support\ResourceType;
use Panneau\Http\Controllers\ResourceController;

class Credits extends Items
{
    public function field(): ?string
    {
        return Credit::class;
    }
}

class Credit extends Fields
{
    public function fields(): array
    {
        return [];
    }
}

class HtmlLocalized extends LocalizedField
{
    public function field($locale)
    {
        return new Html($locale);
    }
}

class Colors extends Field
{
    public function component(): string
    {
        return 'colors';
    }
}

class TextBlock extends ResourceType
{
}

class Article extends ResourceType
{
}

class PagesController extends ResourceController
{
}

class Helper
{
}

?>
-----
<?php

namespace App\Panneau\Fields;

use Panneau\Fields\Items;
use Panneau\Fields\Fields;
use Panneau\Support\Field;
use Panneau\Support\LocalizedField;
use Panneau\Support\ResourceType;
use Panneau\Http\Controllers\ResourceController;

// TODO(panneau-5): a 1.x field that extends Items: Repeater. 5.x fields are configured with methods: move field(), fields(), attributes() and component() there, or make it a field type with a JS plugin
class Credits extends Items
{
    public function field(): ?string
    {
        return Credit::class;
    }
}

// TODO(panneau-5): a 1.x field that extends Fields: Group. 5.x fields are configured with methods: move field(), fields(), attributes() and component() there, or make it a field type with a JS plugin
class Credit extends Fields
{
    public function fields(): array
    {
        return [];
    }
}

// TODO(panneau-5): a 1.x field that extends LocalizedField: the 5.x field of field(), ->localized(). 5.x fields are configured with methods: move field(), fields(), attributes() and component() there, or make it a field type with a JS plugin
class HtmlLocalized extends LocalizedField
{
    public function field($locale)
    {
        return new Html($locale);
    }
}

// TODO(panneau-5): a 1.x field that extends Field: a built-in type, or a field type with a JS plugin. 5.x fields are configured with methods: move field(), fields(), attributes() and component() there, or make it a field type with a JS plugin
class Colors extends Field
{
    public function component(): string
    {
        return 'colors';
    }
}

// TODO(panneau-5): a 1.x resource type: a BlockType of the Repeater of the blocks (BlockTypes::register() of laravel-folklore 2), then delete it
class TextBlock extends ResourceType
{
}

// TODO(panneau-5): a 1.x resource type: resource types are not built yet in 5.x
class Article extends ResourceType
{
}

// TODO(panneau-5): a 1.x resource controller: move its methods to hooks (creating(), updating(), saved()…) and actions of the resource, then delete it
class PagesController extends ResourceController
{
}

class Helper
{
}

?>
