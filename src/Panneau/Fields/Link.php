<?php

namespace Panneau\Fields;

use Closure;
use Illuminate\Validation\Rule as LaravelRule;
use InvalidArgumentException;
use Panneau\Fields\Concerns\ValidatesEachLocale;
use Panneau\Forms\RuleSet;
use Panneau\Forms\ValidationContext;

/**
 * A link (panneau-js decision 0055): `{ "type", "url", "page", "file", "label", "newTab" }`.
 *
 * - **Types**, in `types()`: `url` (an `http://` or `https://` URL), `page` (an item of the
 *   resources of `pages()`), `email` (`mailto:` and an address), `tel` (`tel:` and a number in
 *   E.164), `file` (a media of the libraries of `libraries()`), and the types of a site, with an
 *   id, a label and a pattern. By default, `url`, `email` and `tel`, after `page` with `pages()`.
 * - **`url`** is the `href` of the link. For `page` and `file` links, items give the URL the
 *   backend resolves (the page's with `resolvePageUrlUsing()`, the file's), and requests' are
 *   ignored.
 * - **`page`** is an id in requests, or `{ "id", "resource" }` when the field has several
 *   resources; the repository receives `{ "id", "resource" }`, and items give a reference,
 *   `{ "id", "resource", "label" }`. **`file`** is a media value, as for `Media` fields.
 * - **`label`** is a string per locale (`{ "fr": "…" }`), or a string in a `localized()` link,
 *   whose every part is per locale already. **`newTab`** is always there: with `newTab('never')`
 *   it is false, with `newTab('external')` the backend sets it by the type.
 * - **Checks**, keyed by the path of each part: the type (`link.type`), the URL of its type
 *   (`link.url`), the page or the file (`link.page`, `link.file`), and the label with
 *   `linkLabel('required')` (`link.label`).
 * - **Model.** The attribute needs a JSON column with the `array` cast. Items resolve the pages
 *   and files of the links at the top of a resource only: in groups and blocks, values give what
 *   the repository received.
 */
class Link extends Field
{
    use ValidatesEachLocale;

    /**
     * The built-in types of links.
     */
    public const TYPES = ['url', 'page', 'email', 'tel', 'file'];

    /**
     * Which links open in a new tab: as the user chooses, by their type, or none.
     */
    public const NEW_TAB = ['choose', 'external', 'never'];

    /**
     * Whether links have a label: optional, required, or none.
     */
    public const LABELS = ['optional', 'required', 'none'];

    /**
     * The resources of `page` links, by class or id.
     *
     * @var list<string>
     */
    protected array $pages = [];

    /**
     * Resolves the URL of the item of a `page` link.
     *
     * @var (Closure(object, string): ?string)|null
     */
    protected ?Closure $pageUrlResolver = null;

    public function type(): string
    {
        return 'link';
    }

    /**
     * The types the field offers, in order: built-in names (`url`, `page`, `email`, `tel`,
     * `file`), or a site's types, `['id' => 'anchor', 'label' => 'Anchor', 'pattern' =>
     * '^#[\w-]+$', 'placeholder' => '#program']`, whose `url` is the text typed, checked
     * against `pattern` when it has one.
     *
     * @param  list<string|array<string, string>>  $types
     *
     * @throws InvalidArgumentException For an unknown name, or a type without an id or a label.
     */
    public function types(array $types): static
    {
        $checked = [];
        $ids = [];
        foreach ($types as $type) {
            if (is_string($type)) {
                if (! in_array($type, self::TYPES, true)) {
                    throw new InvalidArgumentException(sprintf('"%s" is not a built-in type of link: %s. Give a type of the site as an array with an id and a label.', $type, implode(', ', self::TYPES)));
                }
                $id = $type;
            } else {
                $id = $type['id'] ?? null;
                if (! is_string($id) || $id === '' || ! is_string($type['label'] ?? null)) {
                    throw new InvalidArgumentException('A type of link of the site needs an id and a label: [\'id\' => \'anchor\', \'label\' => \'Anchor\'].');
                }
                if (in_array($id, self::TYPES, true)) {
                    throw new InvalidArgumentException(sprintf('"%s" is a built-in type of link: name it as a string.', $id));
                }
                $type = array_filter(
                    array_intersect_key($type, array_flip(['id', 'label', 'pattern', 'placeholder'])),
                    is_string(...),
                );
            }
            if (in_array($id, $ids, true)) {
                throw new InvalidArgumentException(sprintf('The type of link "%s" is listed twice.', $id));
            }
            $ids[] = $id;
            $checked[] = $type;
        }

        return $this->settings(['types' => $checked]);
    }

    /**
     * The resources whose items `page` links point to: classes or ids, one, a list, or several
     * arguments (`pages(PagesResource::class, EventsResource::class)`).
     *
     * @param  string|array<array-key, string>  $resource
     */
    public function pages(string|array $resource, string ...$more): static
    {
        $resources = [...(is_string($resource) ? [$resource] : array_values($resource)), ...array_values($more)];
        $this->pages = array_values(array_unique($resources));

        return $this;
    }

    /**
     * The resources of `page` links, as given to `pages()`.
     *
     * @return list<string>
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * The media libraries of `file` links: classes or ids of `MediaResource`s. Without, every
     * library of the definition.
     *
     * @param  list<string>  $libraries
     */
    public function libraries(array $libraries): static
    {
        return $this->settings(['libraries' => array_values(array_unique($libraries))]);
    }

    /**
     * Which links open in a new tab: `choose` (the default, a checkbox), `external` (by the type:
     * `url` and `file` links, and links of a site's type to another site) or `never`.
     */
    public function newTab(string $newTab): static
    {
        if (! in_array($newTab, self::NEW_TAB, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a setting of new tabs: %s.', $newTab, implode(', ', self::NEW_TAB)));
        }

        return $this->settings(['newTab' => $newTab]);
    }

    /**
     * Whether links have a label (`settings.label`): `optional` (the default), `required`, or
     * `none`, when the site writes the words. Not `label()`, the label of the field.
     */
    public function linkLabel(string $label): static
    {
        if (! in_array($label, self::LABELS, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a setting of link labels: %s.', $label, implode(', ', self::LABELS)));
        }

        return $this->settings(['label' => $label]);
    }

    /**
     * Resolves the URL of the item of a `page` link, which items give in `url`: receives the item
     * and the id of its resource, and returns its URL, or null. Without it, `page` links have no
     * `url`, and sites resolve the page themselves.
     *
     * @param  Closure(object, string): ?string  $resolver
     */
    public function resolvePageUrlUsing(Closure $resolver): static
    {
        $this->pageUrlResolver = $resolver;

        return $this;
    }

    /**
     * The URL of the item of a `page` link, from `resolvePageUrlUsing()`.
     */
    public function pageUrl(object $item, string $resource): ?string
    {
        $url = $this->pageUrlResolver !== null ? ($this->pageUrlResolver)($item, $resource) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The types the field offers, as clients read them: those of `types()`, or `url`, `email`
     * and `tel`, after `page` with `pages()`. A `page` type without `pages()` is left out.
     *
     * @return list<array{id: string, label?: string, pattern?: string, placeholder?: string}>
     */
    public function getTypes(): array
    {
        $setting = $this->settings['types'] ?? null;
        $hasPages = $this->pages !== [];
        $entries = is_array($setting) ? $setting : [...($hasPages ? ['page'] : []), 'url', 'email', 'tel'];
        $types = [];
        foreach ($entries as $entry) {
            $type = null;
            if (is_string($entry) && in_array($entry, self::TYPES, true)) {
                $type = ['id' => $entry];
            } elseif (is_array($entry) && is_string($entry['id'] ?? null) && $entry['id'] !== '') {
                $type = ['id' => $entry['id']];
                foreach (['label', 'pattern', 'placeholder'] as $key) {
                    if (is_string($entry[$key] ?? null)) {
                        $type[$key] = $entry[$key];
                    }
                }
            }
            if ($type !== null && ($type['id'] !== 'page' || $hasPages) && ! isset($types[$type['id']])) {
                $types[$type['id']] = $type;
            }
        }

        return array_values($types);
    }

    public function getNewTab(): string
    {
        $newTab = $this->settings['newTab'] ?? null;

        return in_array($newTab, ['external', 'never'], true) ? $newTab : 'choose';
    }

    public function getLinkLabel(): string
    {
        $label = $this->settings['label'] ?? null;

        return in_array($label, ['required', 'none'], true) ? $label : 'optional';
    }

    /**
     * The media field of `file` links: their checks, their values and their references are those
     * of a `Media` field of the libraries of `libraries()`.
     */
    public function fileField(): Media
    {
        $libraries = $this->settings['libraries'] ?? [];

        return Media::make($this->name)
            ->label('file')
            ->resource(is_array($libraries) ? array_values(array_filter($libraries, is_string(...))) : [])
            ->required();
    }

    /**
     * Whether a link opens in a new tab: the user's choice with `choose`, never with `never`, and
     * with `external`, `true` for `url` and `file` links, `false` for `page`, `email` and `tel`
     * links, and for a site's types, whether their URL points to another site.
     *
     * @param  array<array-key, mixed>  $link
     */
    public function opensInNewTab(array $link): bool
    {
        return match ($this->getNewTab()) {
            'never' => false,
            'choose' => ($link['newTab'] ?? false) === true,
            default => match ($link['type'] ?? null) {
                'url', 'file' => true,
                'page', 'email', 'tel' => false,
                default => is_string($link['url'] ?? null) && preg_match('#^(https?:)?//#i', trim($link['url'])) === 1,
            },
        };
    }

    protected function typeDefinition(): array
    {
        return match (count($this->pages)) {
            0 => [],
            1 => ['resource' => $this->pages[0]],
            default => ['resource' => $this->pages],
        };
    }

    protected function valueRules(ValidationContext $context): array
    {
        return ['array'];
    }

    protected function collectNestedRules(RuleSet $rules, string $path, mixed $value, ValidationContext $context): void
    {
        if (! is_array($value)) {
            return;
        }
        $types = $this->getTypes();
        $ids = array_column($types, 'id');
        $rules->attribute($path.'.type', __('type'));
        $rules->add($path.'.type', ['required', 'string', LaravelRule::in($ids)]);
        $type = null;
        foreach ($types as $candidate) {
            if ($candidate['id'] === ($value['type'] ?? null)) {
                $type = $candidate;
            }
        }
        if ($type === null) {
            return;
        }

        if ($type['id'] === 'page') {
            $rules->attribute($path.'.page', __('page'));
            $rules->add($path.'.page', ['required', $this->pageRule($context)]);
        } elseif ($type['id'] === 'file') {
            $this->fileField()->collectRules($rules, $path.'.file', $value['file'] ?? null, $value, $path.'.', $context);
        } else {
            $rules->attribute($path.'.url', __('URL'));
            $rules->add($path.'.url', ['required', 'string', 'max:2048', ...$this->urlRules($type)]);
        }

        $label = $this->getLinkLabel();
        if ($label === 'none') {
            return;
        }
        $rules->attribute($path.'.label', __('label'));
        if ($this->localized) {
            $rules->add($path.'.label', [$label === 'required' ? 'required' : 'nullable', 'string', 'max:255']);
        } else {
            $labelRules = [$label === 'required' ? 'required' : 'nullable', 'array'];
            if ($label === 'required') {
                $labelRules[] = function (string $attribute, mixed $labels, Closure $fail): void {
                    if (is_array($labels) && self::labels($labels, []) === []) {
                        $fail(__('The :attribute field is required.'));
                    }
                };
            }
            $rules->add($path.'.label', $labelRules);
            foreach ($context->locales as $locale) {
                $rules->attribute($path.'.label.'.$locale, __('label').' ('.$locale.')');
                $rules->add($path.'.label.'.$locale, ['nullable', 'string', 'max:255']);
            }
        }
        $rules->add($path.'.newTab', ['nullable', 'boolean']);
    }

    protected function extractValue(mixed $value, ValidationContext $context): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $type = $value['type'] ?? null;
        $link = ['type' => $type];
        if ($type === 'page') {
            [$id, $resource] = $this->pageOf($value['page'] ?? null, $context);
            $link['page'] = $id !== null && is_string($resource) ? ['id' => $id, 'resource' => $resource] : null;
        } elseif ($type === 'file') {
            $link['file'] = $this->fileField()->extract($value['file'] ?? null, $context);
        } else {
            $link['url'] = is_string($value['url'] ?? null) ? trim($value['url']) : null;
        }
        $label = $value['label'] ?? null;
        if ($this->getLinkLabel() !== 'none') {
            if ($this->localized && is_string($label) && trim($label) !== '') {
                $link['label'] = trim($label);
            } elseif (! $this->localized && is_array($label) && ($labels = self::labels($label, $context->locales)) !== []) {
                $link['label'] = $labels;
            }
        }
        $link['newTab'] = $this->opensInNewTab($link + ['newTab' => filter_var($value['newTab'] ?? false, FILTER_VALIDATE_BOOLEAN)]);

        return $link;
    }

    /**
     * The id and the resource of the page of a `page` link: an id, or `{ "id", "resource" }`.
     * The resource is an id, among those of `pages()`, or the only one; false when the value
     * names another resource, or none when the field has several.
     *
     * @return array{?string, string|false|null}
     */
    public function pageOf(mixed $page, ValidationContext $context): array
    {
        $id = Media::idOf($page);
        $resources = array_map(
            fn (string $resource): string => $context->resourceId !== null ? (($context->resourceId)($resource) ?? $resource) : $resource,
            $this->pages,
        );
        $named = is_array($page) ? ($page['resource'] ?? null) : null;
        if ($named !== null) {
            return [$id, is_string($named) && in_array($named, $resources, true) ? $named : false];
        }

        return [$id, count($resources) === 1 ? $resources[0] : (count($resources) === 0 ? null : false)];
    }

    /**
     * Checks the page of a `page` link: an id, of an item of the field's resources, within
     * their scope.
     *
     * @return Closure(string, mixed, Closure): void
     */
    private function pageRule(ValidationContext $context): Closure
    {
        return function (string $attribute, mixed $page, Closure $fail) use ($context): void {
            [$id, $resource] = $this->pageOf($page, $context);
            if ($id === null) {
                $fail(__('The :attribute must be an item id.'));

                return;
            }
            if (! is_string($resource)) {
                $fail(is_array($page) && array_key_exists('resource', $page) && $page['resource'] !== null
                    ? __('The resource of the :attribute is not one this field allows.')
                    : __('The :attribute allows several resources: name the resource.'));

                return;
            }
            if ($context->relationExists !== null && ! ($context->relationExists)($resource, $id)) {
                $fail(__('The selected :attribute is invalid.'));
            }
        };
    }

    /**
     * The rules of the `url` of a type: an `http://` or `https://` URL, `mailto:` and an email
     * address (with a query, if any), `tel:` and a number in E.164, or the pattern of a site's
     * type.
     *
     * @param  array{id: string, label?: string, pattern?: string, placeholder?: string}  $type
     * @return list<mixed>
     */
    private function urlRules(array $type): array
    {
        $check = match ($type['id']) {
            'url' => fn (string $url): bool => preg_match('#^https?://#i', $url) === 1
                && preg_match('/\s/', $url) !== 1
                && is_string(parse_url($url, PHP_URL_HOST)) && parse_url($url, PHP_URL_HOST) !== '',
            'email' => fn (string $url): bool => preg_match('/^mailto:/i', $url) === 1
                && filter_var(explode('?', substr($url, 7))[0], FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false,
            'tel' => fn (string $url): bool => preg_match('/^tel:/i', $url) === 1 && preg_match(Tel::E164, substr($url, 4)) === 1,
            default => null,
        };
        if ($check === null) {
            return isset($type['pattern']) ? ['regex:'.self::regex($type['pattern'])] : [];
        }
        $message = match ($type['id']) {
            'url' => 'The :attribute must be an http:// or https:// URL.',
            'email' => 'The :attribute must be mailto: and an email address.',
            default => 'The :attribute must be tel: and a phone number in E.164.',
        };

        return [function (string $attribute, mixed $url, Closure $fail) use ($check, $message): void {
            if (is_string($url) && ! $check(trim($url))) {
                $fail(__($message));
            }
        }];
    }

    /**
     * The filled labels of a link, trimmed: those of the locales, or all of them without.
     *
     * @param  array<array-key, mixed>  $labels
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private static function labels(array $labels, array $locales): array
    {
        $filled = [];
        foreach ($labels as $locale => $label) {
            if (is_string($locale) && is_string($label) && trim($label) !== ''
                && ($locales === [] || in_array($locale, $locales, true))) {
                $filled[$locale] = trim($label);
            }
        }

        return $filled;
    }
}
