<?php

namespace Panneau\Actions;

/**
 * The confirmation shown before running an action.
 */
final class Confirm
{
    private ?string $title = null;

    private ?string $confirmLabel = null;

    private ?string $cancelLabel = null;

    /**
     * @param  string|null  $message  An ICU message; `{count}` is the number of items.
     */
    public function __construct(private ?string $message = null) {}

    public static function make(?string $message = null): self
    {
        return new self($message);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function confirmLabel(string $label): self
    {
        $this->confirmLabel = $label;

        return $this;
    }

    public function cancelLabel(string $label): self
    {
        $this->cancelLabel = $label;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'title' => $this->title,
            'message' => $this->message,
            'confirmLabel' => $this->confirmLabel,
            'cancelLabel' => $this->cancelLabel,
        ], fn (?string $value): bool => $value !== null);
    }
}
