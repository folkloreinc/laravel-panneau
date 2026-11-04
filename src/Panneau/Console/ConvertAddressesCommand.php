<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Locale;
use Panneau\Fields\Address;
use Panneau\Fields\Country;
use Panneau\Fields\Regions;
use Panneau\Rules\PostalCode;

/**
 * Turns the parts of addresses that a project kept in separate columns into the value of an
 * `Address` field (decision 0054 of panneau-js), in a JSON column. Its targets are those of
 * `panneau.addresses.columns`: `table.column` and the source of each part, a column, or
 * `column:path.in.json` for a key of a JSON column (`city:fr`).
 *
 * Parts are normalized as `Address` does. Countries are turned into codes from their English or
 * French names (with `ext-intl`), and the regions of Canada and the United States into theirs.
 * Values it can't convert are kept as typed, and listed with `-v` by their shape (`a9a 9a`, an
 * `a` for each letter), never their value. A row whose target already holds an address is left
 * alone, so running it again changes nothing. Rows are read and written by their `id`, without
 * their model: dates don't change, and deleted rows convert too.
 */
final class ConvertAddressesCommand extends Command
{
    protected $signature = 'panneau:convert-addresses
        {targets?* : Only these targets of panneau.addresses.columns (table.column)}
        {--country= : The country of an address without one, instead of panneau.addresses.country}
        {--dry-run : Count and list what would change, without writing}';

    protected $description = 'Turn the parts of addresses kept in separate columns into address values';

    /**
     * Countries named otherwise than by their names in English or French.
     */
    private const COUNTRY_ALIASES = [
        'usa' => 'US',
        'unitedstatesofamerica' => 'US',
        'etatsunisdamerique' => 'US',
        'uk' => 'GB',
        'greatbritain' => 'GB',
        'england' => 'GB',
        'angleterre' => 'GB',
    ];

    /**
     * Country codes by the key of their names, from ext-intl.
     *
     * @var array<string, string>|null
     */
    private ?array $countries = null;

    public function handle(): int
    {
        /** @var array<string, array<string, string>> $columns */
        $columns = (array) config('panneau.addresses.columns', []);
        /** @var list<string> $targets */
        $targets = $this->argument('targets');
        if ($targets !== []) {
            $unknown = array_diff($targets, array_keys($columns));
            if ($unknown !== []) {
                $this->components->error(sprintf('Not in panneau.addresses.columns: %s.', implode(', ', $unknown)));

                return self::FAILURE;
            }
            $columns = array_intersect_key($columns, array_flip($targets));
        }
        $country = $this->option('country') ?? config('panneau.addresses.country');
        $country = is_string($country) ? Country::code($country) : null;

        if ($columns === []) {
            $this->components->warn('No addresses to convert: list them in panneau.addresses.columns.');

            return self::SUCCESS;
        }
        if (! extension_loaded('intl')) {
            $this->components->warn('Without ext-intl, only the codes of countries convert, not their names.');
        }

        foreach ($columns as $target => $parts) {
            $this->convert($target, $parts, $country, (bool) $this->option('dry-run'));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $parts  The source of each part.
     */
    private function convert(string $target, array $parts, ?string $country, bool $dryRun): void
    {
        if (preg_match('/^(\w+)\.(\w+)$/', $target, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not table.column.', $target));
        }
        [, $table, $column] = $match;
        $sources = [];
        foreach ($parts as $part => $source) {
            if (! in_array($part, Address::PARTS, true)) {
                throw new InvalidArgumentException(sprintf('"%s" of %s is not a part of an address: %s.', $part, $target, implode(', ', Address::PARTS)));
            }
            if (preg_match('/^(\w+)(?::([\w.]+))?$/', $source, $sourceMatch) !== 1) {
                throw new InvalidArgumentException(sprintf('"%s" of %s is not a column, nor column:path.in.json.', $source, $target));
            }
            $sources[$part] = [$sourceMatch[1], $sourceMatch[2] ?? null];
        }
        $selected = array_values(array_unique(['id', $column, ...array_column($sources, 0)]));

        $counts = ['converted' => 0, 'already' => 0, 'empty' => 0, 'kept' => 0];
        $kept = [];
        DB::table($table)
            ->select($selected)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $sources, $country, $dryRun, &$counts, &$kept): void {
                foreach ($rows as $row) {
                    if (self::isAddress($row->{$column})) {
                        $counts['already']++;

                        continue;
                    }
                    $value = [];
                    foreach ($sources as $part => [$source, $path]) {
                        $text = $row->{$source};
                        if ($path !== null) {
                            $text = data_get(is_string($text) ? json_decode($text, true) : $text, $path);
                        }
                        if (is_string($text) || is_int($text)) {
                            $value[$part] = (string) $text;
                        }
                    }
                    [$address, $unconverted] = $this->address($value, $country);
                    if ($address === null) {
                        $counts['empty']++;

                        continue;
                    }
                    $counts['converted']++;
                    if ($unconverted !== []) {
                        $counts['kept']++;
                        foreach ($unconverted as $part => $text) {
                            $kept[] = [$row->id, $part, preg_replace(['/\d/', '/\p{L}/u'], ['9', 'a'], $text)];
                        }
                    }
                    if (! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update([$column => json_encode($address)]);
                    }
                }
            });

        $this->components->info(sprintf(
            '%s: %d %s, %d already an address, %d without an address, %d with parts kept as typed',
            $target,
            $counts['converted'],
            $dryRun ? 'to convert' : 'converted',
            $counts['already'],
            $counts['empty'],
            $counts['kept'],
        ));
        if ($kept !== [] && $this->output->isVerbose()) {
            $this->table(['id', 'part', 'shape'], $kept);
        }
    }

    /**
     * The address of the parts of a row, and the parts it couldn't convert: a country that isn't
     * a code, a region of Canada or the United States that isn't one of theirs, or a postal code
     * that isn't one of its country.
     *
     * @param  array<string, string>  $parts
     * @return array{array<string, mixed>|null, array<string, string>}
     */
    private function address(array $parts, ?string $country): array
    {
        $typed = isset($parts['country']) && trim($parts['country']) !== '' ? trim($parts['country']) : null;
        if ($typed !== null) {
            $parts['country'] = $this->countryCode($typed) ?? $typed;
        }
        $address = Address::normalize($parts, $country);
        if ($address === null) {
            return [null, []];
        }
        // A country that isn't converted stays as typed
        if ($typed !== null && ! Country::isCode($address['country'] ?? null)) {
            $address['country'] = $typed;
        }
        $unconverted = [];
        $code = $address['country'] ?? null;
        if (is_string($code) && ! Country::isCode($code)) {
            $unconverted['country'] = $code;
        }
        $region = $address['region'] ?? null;
        if (is_string($region) && in_array($code, Regions::countries(), true) && Regions::code($region, $code) !== $region) {
            $unconverted['region'] = $region;
        }
        $postalCode = $address['postalCode'] ?? null;
        if (is_string($postalCode) && ! PostalCode::isValid($postalCode, is_string($code) ? $code : null)) {
            $unconverted['postalCode'] = $postalCode;
        }

        return [$address, $unconverted];
    }

    /**
     * The code of a country, from its code or its name in English or French.
     */
    private function countryCode(string $country): ?string
    {
        $country = trim($country);
        if (strlen($country) === 2 && Country::isCode(strtoupper($country))) {
            return strtoupper($country);
        }
        $key = self::key($country);
        if (isset(self::COUNTRY_ALIASES[$key])) {
            return self::COUNTRY_ALIASES[$key];
        }
        if ($this->countries === null) {
            $this->countries = [];
            if (class_exists(Locale::class)) {
                foreach (Country::CODES as $code) {
                    foreach (['en', 'fr'] as $locale) {
                        $name = Locale::getDisplayRegion('-'.$code, $locale);
                        if (is_string($name) && $name !== '' && $name !== $code) {
                            $this->countries[self::key($name)] = $code;
                        }
                    }
                }
            }
        }

        return $this->countries[$key] ?? null;
    }

    /**
     * Whether a value of the target is already an address: an object with a part of one.
     */
    private static function isAddress(mixed $value): bool
    {
        $value = is_string($value) && str_starts_with(ltrim($value), '{') ? json_decode($value, true) : $value;

        return is_array($value) && array_intersect(array_keys($value), [...Address::PARTS, 'point']) !== [];
    }

    /**
     * A name in lower case letters only, without accents: `États-Unis` is `etatsunis`.
     */
    private static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z]/', '', strtolower(Str::ascii($name)));
    }
}
