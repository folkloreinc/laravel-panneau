<?php

namespace Panneau\Fields;

use InvalidArgumentException;

/**
 * Rich text, stored as HTML (panneau-js decision 0007), edited with the `@panneau/editor` plugin.
 * Its settings set what users can add, not what a value may hold: HTML written by another editor
 * (CKEditor) saves unchanged until it is edited.
 */
class RichText extends Text
{
    /** The elements a block style can apply to. */
    public const STYLE_ELEMENTS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'li'];

    /** Which links open in a new tab: as the user chooses, every link to another site, or none. */
    public const NEW_TAB = ['choose', 'external', 'never'];

    public function type(): string
    {
        return 'richtext';
    }

    /**
     * The toolbar, in order: `heading`, `style`, `bold`, `italic`, `underline`, `strike`,
     * `superscript`, `subscript`, `code`, `link`, `bulletList`, `orderedList`, `blockquote`,
     * `horizontalRule`, `clear`, `undo`, `redo`, the buttons of JS plugins, and `|` between
     * groups.
     *
     * @param  list<string>  $items
     */
    public function toolbar(array $items): static
    {
        foreach ($items as $item) {
            if (trim($item) === '') {
                throw new InvalidArgumentException('The toolbar of a rich text field names its buttons: an item is empty.');
            }
        }

        return $this->settings(['toolbar' => $items]);
    }

    /**
     * The heading levels the toolbar offers, from 1 to 6. Defaults to 2, 3 and 4.
     */
    public function headings(int ...$levels): static
    {
        foreach ($levels as $level) {
            if ($level < 1 || $level > 6) {
                throw new InvalidArgumentException(sprintf('Heading levels go from 1 to 6, not %d.', $level));
            }
        }

        return $this->settings(['headings' => array_values($levels)]);
    }

    /**
     * Adds a block style: a class that the toolbar puts on an element, `<p class="big">`. The
     * element is `p`, `h1` to `h6`, `blockquote`, `ul`, `ol` or `li`. Several classes are
     * separated by spaces.
     */
    public function style(string $class, string $label, string $element = 'p'): static
    {
        if (trim($class) === '') {
            throw new InvalidArgumentException(sprintf('The block style "%s" needs a class.', $label));
        }
        if (! in_array($element, self::STYLE_ELEMENTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'A block style applies to %s, not "%s".',
                implode(', ', self::STYLE_ELEMENTS),
                $element,
            ));
        }
        /** @var list<array<string, string>> $styles */
        $styles = $this->settings['styles'] ?? [];
        $styles[] = $element === 'p'
            ? ['label' => $label, 'class' => $class]
            : ['label' => $label, 'class' => $class, 'element' => $element];

        return $this->settings(['styles' => $styles]);
    }

    /**
     * Which links open in a new tab (`target="_blank" rel="noopener noreferrer"`): as the user
     * chooses in the link dialog (`choose`, the default), every link to another site (`external`),
     * or none (`never`).
     */
    public function linksInNewTab(string $newTab): static
    {
        if (! in_array($newTab, self::NEW_TAB, true)) {
            throw new InvalidArgumentException(sprintf('Links open in a new tab as the user chooses (choose), when they go to another site (external) or never (never), not "%s".', $newTab));
        }

        return $this->settings(['links' => ['newTab' => $newTab]]);
    }
}
