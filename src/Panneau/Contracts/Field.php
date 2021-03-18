<?php

namespace Panneau\Contracts;

use Illuminate\Http\Request;

interface Field
{
    public function name(): string;

    public function type(): string;

    public function component(): string;

    public function label(): ?string;

    public function required(): bool;

    public function properties(): ?array;

    public function attributes(): ?array;

    public function sibblingFields(): ?array;

    public function getRules(Request $request): array;
}
