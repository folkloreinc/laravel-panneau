<?php

namespace Panneau\Lists;

/**
 * A column of a list: a field, or a column with its own id.
 */
final class Column
{
    private ?string $label = null;

    private ?string $display = null;

    private ?bool $sortable = null;

    private int|string|null $width = null;

    private ?string $align = null;

    private function __construct(private ?string $field, private ?string $id) {}

    /**
     * A column that shows a field.
     */
    public static function make(string $field): self
    {
        return new self($field, null);
    }

    /**
     * A column that is not a field, rendered by the frontend.
     */
    public static function id(string $id): self
    {
        return new self(null, $id);
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function display(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Whether the column can be sorted. By default, columns of the list's sorts are.
     */
    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function width(int|string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * @param  'start'|'center'|'end'  $align
     */
    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    /**
     * @param  list<string>  $sorts
     * @return array<string, mixed>
     */
    public function toDefinition(array $sorts): array
    {
        $sortable = $this->sortable ?? ($this->field !== null && in_array($this->field, $sorts, true));

        return array_filter([
            'field' => $this->field,
            'id' => $this->id,
            'label' => $this->label,
            'display' => $this->display,
            'sortable' => $sortable ?: null,
            'width' => $this->width,
            'align' => $this->align,
        ], fn (mixed $value): bool => $value !== null);
    }
}
