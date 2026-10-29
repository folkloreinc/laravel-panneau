<?php

namespace Panneau\Upgrade;

/**
 * Something the upgrade found in the project, and what it becomes in 5.x, with the details
 * found in it (the properties of a resource, the methods of a controller).
 *
 * @internal
 */
final class Finding
{
    /**
     * @var list<Finding>
     */
    public array $details = [];

    /**
     * @param  string  $subject  What was found.
     * @param  string  $target  What it becomes; "manual: …" when it is moved by hand.
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $target,
        public readonly ?string $location = null,
    ) {}

    public function add(string $subject, string $target, ?string $location = null): self
    {
        $this->details[] = $detail = new self($subject, $target, $location);

        return $detail;
    }

    public function isManual(): bool
    {
        return str_starts_with($this->target, 'manual');
    }

    public function detail(string $subject): ?self
    {
        foreach ($this->details as $detail) {
            if ($detail->subject === $subject) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Itself and its details that are manual.
     */
    public function manualCount(): int
    {
        return array_sum(array_map(fn (self $detail): int => $detail->manualCount(), $this->details)) + ($this->isManual() ? 1 : 0);
    }
}
