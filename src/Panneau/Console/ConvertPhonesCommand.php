<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Panneau\Fields\Country;
use Panneau\Fields\PhoneNumber;

/**
 * Turns the phone numbers that a project kept as typed into E.164, the values of `Tel` fields
 * (decision 0047 of panneau-js). Its columns are those of `panneau.phones.columns`, or its
 * arguments: `table.column` for a column, `table.column:path.in.json` for a key of a JSON column.
 *
 * A column of `panneau.phones.columns` can keep extensions, for `Tel` fields with `extension()`
 * (decision 0058 of panneau-js): `'contacts.phone' => ['extension' => true]` reads an extension
 * typed after the number (`ext. 123`, `x123`, `poste 123`), and `['extension' => 'phone_ext']`
 * also merges the extension kept in another column of the same table (`column`, or
 * `column:path.in.json`). Either way the value becomes `+15145550199;ext=123`. The extension's own
 * column stays as it is.
 *
 * Numbers that don't read as one stay as typed, and are listed with `-v` by their shape
 * (`999-9999`, an `a` for each letter), never their value. Running it again changes nothing. Rows
 * are read and written by their `id`, without their model: dates don't change, and deleted rows
 * convert too.
 */
final class ConvertPhonesCommand extends Command
{
    protected $signature = 'panneau:convert-phones
        {columns?* : The columns to convert, instead of those of panneau.phones.columns}
        {--country= : The country of a number typed without its calling code, instead of panneau.phones.country}
        {--dry-run : Count and list what would change, without writing}';

    protected $description = 'Turn the phone numbers kept as typed into E.164';

    public function handle(): int
    {
        /** @var array<array-key, mixed> $columns */
        $columns = $this->argument('columns') ?: (array) config('panneau.phones.columns', []);
        $country = $this->option('country') ?? config('panneau.phones.country');
        $dryRun = (bool) $this->option('dry-run');
        if ($country === null) {
            $this->components->error('Set panneau.phones.country, or pass --country: the country of a number typed without its calling code.');

            return self::FAILURE;
        }
        $country = Country::code($country);

        if ($columns === []) {
            $this->components->warn('No columns to convert: list them in panneau.phones.columns.');

            return self::SUCCESS;
        }

        foreach ($columns as $key => $options) {
            // `table.column`, or `table.column => [options]`
            [$target, $options] = is_string($key) ? [$key, (array) $options] : [(string) $options, []];
            $this->convert($target, $options['extension'] ?? false, $country, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string|null} The column and the path in it, for a JSON column.
     */
    private static function column(string $column, string $target): array
    {
        if (preg_match('/^(\w+)(?::([\w.]+))?$/', $column, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('The extension of "%s", "%s", is not a column, nor column:path.in.json.', $target, $column));
        }

        return [$match[1], $match[2] ?? null];
    }

    /** The value of a column of a row, or of a key of a JSON column. */
    private static function read(object $row, string $column, ?string $path): mixed
    {
        $value = $row->{$column};

        return $path !== null
            ? data_get(is_string($value) ? json_decode($value, true) : $value, $path)
            : $value;
    }

    private function convert(string $target, mixed $extension, string $country, bool $dryRun): void
    {
        if (preg_match('/^(\w+)\.(\w+)(?::([\w.]+))?$/', $target, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not table.column, nor table.column:path.in.json.', $target));
        }
        [, $table, $column] = $match;
        $path = $match[3] ?? null;
        $update = $path !== null ? $column.'->'.str_replace('.', '->', $path) : $column;
        // `true` reads the extensions typed after the numbers; a column also merges its own
        $extensionColumn = is_string($extension) ? self::column($extension, $target) : null;
        $withExtension = $extension === true || $extensionColumn !== null;
        $select = array_values(array_unique(['id', $column, ...($extensionColumn !== null ? [$extensionColumn[0]] : [])]));

        $counts = ['converted' => 0, 'already' => 0, 'empty' => 0, 'kept' => 0];
        $kept = [];
        DB::table($table)
            ->select($select)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $path, $update, $extensionColumn, $withExtension, $country, $dryRun, &$counts, &$kept): void {
                foreach ($rows as $row) {
                    $phone = self::read($row, $column, $path);
                    $stored = $extensionColumn !== null ? self::read($row, ...$extensionColumn) : null;
                    $storedExtension = is_string($stored) || is_int($stored) ? trim((string) $stored) : '';
                    if (! is_string($phone) || trim($phone) === '') {
                        if ($storedExtension === '') {
                            $counts['empty']++;
                        } else {
                            // An extension without its number: nothing to merge it into
                            $counts['kept']++;
                            $kept[] = [$row->id, '', self::shape($storedExtension)];
                        }

                        continue;
                    }

                    $number = PhoneNumber::toE164($phone, $country, $withExtension);
                    if ($number !== null && $storedExtension !== '') {
                        $number = self::merge($number, $storedExtension);
                    }
                    if ($number === $phone) {
                        $counts['already']++;
                    } elseif ($number === null) {
                        $counts['kept']++;
                        $kept[] = [$row->id, self::shape($phone), self::shape($storedExtension)];
                    } else {
                        $counts['converted']++;
                        if (! $dryRun) {
                            DB::table($table)->where('id', $row->id)->update([$update => $number]);
                        }
                    }
                }
            });

        $this->components->info(sprintf(
            '%s: %d %s, %d already in E.164, %d without a number, %d kept as typed',
            $target,
            $counts['converted'],
            $dryRun ? 'to convert' : 'converted',
            $counts['already'],
            $counts['empty'],
            $counts['kept'],
        ));
        if ($kept !== [] && $this->output->isVerbose()) {
            $this->table(
                $withExtension ? ['id', 'shape', 'extension'] : ['id', 'shape'],
                $withExtension ? $kept : array_map(fn (array $row): array => array_slice($row, 0, 2), $kept),
            );
        }
    }

    /**
     * A number in E.164 with the extension kept in its own column, or `null` when that extension
     * isn't digits, or isn't the one typed after the number.
     */
    private static function merge(string $number, string $stored): ?string
    {
        $extension = PhoneNumber::extension($stored);
        [$e164, $own] = PhoneNumber::splitExtension($number);
        if ($extension === null || ($own !== null && $own !== $extension)) {
            return null;
        }

        return $e164.';ext='.$extension;
    }

    /** The shape of a value, without the value: `999-9999`, an `a` for each letter. */
    private static function shape(string $value): string
    {
        return (string) preg_replace(['/\d/', '/\p{L}/u'], ['9', 'a'], $value);
    }
}
