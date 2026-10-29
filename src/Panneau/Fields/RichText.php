<?php

namespace Panneau\Fields;

/**
 * Rich text, stored as HTML (panneau-js decision 0007), edited with the `@panneau/editor` plugin.
 * Its settings set what users can add, not what a value may hold: HTML written by another editor
 * (CKEditor) saves unchanged until it is edited.
 */
class RichText extends Text
{
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
        return $this->settings(['toolbar' => $items]);
    }

    /**
     * The heading levels the toolbar offers, from 1 to 6. Defaults to 2, 3 and 4.
     */
    public function headings(int ...$levels): static
    {
        return $this->settings(['headings' => array_values($levels)]);
    }

    /**
     * Adds a block style: a class that the toolbar puts on an element, `<p class="big">`. The
     * element is `p`, `h1` to `h6`, `blockquote`, `ul`, `ol` or `li`. Several classes are
     * separated by spaces.
     */
    public function style(string $class, string $label, string $element = 'p'): static
    {
        /** @var list<array<string, string>> $styles */
        $styles = $this->settings['styles'] ?? [];
        $styles[] = $element === 'p'
            ? ['label' => $label, 'class' => $class]
            : ['label' => $label, 'class' => $class, 'element' => $element];

        return $this->settings(['styles' => $styles]);
    }

    /**
     * Which links open in a new tab (`target="_blank" rel="noopener noreferrer"`): as the user
     * chooses in the link dialog (the default), every link to another site, or none.
     *
     * @param  'choose'|'external'|'never'  $newTab
     */
    public function linksInNewTab(string $newTab): static
    {
        return $this->settings(['links' => ['newTab' => $newTab]]);
    }
}
