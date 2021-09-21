<?php

namespace Panneau\Contracts;

use Illuminate\Http\Request;
use JsonSerializable;

interface Field extends JsonSerializable
{
    public function name(): ?string;

    public function label(): ?string;

    public function type(): string;

    public function component(): string;

    public function required(): bool;

    public function disabled(): bool;

    public function defaultValue();

    public function properties(): ?array;

    public function components(): ?array;

    public function settings(): ?array;

    public function getRulesFromRequest(Request $request): array;
}
