<?php

namespace Panneau\Layout;

final class Section extends Container
{
    private ?string $description = null;

    private bool $collapsible = false;

    private bool $collapsed = false;

    /**
     * @param  list<Node>  $children
     */
    public function __construct(private ?string $label = null, array $children = [])
    {
        parent::__construct($children);
    }

    /**
     * @param  list<Node>  $children
     */
    public static function make(?string $label = null, array $children = []): self
    {
        return new self($label, $children);
    }

    public function type(): string
    {
        return 'section';
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function collapsible(bool $collapsed = false): self
    {
        $this->collapsible = true;
        $this->collapsed = $collapsed;

        return $this;
    }

    protected function properties(): array
    {
        return array_filter([
            'label' => $this->label,
            'description' => $this->description,
            'collapsible' => $this->collapsible ?: null,
            'collapsed' => $this->collapsed ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
