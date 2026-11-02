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
     * Drafts have the default tone.
     */
    public function tone(): ?string
    {
        return match ($this) {
            self::Draft => null,
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }
}
