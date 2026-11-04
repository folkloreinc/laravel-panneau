<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Panneau\Money\Currency;
use Panneau\Money\Money;

/**
 * Turns the amounts that a project kept in major units (floats, decimals, numeric strings: `12.5`
 * dollars) into minor units (`1250` cents), the amounts of `Money` fields (panneau-js decision
 * 0053), rounded with the digits of the currency in ISO 4217. Its targets are those of
 * `panneau.money.columns`, or its arguments:
 *
 * - `table.column:target_column`, when the table has `target_column`: the amount of `column`
 *   (a decimal or a float) goes to `target_column`, an integer column that the project added by
 *   migration, for the `AsMoney` cast. A row whose target is filled is already converted.
 * - `table.column:path.in.json` otherwise: the number at that key of the JSON column becomes a
 *   money value, `{ "amount": 1250, "currency": "CAD" }`, in place.
 *
 * Every amount is in the currency of `--currency`, or `panneau.money.currency`. Values that don't
 * read as a number stay as they are, and are listed with `-v` by their shape (`99,99 $`, an `a`
 * for each letter), never their value. Running it again changes nothing. Rows are read and
 * written by their `id`, without their model: dates don't change, and deleted rows convert too.
 */
final class ConvertMoneyCommand extends Command
{
    protected $signature = 'panneau:convert-money
        {columns?* : What to convert, instead of panneau.money.columns: table.column:target_column, or table.column:path.in.json}
        {--currency= : The ISO 4217 currency of the amounts, instead of panneau.money.currency}
        {--dry-run : Count and list what would change, without writing}';

    protected $description = 'Turn the amounts kept in major units (floats, decimals) into minor units';

    public function handle(): int
    {
        /** @var list<string> $columns */
        $columns = $this->argument('columns') ?: (array) config('panneau.money.columns', []);
        $currency = $this->option('currency') ?? config('panneau.money.currency');
        $dryRun = (bool) $this->option('dry-run');
        if ($currency === null) {
            $this->components->error('Set panneau.money.currency, or pass --currency: the currency of the amounts (CAD).');

            return self::FAILURE;
        }
        if (! Currency::isCode($currency)) {
            $this->components->error(sprintf('%s is not an ISO 4217 currency code, in capitals.', var_export($currency, true)));

            return self::FAILURE;
        }

        if ($columns === []) {
            $this->components->warn('Nothing to convert: list the columns in panneau.money.columns.');

            return self::SUCCESS;
        }

        foreach ($columns as $column) {
            $this->convert($column, $currency, $dryRun);
        }

        return self::SUCCESS;
    }

    private function convert(string $target, string $currency, bool $dryRun): void
    {
        if (preg_match('/^(\w+)\.(\w+):([\w.]+)$/', $target, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not table.column:target_column, nor table.column:path.in.json.', $target));
        }
        [, $table, $column, $to] = $match;
        $toColumn = ! str_contains($to, '.') && Schema::hasColumn($table, $to);
        $counts = ['converted' => 0, 'already' => 0, 'empty' => 0, 'kept' => 0];
        $kept = [];

        DB::table($table)
            ->select($toColumn ? ['id', $column, $to] : ['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $to, $toColumn, $currency, $dryRun, &$counts, &$kept): void {
                foreach ($rows as $row) {
                    $value = $row->{$column};
                    if ($toColumn) {
                        $amount = $value;
                        $done = $row->{$to} !== null;
                    } else {
                        $amount = data_get(is_string($value) ? json_decode($value, true) : $value, $to);
                        $done = is_array($amount) && is_int($amount['amount'] ?? null) && Currency::isCode($amount['currency'] ?? null);
                    }
                    if ($done) {
                        $counts['already']++;

                        continue;
                    }
                    if ($amount === null || (is_string($amount) && trim($amount) === '')) {
                        $counts['empty']++;

                        continue;
                    }
                    $money = self::money($amount, $currency);
                    if ($money === null) {
                        $counts['kept']++;
                        $kept[] = [$row->id, self::shape($amount)];

                        continue;
                    }
                    $counts['converted']++;
                    if (! $dryRun) {
                        DB::table($table)->where('id', $row->id)->update($toColumn
                            ? [$to => $money->amount]
                            : [$column.'->'.str_replace('.', '->', $to) => $money->toArray()]);
                    }
                }
            });

        $this->components->info(sprintf(
            '%s: %d %s, %d already in minor units, %d without an amount, %d kept as they are',
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

    /**
     * An amount in major units: a number, or a string of one (`12.50`, `-3`, `1e3`).
     */
    private static function money(mixed $amount, string $currency): ?Money
    {
        if (! is_int($amount) && ! is_float($amount) && ! (is_string($amount) && is_numeric(trim($amount)))) {
            return null;
        }
        try {
            return Money::fromDecimal($amount, $currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The shape of a value, without the value: a `9` for each digit, an `a` for each letter.
     */
    private static function shape(mixed $value): string
    {
        if (is_array($value)) {
            return 'an object or a list';
        }
        if (is_bool($value)) {
            return 'a boolean';
        }

        return (string) preg_replace(['/\d/', '/\p{L}/u'], ['9', 'a'], is_scalar($value) ? (string) $value : get_debug_type($value));
    }
}
