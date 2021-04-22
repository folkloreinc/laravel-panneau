<?php

namespace Panneau\Contracts;

use Illuminate\Http\Request;
use JsonSerializable;

interface Field extends JsonSerializable
{
    public function name(): string;

    public function label(): ?string;

    public function type(): string;

    public function component(): string;

    public function required(): bool;

    public function defaultValue();

    public function properties(): ?array;

    public function attributes(): ?array;

    public function meta(): ?array;

    public function components(): ?array;

    public function sibblingFields(): ?array;

    public function exceptTypes(): ?array;

    public function onlyTypes(): ?array;

    public function getRulesFromRequest(Request $request): array;
}
