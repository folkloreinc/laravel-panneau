<?php

namespace TestApp\Models;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The tones of panneau-js's docs/protocol.md: a draft hasn't started (the default, neutral),
     * what is published is live, and what is archived is over.
     */
    public function tone(): ?string
    {
        return match ($this) {
            self::Draft => null,
            self::Published => 'success',
            self::Archived => 'muted',
        };
    }
}
