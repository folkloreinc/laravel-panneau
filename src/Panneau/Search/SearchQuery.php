<?php

namespace Panneau\Search;

use Illuminate\Validation\ValidationException;

/**
 * The query string of the global search, `GET /search?q=&limit=&resources=`, read the way
 * `readSearchQuery()` of `@panneau/protocol` reads it.
 *
 * @internal
 */
final readonly class SearchQuery
{
    /**
     * Shortest search, in characters once trimmed.
     */
    public const MIN_LENGTH = 2;

    /**
     * Longest search, in characters.
     */
    public const MAX_LENGTH = 255;

    /**
     * Items per resource without a `limit`.
     */
    public const DEFAULT_LIMIT = 5;

    /**
     * Highest `limit`, and most items of a response, every resource together.
     */
    public const MAX_LIMIT = 20;

    /**
     * @param  list<string>  $resources  Ids of the resources to search, in the order of the
     *                                   definition.
     */
    public function __construct(
        public string $q,
        public int $limit,
        public array $resources,
    ) {}

    /**
     * Reads the query string. Invalid parameters throw a `ValidationException`, with the
     * messages keyed by parameter: `q`, `limit` and `resources`.
     *
     * @param  array<array-key, mixed>  $parameters  The query string, as parsed by PHP.
     * @param  list<string>  $searchable  Ids of the resources the user can search, in the
     *                                    order of the definition.
     *
     * @throws ValidationException
     */
    public static function fromParameters(array $parameters, array $searchable): self
    {
        $errors = [];

        $q = $parameters['q'] ?? null;
        $q = is_string($q) && mb_check_encoding($q, 'UTF-8') ? trim($q) : '';
        if (mb_strlen($q) < self::MIN_LENGTH) {
            $errors['q'] = [__('The search must have at least :min characters.', ['min' => self::MIN_LENGTH])];
        } elseif (mb_strlen($q) > self::MAX_LENGTH) {
            $errors['q'] = [__('The search must have at most :max characters.', ['max' => self::MAX_LENGTH])];
        }

        $limit = self::DEFAULT_LIMIT;
        if (array_key_exists('limit', $parameters)) {
            $raw = $parameters['limit'];
            $limit = is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1 ? (int) $raw : 0;
            if ($limit < 1 || $limit > self::MAX_LIMIT) {
                $errors['limit'] = [__('The limit must be an integer from 1 to :max.', ['max' => self::MAX_LIMIT])];
            }
        }

        $asked = $searchable;
        if (array_key_exists('resources', $parameters)) {
            $raw = $parameters['resources'];
            $asked = is_string($raw)
                ? array_values(array_filter(array_map(trim(...), explode(',', $raw)), fn (string $id): bool => $id !== ''))
                : [];
            $unknown = array_values(array_unique(array_diff($asked, $searchable)));
            if (count($unknown) > 0) {
                $errors['resources'] = [__('These resources are not searchable: :resources.', ['resources' => implode(', ', $unknown)])];
            } elseif (count($asked) === 0) {
                $errors['resources'] = [__('List at least one resource.')];
            }
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages($errors);
        }

        // The order of the definition, without duplicates
        return new self($q, $limit, array_values(array_intersect($searchable, $asked)));
    }
}
