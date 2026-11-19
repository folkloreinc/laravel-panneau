<?php

namespace TestApp\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * A run with an accessor, which aggregates sum in PHP (decision 0024).
 */
class TaxedRun extends Run
{
    protected $table = 'runs';

    /**
     * The tokens and a tenth more.
     *
     * @return Attribute<int|null, never>
     */
    protected function taxedTokens(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->tokens !== null ? (int) round($this->tokens * 1.1) : null);
    }
}
