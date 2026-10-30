<?php

namespace Panneau\Upgrade;

/**
 * A part of the audit: its findings, under a title.
 *
 * @internal
 */
final class Section
{
    /**
     * @var list<Finding>
     */
    public array $findings = [];

    public function __construct(
        public readonly string $key,
        public readonly string $title,
    ) {}

    public function add(string $subject, string $target, ?string $location = null): Finding
    {
        return $this->findings[] = new Finding($subject, $target, $location);
    }

    public function find(string $subject): ?Finding
    {
        foreach ($this->findings as $finding) {
            if ($finding->subject === $subject) {
                return $finding;
            }
        }

        return null;
    }

    public function manualCount(): int
    {
        return array_sum(array_map(fn (Finding $finding): int => $finding->manualCount(), $this->findings));
    }
}
