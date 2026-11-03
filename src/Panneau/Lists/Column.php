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

    private bool $hidden = false;

    private bool $hideable = true;

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
     * Not shown by default: users show it from the columns of the list (panneau-js decision 0042).
     */
    public function hidden(bool $hidden = true): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Whether users can hide the column. A column that can't be hidden is always shown.
     */
    public function hideable(bool $hideable = true): self
    {
        $this->hideable = $hideable;

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
            'hidden' => $this->hidden && $this->hideable ? true : null,
            'hideable' => $this->hideable ? null : false,
        ], fn (mixed $value): bool => $value !== null);
    }
}
