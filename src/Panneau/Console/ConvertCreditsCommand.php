<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Turns the credits that a project kept in JSON into the value of the `Credits` field (decision
 * 0053 of panneau-js): `[{ "person": { "id" } | { "name" }, "roles": [{ "id" } | { "name" }],
 * "featured": true }]`. Its columns are those of `panneau.credits.columns`, or its arguments:
 * `table.column` for a column, `table.column:path.in.json` for a key of a JSON column.
 *
 * - **The person** is the value of the `--person` keys or of `person`, or without `--person`, of
 *   the only key that holds a reference (`people://12`, or an object with an `id`), or else the
 *   `name` (`firstName` and `lastName`) of the credit. A reference, an id or an object with an `id` becomes `{ "id" }`;
 *   an object with a `name` becomes `{ "name" }`.
 * - **The roles** are `roles`, or a single `role`. A reference, an id or an object with an `id`
 *   becomes `{ "id" }`; a localized text `{ "fr", "en" }` becomes `{ "name": { "fr", "en" } }`, and
 *   a plain string (a key of a fixed list) a name in the locale of `--locale`.
 * - **A credit grouped by role**, a role (`title`, `role` or `roles`) with a list of `people`,
 *   becomes a credit per person, in the same order.
 * - `featured` stays when it is true, and the other keys stay, for the fields of the credit.
 *
 * A value with a credit it can't read stays as it is, and is listed with `-v` by its row id and
 * the reason, never its content. Running it again changes nothing. Rows are read and written by
 * their `id`, without their model: dates don't change, and deleted rows convert too.
 */
final class ConvertCreditsCommand extends Command
{
    /**
     * A reference kept as a URI: `people://12`.
     */
    private const REFERENCE = '/^[A-Za-z][\w.+-]*:\/\/([^\/\s]+)$/';

    /**
     * The keys of a credit that are not its person.
     */
    private const NOT_PERSON = ['roles', 'role', 'title', 'featured', 'people'];

    protected $signature = 'panneau:convert-credits
        {columns?* : The columns to convert, instead of those of panneau.credits.columns}
        {--person=* : The key of the person in the old credits (the first one found), instead of the only key that holds a reference}
        {--locale= : The locale of the roles kept as plain strings, instead of the locale of the app}
        {--dry-run : Count and list what would change, without writing}';

    protected $description = 'Turn the credits kept in JSON into the value of the Credits field';

    /**
     * @var list<string>
     */
    private array $personKeys = [];

    private string $locale = 'en';

    public function handle(): int
    {
        /** @var list<string> $columns */
        $columns = $this->argument('columns') ?: (array) config('panneau.credits.columns', []);
        $this->personKeys = array_values(array_filter(
            (array) $this->option('person'),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
        $locale = $this->option('locale');
        $this->locale = is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
        $dryRun = (bool) $this->option('dry-run');

        if ($columns === []) {
            $this->components->warn('No columns to convert: list them in panneau.credits.columns.');

            return self::SUCCESS;
        }

        foreach ($columns as $column) {
            $this->convert($column, $dryRun);
        }

        return self::SUCCESS;
    }

    private function convert(string $target, bool $dryRun): void
    {
        if (preg_match('/^(\w+)\.(\w+)(?::([\w.]+))?$/', $target, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not table.column, nor table.column:path.in.json.', $target));
        }
        [, $table, $column] = $match;
        $path = $match[3] ?? null;

        $counts = ['converted' => 0, 'already' => 0, 'empty' => 0, 'kept' => 0];
        $kept = [];
        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $path, $dryRun, &$counts, &$kept): void {
                foreach ($rows as $row) {
                    $raw = $row->{$column};
                    // Objects, so that the other keys of the column are written back as they were
                    $data = is_string($raw) ? json_decode($raw) : $raw;
                    $credits = self::toArray($path !== null ? data_get($data, $path) : $data);
                    if ($credits === null || $credits === []) {
                        $counts['empty']++;

                        continue;
                    }

                    try {
                        if (! is_array($credits) || ! array_is_list($credits)) {
                            throw new UnexpectedValueException('not a list of credits');
                        }
                        $converted = $this->credits($credits);
                    } catch (UnexpectedValueException $exception) {
                        $counts['kept']++;
                        $kept[] = [$row->id, $exception->getMessage()];

                        continue;
                    }

                    if ($converted === $credits) {
                        $counts['already']++;

                        continue;
                    }
                    $counts['converted']++;
                    if (! $dryRun) {
                        if ($path !== null) {
                            data_set($data, $path, $converted);
                        } else {
                            $data = $converted;
                        }
                        DB::table($table)->where('id', $row->id)->update([
                            $column => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });

        $this->components->info(sprintf(
            '%s: %d %s, %d already converted, %d without credits, %d kept as they were',
            $target,
            $counts['converted'],
            $dryRun ? 'to convert' : 'converted',
            $counts['already'],
            $counts['empty'],
            $counts['kept'],
        ));
        if ($kept !== [] && $this->output->isVerbose()) {
            $this->table(['id', 'reason'], $kept);
        }
    }

    /**
     * @param  list<mixed>  $credits
     * @return list<array<string, mixed>>
     */
    private function credits(array $credits): array
    {
        $converted = [];
        foreach ($credits as $credit) {
            if (! is_array($credit) || ($credit !== [] && array_is_list($credit))) {
                throw new UnexpectedValueException('a credit that is not an object');
            }
            /** @var array<string, mixed> $credit */
            array_push($converted, ...$this->credit($credit));
        }

        return $converted;
    }

    /**
     * One credit, or those of a credit grouped by role.
     *
     * @param  array<string, mixed>  $credit
     * @return list<array<string, mixed>>
     */
    private function credit(array $credit): array
    {
        $featured = in_array($credit['featured'] ?? null, [true, 1, '1'], true);
        $people = $credit['people'] ?? null;
        if (is_array($people) && array_is_list($people)) {
            $roles = $this->roles($credit, grouped: true);
            $rest = array_diff_key($credit, array_flip(self::NOT_PERSON));

            return array_map(fn (mixed $person): array => self::make($this->person($person), $roles, $featured, $rest), $people);
        }

        [$keys, $person] = $this->findPerson($credit);
        $roles = $this->roles($credit, grouped: false);
        $rest = array_diff_key($credit, array_flip([...$keys, 'roles', 'role', 'featured']));

        return [self::make($person, $roles, $featured, $rest)];
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  list<array<string, mixed>>  $roles
     * @param  array<string, mixed>  $rest
     * @return array<string, mixed>
     */
    private static function make(array $person, array $roles, bool $featured, array $rest): array
    {
        return ['person' => $person, 'roles' => $roles, ...($featured ? ['featured' => true] : []), ...$rest];
    }

    /**
     * The person of a credit, and the keys it was read from.
     *
     * @param  array<string, mixed>  $credit
     * @return array{list<string>, array<string, mixed>}
     */
    private function findPerson(array $credit): array
    {
        // `person` last, for the credits already converted
        foreach (array_unique([...$this->personKeys, 'person']) as $key) {
            if (($credit[$key] ?? null) !== null) {
                return [[$key], $this->person($credit[$key])];
            }
        }
        if ($this->personKeys === []) {
            $candidates = array_values(array_filter(
                array_keys($credit),
                fn (string|int $key): bool => ! in_array($key, self::NOT_PERSON, true) && self::isReference($credit[$key]),
            ));
            if (count($candidates) === 1) {
                return [[(string) $candidates[0]], $this->person($credit[$candidates[0]])];
            }
        }
        $name = $this->nameOf($credit);
        if ($name !== null) {
            return [['name', 'firstName', 'lastName', 'first_name', 'last_name'], ['name' => $name]];
        }

        throw new UnexpectedValueException('a credit without a person');
    }

    /**
     * @return array{id: string}|array{name: string}
     */
    private function person(mixed $value): array
    {
        $id = self::idOf($value);
        if ($id !== null) {
            return ['id' => $id];
        }
        $name = is_array($value) ? $this->nameOf($value) : null;
        if ($name !== null) {
            return ['name' => $name];
        }

        throw new UnexpectedValueException('a person that is neither a reference nor a name');
    }

    /**
     * @param  array<string, mixed>  $credit
     * @return list<array<string, mixed>>
     */
    private function roles(array $credit, bool $grouped): array
    {
        $roles = match (true) {
            ($credit['roles'] ?? null) !== null => $credit['roles'],
            ($credit['role'] ?? null) !== null => [$credit['role']],
            $grouped && ($credit['title'] ?? null) !== null => [$credit['title']],
            default => [],
        };
        if (! is_array($roles) || ! array_is_list($roles)) {
            // A single role under roles
            $roles = [$roles];
        }
        $converted = [];
        foreach ($roles as $role) {
            $role = $this->role($role);
            if ($role !== null) {
                $converted[] = $role;
            }
        }

        return $converted;
    }

    /**
     * A role, or null for an empty one.
     *
     * @return array{id: string}|array{name: array<string, string>}|null
     */
    private function role(mixed $value): ?array
    {
        if ($value === null || is_string($value) && trim($value) === '') {
            return null;
        }
        $id = self::idOf($value);
        if ($id !== null) {
            return ['id' => $id];
        }
        if (is_string($value)) {
            return ['name' => [$this->locale => trim($value)]];
        }
        if (is_array($value) && array_key_exists('name', $value)) {
            $value = is_string($value['name']) ? [$this->locale => $value['name']] : $value['name'];
        }
        $name = self::localized($value);
        if ($name !== null) {
            return $name === [] ? null : ['name' => $name];
        }

        throw new UnexpectedValueException('a role that is neither a reference nor a name');
    }

    /**
     * An id: an integer, digits, a reference (`people://12`), or an object with one of them as
     * its `id`.
     */
    private static function idOf(mixed $value): ?string
    {
        if (is_array($value) && ! array_is_list($value)) {
            $value = $value['id'] ?? null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        if (preg_match('/^\d+$/', $value) === 1) {
            return $value;
        }

        return preg_match(self::REFERENCE, $value, $match) === 1 ? $match[1] : null;
    }

    /**
     * Whether a value is a reference to an item: `people://12`, or an object with an `id`.
     */
    private static function isReference(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match(self::REFERENCE, $value) === 1;
        }

        return is_array($value) && ! array_is_list($value) && (is_int($value['id'] ?? null) || is_string($value['id'] ?? null));
    }

    /**
     * The name of a person: its `name`, or its `firstName` and `lastName`. A name kept in each
     * locale gives the one of `--locale`, or the first one.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function nameOf(array $value): ?string
    {
        $name = $value['name'] ?? null;
        if (is_array($name)) {
            $localized = self::localized($name) ?? [];
            $name = $localized[$this->locale] ?? reset($localized);
        }
        if (! is_string($name) || trim($name) === '') {
            $parts = [
                $value['firstName'] ?? $value['first_name'] ?? null,
                $value['lastName'] ?? $value['last_name'] ?? null,
            ];
            $name = implode(' ', array_filter(array_map(
                fn (mixed $part): string => is_string($part) ? trim($part) : '',
                $parts,
            )));
        }
        $name = trim($name);

        return $name !== '' ? $name : null;
    }

    /**
     * A text in each locale, trimmed and without the empty ones, or null when it isn't one.
     *
     * @return array<string, string>|null
     */
    private static function localized(mixed $value): ?array
    {
        if (! is_array($value) || $value === [] || array_is_list($value)) {
            return null;
        }
        $localized = [];
        foreach ($value as $locale => $text) {
            if (! is_string($locale) || ($text !== null && ! is_string($text))) {
                return null;
            }
            $text = trim((string) $text);
            if ($text !== '') {
                $localized[$locale] = $text;
            }
        }

        return $localized;
    }

    /**
     * A decoded JSON value, with arrays instead of objects.
     */
    private static function toArray(mixed $value): mixed
    {
        if (! is_object($value) && ! is_array($value)) {
            return $value;
        }

        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), true);
    }
}
