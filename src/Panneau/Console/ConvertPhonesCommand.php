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
        /** @var list<string> $columns */
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

        foreach ($columns as $column) {
            $this->convert($column, $country, $dryRun);
        }

        return self::SUCCESS;
    }

    private function convert(string $target, string $country, bool $dryRun): void
    {
        if (preg_match('/^(\w+)\.(\w+)(?::([\w.]+))?$/', $target, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not table.column, nor table.column:path.in.json.', $target));
        }
        [, $table, $column] = $match;
        $path = $match[3] ?? null;
        $update = $path !== null ? $column.'->'.str_replace('.', '->', $path) : $column;

        $counts = ['converted' => 0, 'already' => 0, 'empty' => 0, 'kept' => 0];
        $kept = [];
        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $path, $update, $country, $dryRun, &$counts, &$kept): void {
                foreach ($rows as $row) {
                    $value = $row->{$column};
                    $phone = $path !== null
                        ? data_get(is_string($value) ? json_decode($value, true) : $value, $path)
                        : $value;
                    if (! is_string($phone) || trim($phone) === '') {
                        $counts['empty']++;

                        continue;
                    }

                    $number = PhoneNumber::toE164($phone, $country);
                    if ($number === $phone) {
                        $counts['already']++;
                    } elseif ($number === null) {
                        $counts['kept']++;
                        $kept[] = [$row->id, preg_replace(['/\d/', '/\p{L}/u'], ['9', 'a'], $phone)];
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
            $this->table(['id', 'shape'], $kept);
        }
    }
}
