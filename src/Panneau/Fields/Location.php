<?php

namespace Panneau\Fields;

use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A point and its address, `{ "lat", "lng", "address" }`, placed on a map. The address is
 * optional.
 */
class Location extends Field
{
    public function type(): string
    {
        return 'location';
    }

    /**
     * The zoom of the map, from 0 (the world) to about 19 (a building).
     */
    public function zoom(int $zoom): static
    {
        return $this->settings(['zoom' => $zoom]);
    }

    /**
     * Where the map opens before a point is placed.
     */
    public function center(float $lat, float $lng): static
    {
        return $this->settings(['center' => ['lat' => $lat, 'lng' => $lng]]);
    }

    /**
     * Raster tiles for the map of this field, instead of the app's: a URL template
     * (`https://tiles.example.com/{z}/{x}/{y}.png`) and the attribution its terms require.
     */
    public function tiles(string $url, string $attribution, ?int $maxZoom = null): static
    {
        return $this->settings(['tiles' => array_filter(
            ['url' => $url, 'attribution' => $attribution, 'maxZoom' => $maxZoom],
            fn (mixed $value): bool => $value !== null,
        )]);
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if (! is_array($value)) {
            return;
        }
        $rules->add($path.'.lat', ['required', 'numeric', 'between:-90,90']);
        $rules->add($path.'.lng', ['required', 'numeric', 'between:-180,180']);
        $rules->add($path.'.address', ['nullable', 'string', 'max:1000']);
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $address = is_string($value['address'] ?? null) && trim($value['address']) !== '' ? trim($value['address']) : null;

        return array_filter([
            'lat' => (float) $value['lat'],
            'lng' => (float) $value['lng'],
            'address' => $address,
        ], fn (mixed $part): bool => $part !== null);
    }
}
