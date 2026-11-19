<?php

namespace TestApp\Conformance;

use Panneau\Fields\BlockType;
use Panneau\Fields\Repeater;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Resources\Resource;
use TestApp\Conformance\Landings\LandingType;
use TestApp\Conformance\Landings\PromoType;
use TestApp\Models\Entry;

/**
 * Landing pages of two types with the same blocks (panneau-js decision 0094): the definition sends
 * them once, in `blockTypeSets`, and the repeater of each type names them with `typeSet`. A block
 * that repeats only inside another stays in its field.
 */
class ConformanceLandingsResource extends Resource
{
    public function id(): string
    {
        return 'landings';
    }

    public function model(): string
    {
        return Entry::class;
    }

    public function fields(): array
    {
        return [Text::make('title')->required()];
    }

    public function types(): array
    {
        return [LandingType::class, PromoType::class];
    }

    public static function blocks(): Repeater
    {
        return Repeater::make('blocks')->types([
            BlockType::make('text', 'Text', [Textarea::make('body')]),
            BlockType::make('stack', 'Stack', [
                Repeater::make('children')->types([BlockType::make('text', 'Text', [Textarea::make('body')])]),
            ]),
        ]);
    }
}
