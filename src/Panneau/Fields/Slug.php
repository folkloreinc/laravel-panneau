<?php

namespace Panneau\Fields;

class Slug extends Text
{
    protected ?string $from = null;

    public function type(): string
    {
        return 'slug';
    }

    /**
     * The field the slug is generated from, in the form.
     */
    public function from(string $field): static
    {
        $this->from = $field;

        return $this;
    }

    protected function typeDefinition(): array
    {
        return $this->from !== null ? ['from' => $this->from] : [];
    }
}
