# Architecture (Laravel)

Status: accepted direction for 5.0, released as alphas (see the [CHANGELOG](../CHANGELOG.md)). The examples show the intended API. What isn't built yet is marked **Not built yet**, with its issue or its slice of the [roadmap](roadmap.md).

`laravel-panneau` 5 is the reference implementation of the [Panneau protocol](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/protocol.md). It turns PHP resource classes into a definition, and the items of a repository (Entities, Eloquent models or any object) into protocol responses.

## Installation

```bash
composer require folklore/laravel-panneau
php artisan panneau:install
```

`panneau:install` asks a few questions (or takes them as options: `--guard`, `--login-url`, `--resource`; `--no-js` skips the JS part):

- **guard** of the admin users;
- **login URL** where unauthenticated users are sent;
- **a first resource:** an Eloquent model, whose columns give the first fields;
- later: the **auth mode** (Panneau's own login, or the site's existing auth) and **features** (roles, media, search), as they are built.

It then writes, keeping existing files unless `--force`:

- `config/panneau.php`;
- `app/Providers/PanneauServiceProvider.php`, which registers the resources, defines the `viewPanneau` gate (closed until the project opens it) and loads the routes file; it is added to `bootstrap/providers.php`;
- `routes/panneau.php`, which mounts the routes with their middleware;
- a first resource and its policy, if asked (the model's hidden attributes never become fields);
- `resources/js/panneau.tsx`, the JS entry that mounts `@panneau/app` (`createPanneauApp()`), where the project adds its plugins.

It also edits two project files, keeping their formatting and what they already have:

- **`vite.config.js`** (or `.ts`, `.mjs`, `.mts`): the entry is added to the `input` of `laravel()`, and the `react()` plugin of `@vitejs/plugin-react` to `plugins`. When the file doesn't have that shape, it says what to add instead.
- **`package.json`:** `@panneau/app`, `react`, `react-dom` and `@tanstack/react-query` in `dependencies`, and `@vitejs/plugin-react` in `devDependencies`. Packages already there keep their version, except an older major of `@panneau/app`. Until 5.0.0, the range is explicit (`^5.0.0-alpha.10`, the first that applies rules limited to a mode): v5 is published on the `alpha` tag, and `latest` is v4.

With `--upgrade`, it migrates a 1.3 or 1.4 project: it converts its config, writes the provider, routes and JS entry (Vite next to the site's bundler), moves a 1.x override of the page aside, keeps what it changes as `{file}.1x.bak`, and lists what to move by hand and the files the package now provides. `--upgrade --check` only reports, without writing. See the [migration guide](migration.md) and [decisions/0013](decisions/0013-upgrade-from-1x.md).

## Resources

A resource describes how a set of items appears in the admin: its fields, layout, list and actions. It is **backed by a repository, not by a model** (see [decisions/0003](decisions/0003-resources.md)). Configuration is done through instance methods, not static properties.

### With Folklore entities and repositories

Folklore projects keep their Entities and Repositories. The resource points to the repository contract, and the `Entities` adapter registered by the `laravel-folklore` plugin does the rest.

```php
final class PagesResource extends Resource
{
    public function repository(): string
    {
        return \Folklore\Contracts\Repositories\Pages::class; // resolved from the container
    }

    public function itemClass(): string
    {
        return \Folklore\Contracts\Entities\Page::class; // for policies and types
    }

    public function fields(): array
    {
        return [
            Text::make('title')->localized()->required()->max(120),
            Slug::make('slug')->from('title')->localized()->unique()->url($this->pageUrl(...)),
            RichText::make('body')->localized(),
            Media::make('image')->resource(ImagesResource::class), // a media library
            Select::make('status')->options(PageStatus::class)->required(),
            DateTime::make('published_at'),
            Relation::make('author')->resource(AuthorsResource::class),
        ];
    }

    public function layout(): array
    {
        return [
            Tabs::make([
                Tab::make('Content', [
                    Row::make([Field::make('title')->width(8), Field::make('status')->width(4)]),
                    Field::make('body'),
                ]),
                Tab::make('Sharing', [Field::make('image')]),
            ]),
            Sidebar::make([Field::make('slug'), Field::make('published_at'), Field::make('author')]),
        ];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list
            ->columns(['title', 'status', 'published_at'])
            ->filters([
                SelectFilter::make('status')->options(PageStatus::class),
                DateRangeFilter::make('published_at'),
            ])
            ->sorts(['title', 'published_at'])
            ->defaultSort('-published_at')
            ->search(['title', 'body']);
    }

    public function actions(): array
    {
        return [PublishPages::class]; // show, edit, duplicate and delete are built in
    }

    public function scope(Request $request): array
    {
        return ['organisation_id' => $request->user()->organisationId()]; // passed to setGlobalQuery()
    }

    public function toItem(object $page): array
    {
        return PageResource::make($page)->resolve(); // the project's existing JsonResource
    }
}
```

### With an Eloquent model

A project without entities can start from a model. `model()` replaces `repository()`, and the package's `EloquentRepository` is used.

```php
final class TagsResource extends Resource
{
    public function model(): string
    {
        return Tag::class;
    }

    public function fields(): array
    {
        return [Text::make('name')->required(), Slug::make('slug')->from('name')];
    }

    public function scope(Request $request): array
    {
        return ['workspace_id' => $request->user()->workspace_id];
    }
}
```

The resource can move to a repository later without changing its fields, layout, list or actions.

### Repositories

`ResourceRepository` is the only data contract Panneau knows:

```php
interface ResourceRepository
{
    public function query(ResourceQuery $query): Paginator; // filters, sort, search, page, scope
    public function find(string $id, array $scope): ?object;
    public function create(array $values, array $scope): object;
    public function update(object $item, array $values): object;
    public function delete(object $item): void;
    public function id(object $item): string;
    public function version(object $item): ?string; // null disables If-Match
}
```

- **Optional capabilities:** `Restorable`, `Duplicable`, `Searchable`, `ChecksSlugs` (for unique slugs on repositories that aren't Eloquent, see [Slug](#slug)), `ChunkedQuery` (for queued actions), and `CursorPaginated` (for lists paged with cursors). Actions and features that need one are only offered when the repository has it.
- **Cursor pagination** ([panneau-js decision 0039](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0039-cursor-pagination.md)). A list too large to count, or too live for page numbers, asks for cursors: `$list->cursorPagination()`, sent as `"pagination": "cursor"` when the repository is `CursorPaginated` (numbered pages otherwise). The index then reads `cursor` instead of `page`, calls `cursorQuery()`, which returns a `CursorPage`, and answers `meta: { perPage, nextCursor, previousCursor }`, without a total unless the repository counts. `previousCursor` is null when the previous page is the first, which has no cursor. A cursor the repository can't read is a `422` problem with `errors.cursor`.
  - `EloquentRepository` uses `cursorPaginate()` on the columns of the sorts, then the key: the sorted columns must never be null. Its cursors also hold the number of their page, for `previousCursor`, and a cursor from another sort is refused.
  - A Scout search has no cursors: it pages by number (`simplePaginate()`) behind its cursors, still without a total.
- **Search.** A `Searchable` repository applies `$query->search` to `$query->searchFields`, within the scope. The fields can be empty for a resource in the global search through its `features()` alone: the repository searches its own fields then (a search index), or finds nothing, but never ignores the search. See [Search and dashboard](#search-and-dashboard).
- **Adapters:**
  - `EloquentRepository` (package) implements the contract for a model.
  - The `laravel-folklore` adapter wraps any `Folklore\Contracts\Repositories\Entities`. It maps the `ResourceQuery` to `get(params, page, count)` (`queryColumns`, `order`, `order_direction`, the `offset_paginator`), and values to `create()` and `update()`. It reads the version from `updated_at` through `HasModel`, or from the Entity's `version()`.
  - Other packages or projects register their own adapters by contract: `Panneau::repositoryAdapter(Contract::class, Adapter::class)`. Niche, for example, would register one for its workspace-scoped repositories.
- **Items** are any object. Panneau never asks them to implement one of its interfaces.
- **Filter values** reach `query()` normalized by the list's filters, keyed by filter id: a scalar, a list, or for a `DateRangeFilter` the bounds of the range, `['from' => '2026-01-01', 'to' => '2026-01-31']`, both inclusive, either one left out for an open end. `EloquentRepository` applies the bounds with `whereDate()`, so `to` includes the whole day.

### Resource behaviour

- **Registration.** Resources are registered in the service provider (`Panneau::resources([...])`). Discovery in `app/Panneau/Resources` is **not built yet**.
- **Scoping.** `scope()` gives the repository its scope for every operation: list, find, create, update, delete and actions. This is where tenant or organisation isolation goes.
- **Public ids.** The repository's `id()` decides the public id: a numeric id, a UUID, or a `uid` column with prefixed ULIDs.
- **Serialization:**
  - `toItem()` or `jsonResource()` serializes an item; Panneau adds `id`, `version` and `abilities`.
  - With `EloquentRepository` and no `toItem()`, items are serialized from their declared fields only.
- **Policies.** Policies receive the item. For Entities, they are registered on the Entity contract (`Gate::policy(PageContract::class, PagePolicy::class)`); Laravel resolves policies through interfaces. `itemClass()` is the class used for resource-level abilities (`viewAny`, `create`).
  - **`deleteAny($user)`**, when the policy has it, says whether the user can delete items of the resource at all. When it refuses, the definition declares the built-in `delete` with `"abilities": { "run": false }`, so the app offers no "Delete" and no checkboxes for it, and `POST /resources/{resource}/actions/delete` answers `403` ([panneau-js decision 0046](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0046-abilities-in-the-ui.md)).
  - **`runAction($user, ?object $item, string $action)`**, when the policy has it, answers every action of its resource: on an item (the item's ability), and on the resource (`null`, the action's `abilities.run`). Without it, an action is checked by the policy method named after it (`publishPages`), or `update`.
- **Hooks.** `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` and `saved` receive the values and the item. They replace most of the custom controllers of v1.
- **Types.** Variants of a resource with their own fields, such as page types, are declared with `types()`.

## Fields

- **One class per protocol field type:**
  - `Text`, `Textarea`, `RichText`, `Number`, `Boolean`;
  - `Date`, `DateTime`, `Time`;
  - `Select`, `Email`, `Url`, `Color`;
  - `Media`, `Image`, `File`, `Relation`, `Group`, `Repeater`, `Slug`;
  - `Tel`, `Range`, `Duration`, `Json`, `Tags`, `Embed`, `Location` (see [More field types](#more-field-types));
  - `Country`, `Region` (see [Countries, regions and phone numbers](#countries-regions-and-phone-numbers));
  - `DateRange`, `Money`, and the `Sessions` preset (see [Date ranges and money](#date-ranges-and-money));
  - `Address`, `Link` (see [Addresses](#addresses) and [Links](#links));
  - `Password`, a `text` field with `settings.secret` (see [Passwords](#passwords)).
- **Common modifiers:**
  - `label()`, `help()`, `default()`;
  - `localized()`, `readOnly()`, `visibleWhen()`;
  - `canSee()` and `canUpdate()`, below;
  - `createOnly()`, `updateOnly()`.
- **Abilities of a field** ([panneau-js decision 0046](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0046-abilities-in-the-ui.md)). `canSee($ability)` shows a field only to the users who have an ability, and `canUpdate($ability)` lets only them edit it: a Gate ability (`'contests.budget.view'`), or a closure that receives the user. For the others, the definition gives the field `"abilities": { "view": false }` or `{ "update": false }`, items leave out the value of a field they can't see, and saving ignores the values of the fields they can't see or edit, as it ignores read-only ones. For the fields at the top of a resource.
- **Secrets.** `Text::secret()` writes `"settings": { "secret": true }`: clients type the value in a password input that a button reveals, keep the browser from filling it in, and show it as dots in lists and details. The backend still decides whether it sends the value back. A secret the project keeps encrypted (an access key) is usually write-only, `->secret()->writeOnly()`: items have `null` for it, and an update that leaves it out, or sends it empty, keeps the stored value, since the form starts without it. Only for fields at the top of a resource; a resource with its own `toItem()` leaves the value out there.
- **Options** of `Select`, `Tags` and `SelectFilter` are a backed enum class, a list of options (`['value' => …, 'label' => …, 'description' => …, 'disabled' => …, 'tone' => …]`), or labels keyed by value. `tone` colors the option where it shows as a badge, in lists and details: `neutral` (the default), `accent`, `success`, `warning`, `danger`, `info`, or a tone of the theme (`panneau.theme.tones`). An enum case gives its label with a `label()` method, and its tone with a `tone()` method (null for the default).
- **Portable rules** (`required()`, `min()`, `max()`, `pattern()`, `in()`, `accept()`, `maxSize()`, `crop()`, `postalCode()`…) go into the definition and become Laravel rules. Server-only rules are added with `rules([...])`.
- **Rules limited to a mode** ([panneau-js#165](https://github.com/folkloreinc/panneau-js/pull/165)). `required()`, `min()`, `max()`, `pattern()`, `in()`, `before()` and `after()` take a `mode`: `Text::make('code')->required(mode: FormMode::Create)` sends `{ "rule": "required", "mode": "create" }`, and the rule applies only when the item is created, in the admin and on the server. In the other mode, the field accepts no value, `null` or `""`. A rule replaces the rule of the same name that applies in the same modes: `required()` replaces both, `required(mode: …)` only the one of its mode. `isRequired(FormMode::Create)` says whether a field is required in a mode, `isRequired()` in every mode. Admins before `@panneau/*` 5.0.0-alpha.10 ignore `mode`: they apply the rule in both modes.
- **Validation.** Rules are built for the submitted values, so they follow the form:
  - fields hidden by `visibleWhen`, read-only fields, and `createOnly`/`updateOnly` fields outside their mode are neither validated nor saved;
  - a partial update (`PATCH`) only validates the fields it sends;
  - localized values are validated per locale (`title.fr`), and blocks per index and type (`blocks.2.image`), so errors use the protocol's dot paths.
- **Values.** Repositories receive validated values as an array, keyed by field name, as in v1. Values that no field declares are dropped, in groups and blocks too. `Relation` values are the ids of the related items, and `Media` values `{ "id", "transform" }`, with the media's `resource` when the field allows several libraries (see [The `media` field](#the-media-field)). Folklore repositories already handle these values: `data` JSON attributes, relations and media.
- **Saving.** Once the values are valid, and before the `creating` and `updating` hooks, each field prepares its value with `prepare()` (see [decisions/0008](decisions/0008-field-preparation.md)). Most fields keep it as is. A field that keeps something outside the item, like `File`, does that work there, and registers with the `SaveContext` what to do once the repository saved the item (`afterSave()`) or if the save fails (`afterFailure()`). Groups and blocks are saved whole: their fields without a value get `prepareAbsent()`, since their previous value is gone.
- **Serialization.** Values are read from the item with `data_get()`, or `resolveUsing()`. Dates become ISO 8601 strings, related items become references (`{ "id": "12", "label": "Ada" }`), and media media references.
- **Eloquent.** `EloquentRepository` sets each value as an attribute, so casts and mutators apply. `BelongsTo` and `BelongsToMany` relations declared with a return type receive the related ids (associate, sync). Persistence can be customized by extending `EloquentRepository` (`fill()`).
- **Field types from other packages** extend `Field` and declare their protocol type. A type that `@panneau/fields` doesn't render needs a JS plugin; the `laravel-folklore` plugin needs none, since its fields are built-in types ([below](#laravel-folklore)).

### Rich text

`RichText` is the `richtext` type: HTML, edited with the `@panneau/editor` plugin on TipTap ([panneau-js decision 0007](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0007-rich-text.md)). Its methods write the protocol's `RichTextSettings`, with no JS:

```php
RichText::make('body')
    ->toolbar(['heading', 'style', '|', 'bold', 'italic', 'link', 'bulletList', 'orderedList'])
    ->headings(2, 3)                       // the levels the toolbar offers (2, 3 and 4 by default)
    ->style('big', 'Big paragraph')        // <p class="big">
    ->style('small', 'Small title', 'h2')  // <h2 class="small">
    ->linksInNewTab('external');           // choose (the default), external or never
```

- `toolbar()` also takes the buttons of a project's JS plugin (`createEditorPlugin()`), such as an inline logo.
- The settings only limit what users add: the HTML a field already holds, from CKEditor in 1.x, saves unchanged until it is edited, and keeps what the toolbar doesn't offer once it is.
- Settings the protocol refuses (a level outside 1 to 6, a style on another element) throw an `InvalidArgumentException`.
- The value is a string, saved as it is sent.

### Passwords

`Password` is the password of a users resource. It sends no protocol type of its own: in the definition, it is a `text` field with `settings.secret`, which `@panneau/fields` renders as a password input with `autocomplete="new-password"` and a button that shows it, so no JS plugin is needed. It needs `@panneau/*` 5.0.0-alpha.10 or later (see Rules below).

```php
Password::make('password')->label(__('Password')),
Password::make('password')->hashed(false), // for a repository that hashes what it receives
Password::make('password')->optional(),    // optional to create the user too
Password::make('password')->required(),    // the default: required to create, not to update
```

- **Write-only.** Items always give `null`, so the form starts empty, and lists and details never show it.
- **Required to create the item, optional to update it.** On an update, an empty value (`null` or `""`) keeps the current password: the field omits it from the values (`Field::omits()`), so the repository receives no `password`.
- **Rules.** `Password::defaults()` on the server, with `rules()` for more. The definition has its `min`, for the app's own check, and `{ "rule": "required", "mode": "create" }`, so the admin marks it required on create only. This needs `@panneau/*` 5.0.0-alpha.10 or later: an older admin ignores `mode`, and requires the password on update too, which blocks editing a user. `required(mode: FormMode::Update)` requires it on update as well; `rules(['required'])` too, since server-only rules apply in both modes.
- **Hashed once.** `prepare()` hashes the password with `Hash::make()`, unless it is already a hash that the app's hasher accepts (`Hash::isHashed()` and `Hash::verifyConfiguration()`, as Eloquent's `hashed` cast: a bcrypt hash of a lower cost is one), so the repository receives the hash. Projects migrated from 1.x hash in their repository: the Rector rules write `hashed(false)` there. `--check` lists the `Password` fields that don't say who hashes: write `hashed()` when the field hashes, `hashed(false)` when the repository does. Eloquent's `hashed` cast keeps a hash as is, and so does laravel-folklore's `Users` repository once folkloreinc/laravel-folklore#144 is released; before that, use `hashed(false)` with it, which leaves the hashing to the repository.

### More field types

The types of [panneau-js decision 0033](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0033-more-field-types.md). See [decisions/0012](decisions/0012-embeds-and-more-field-types.md).

```php
Tel::make('contact'),
Range::make('rating')->max(10)->suffix('/10'),        // 0 to 100 without min() and max()
Duration::make('reading_time')->precision('minutes'), // seconds
Json::make('metadata'),
Tags::make('keywords')->options(['culture' => 'Culture'])->allowCustom(false)->max(5),
Embed::make('video'),
Location::make('place')->zoom(14)->center(45.5017, -73.5673),
```

- **Values:**
  - `tel` is a phone number in E.164 (see [below](#countries-regions-and-phone-numbers)); `range` a number between its ends (0 and 100 by default); `duration` a whole number of seconds, 0 or more;
  - `json` an object or an array, never a string: an empty object is given back as an empty array;
  - `tags` a list of strings, at most 255 characters each, only the options' values with `allowCustom(false)`. `min()` and `max()` count the tags. Saving trims them and drops empty and repeated ones;
  - `location` is `{ "lat", "lng", "address" }`, with the latitude from -90 to 90, the longitude from -180 to 180, and an optional address;
  - `embed` is the protocol's `EmbedValue`.
- **Embeds.** `GET /embeds?url=` answers what the `EmbedResolver` gives for a URL, or a `422` on `url`. The default resolver asks the oEmbed endpoint of the provider whose `schemes` match the URL, among `panneau.embeds.providers`, and never fetches the URL itself. Answers are cached (`panneau.embeds.cache_ttl`, one day), and the route is rate limited per user (`panneau.embeds.rate_limit`, 60 a minute). Bind `Panneau\Contracts\EmbedResolver` to use another service.
- **Saving an embed** keeps only the URL of the request and resolves it again, so the `html` sites render comes from the provider. An unchanged URL keeps the item's value; a URL that can't be resolved is saved as `{ "url" }`.
- **Lists.** Without columns, a list shows the `tel`, `range`, `duration` and `tags` fields with the other simple types.
- **Model.** `json`, `tags`, `embed` and `location` need a JSON column with the `array` cast.

### Countries, regions and phone numbers

The types of [panneau-js decision 0047](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0047-countries-regions-and-phone-numbers.md), for addresses and contacts.

```php
Country::make('country')->preferred(['CA', 'US']),     // countries(['CA', 'US']) to allow only those
Region::make('province')->from('country')->country('CA'),
Tel::make('phone')->country('CA'),                      // the country of a new number
```

- **Values:**
  - `country` is an ISO 3166-1 alpha-2 code in capitals (`CA`), or `XK` for Kosovo (`Country::CODES`). The server refuses other codes, and those outside `countries()` when the field has them;
  - `region` is a string: the code of the region without its country (`QC` for `CA-QC`) for the countries the admin knows the regions of, the text as typed for the others. The server only checks that it is a string;
  - `tel` is an international number in E.164, `+15145550199`. The type implies the protocol's `tel` rule, as `json` implies `json`: the server refuses any other value, such as a number without its country code or the text of a number not typed in full. `pattern()` still applies on top of it.
- **The country of a region** is the value of the field of the same object that `from()` names, or `country()` when it has no `from()` or that field is empty.
- **Codes.** `countries()`, `preferred()` and `country()` take codes in capitals, and throw an `InvalidArgumentException` for any other.
- **Lists.** Without columns, a list shows `country` and `region` fields with the other simple types.
- **The country of the project.** `panneau.phones.country` (`CA`) is the country of the `Tel` fields without `country()`. Without it, the admin takes the region of the user's locale.
- **Numbers kept as typed.** `php artisan panneau:convert-phones` turns them into E.164, in the columns of `panneau.phones.columns`: `table.column`, or `table.column:path.in.json` for a key of a JSON column.

  ```php
  // config/panneau.php
  'phones' => [
      'country' => 'CA',
      'columns' => ['users.phone', 'orders.data:contact.phone'],
  ],
  ```

  - A number with its calling code (`+33 6…`, `0033 6…`, `011 33 6…`) converts for any country. A number without one converts for the countries whose national numbers `PhoneNumber::COUNTRIES` knows (Canada and the United States, France, Belgium and Switzerland), in the country of the config (`--country` to use another).
  - Anything else (a name, an extension, a digit too many or too few) stays as typed, and `-v` lists it by its shape (`999-9999`, an `a` for each letter), never its value. `--dry-run` counts and lists without writing.
  - It reads and writes rows by their `id`, without their models: their dates don't change, and deleted rows convert too. Running it again changes nothing, so a deployment can run it every time.
  - `PhoneNumber::format()` shows a number in the format of the project's country (`514 555-0199`), or with its calling code (`+33 6 12 34 56 78`), for emails, PDFs and pages; `PhoneNumber::nationalNumber()` gives its digits without the calling code, for exports.
  - No library of numbering plans: the admin checks numbers with the smallest metadata of `libphonenumber-js`, which mostly checks their lengths, and the table above does the same on the server for the numbers it converts.
- **Phone numbers kept as typed** before (`514 555-0199`) are refused until they are converted to E.164, or retyped.

### Date ranges and money

The types of [panneau-js decision 0053](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0053-date-ranges-and-money.md) (folkloreinc/panneau-js#198, folkloreinc/panneau-js#200), for events, editions and campaigns, and for prices, offers and donations.

```php
DateRange::make('dates'),                                          // days, both inclusive
DateRange::make('dates')->time()->open('end'),                     // date-times; the end can be null
DateRange::make('dates')->allDay()->timeZone('America/Toronto'),   // an "All day" switch; the zone of the place
DateRange::make('dates')->time()->chooseTimeZone(),                // a zone per value
Money::make('price')->currency('CAD')->min(0),                     // one currency
Money::make('donation')->currencies(['CAD', 'USD'])->min(500),     // the currencies to pick from; min() in cents
Sessions::make('sessions')->venues(VenuesResource::class)->append([Money::make('price')->currency('CAD')]),
```

- **Values:**
  - `daterange` is `{ "start", "end" }`, or null. Its bounds are days (`2026-11-04`), both inclusive, or with `time()` date-times with their offset (`2026-11-04T23:30:00Z`), which items give in ATOM with the offset of the app. With `allDay()`, which turns `time()` on, an all-day value has days as bounds and `"allDay": true`. With `chooseTimeZone()`, the value keeps the IANA zone the user picked (`"timeZone": "America/Vancouver"`). An empty range (`null`, or both bounds empty) is null;
  - `money` is `{ "amount": 1250, "currency": "CAD" }`, or null: an integer in the minor unit of the currency, its exponent in ISO 4217 (`Currency::digits()`: 2 for `CAD`, 0 for `JPY`, 3 for `KWD`), and an ISO 4217 code in capitals (`Currency::CODES`, the list of `@panneau/protocol`).
- **Rules.** The types imply the protocol's `daterange` and `money` rules, as `tel` implies `tel`: the server enforces them from the settings of the field, and doesn't write them in the definition (the admin adds them, with `open` and `currencies`). A filled range has both bounds, except the one `open()` lets be null (`start`, `end` or `both`), bounds of the right kind, and ends on or after its start; each failure has its message (`Give the start of the dates.`, `Give the end of the dates.`, `The end of the dates can’t be before its start.`), on the path of the field. An amount must be an integer with a currency among `currency()` or `currencies()`, or any known one; `min()` and `max()` apply to the amount, in minor units (`min(100)` is one dollar), and don't convert currencies.
- **Settings.** `open()`, `timeZone()`, `currency()` and `currencies()` throw an `InvalidArgumentException` for an unknown bound, an IANA zone that doesn't exist, or a code that isn't a known ISO 4217 code in capitals.
- **Model.** `AsDateRange` keeps a range in the columns of its bounds, so that both stay sortable and filterable; `AsMoney` keeps an amount in an integer column, with a column of its currency or a fixed one:

  ```php
  protected function casts(): array
  {
      return [
          'dates' => AsDateRange::class.':starts_at,ends_at',            // + ',timezone' for chooseTimeZone(), + ',all_day' for allDay()
          'period' => AsDateRange::columns('starts_at', 'ends_at', timeZone: 'timezone', allDay: 'all_day'),
          'price' => AsMoney::class.':price_cents,currency',             // the currency in a column
          'fee' => AsMoney::class.':fee_cents,CAD',                       // always in CAD
      ];
  }
  ```

  - `AsDateRange` gives `['start' => ?CarbonImmutable, 'end' => ?CarbonImmutable]`, with `timeZone` and `allDay` when they have a column, or null. It stores a day at its midnight and a date-time converted to the zone of the app, as the `datetime` cast does. Without a column for them, the zone and `allDay` of a value are lost.
  - `AsMoney` gives a `Panneau\Money\Money`, or null. Its second argument is a currency when it is a known ISO 4217 code in capitals, a column otherwise. It takes a `Money`, `{ amount, currency }` or null, refuses an amount in another currency than a fixed one, and refuses to read an amount that isn't an integer: convert those first (below).
  - In a group, a repeater or a JSON column, both are kept as their JSON values.
- **`Money`** is a value object: `Money::of(1250, 'CAD')`, `Money::fromDecimal('12.5', 'CAD')` (rounded half away from zero to the digits of the currency, without the errors of floating point: `1.005` is 101 cents), `toDecimal()` (`'12.50'`), `format('fr_CA')` (`1 250,00 $`, with `ext-intl`; the amount and the code without it), `equals()`, and `toArray()` and JSON as the protocol's value. `Money` fields serialize it.
- **Lists.** Without columns, a list shows `daterange` and `money` fields with the other simple types. On an attribute with the `AsDateRange` cast, `EloquentRepository` reads a `date-range` filter as an overlap: the items whose range starts on or before `to` and ends on or after `from`, where a null bound never limits it and items without a range are left out; a sort on it sorts by its start column. A sort on an `AsMoney` attribute sorts by its amount column, whatever the currency. `calendar()` takes a `daterange` field: the calendar asks for each month through that same filter.
- **Sessions** of a performance or a screening are a `Repeater` (decision 0053), not a type: `Sessions::make('sessions')` has `dates`, a `DateRange` with `time()` (`dates(fn (DateRange $dates) => …)` to change it), `venues()` adds `venue`, a `Relation` to the resource of the venues, and `append()` other fields. They are kept as the value of the repeater (a JSON column with the `array` cast), or in rows of their own by the project's repository.
- **Amounts kept in major units.** `php artisan panneau:convert-money` turns the floats, decimals and numeric strings of `panneau.money.columns` into minor units, in the currency of `panneau.money.currency` (`--currency` to use another):

  ```php
  // config/panneau.php
  'money' => [
      'currency' => 'CAD',
      'columns' => ['products.price:price_cents', 'orders.data:total'],
  ],
  ```

  - `table.column:target_column`, when the table has that column: the amount of `column` goes to `target_column`, an integer column the project added by migration for the `AsMoney` cast. A row whose target is filled is never overwritten.
  - `table.column:path.in.json` otherwise: the number at that key of the JSON column becomes a money value, `{ "amount", "currency" }`, in place.
  - What doesn't read as a number stays as it is, and `-v` lists it by its shape (`99,99 $`, an `a` for each letter), never its value. `--dry-run` counts and lists without writing. Rows are read and written by their `id`, without their models; running it again changes nothing.
- **From 1.x.** `--check` lists two date fields of a file named as a start and an end (`starts_at` and `ends_at`), and `Number` fields with a currency in their strings (a `$` suffix): neither change is automatic (see the [migration guide](migration.md)).

### Addresses

The `address` type of [panneau-js decision 0054](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0054-addresses.md): a postal address as one value, with its parts.

```php
Address::make('address')
    ->country('CA')                       // the country of a new address
    ->countries(['CA', 'US'])             // and preferred(), as Country
    ->requiredParts(['street', 'city', 'region', 'postalCode', 'country'])
    ->point(),                            // a map, and the point in the value
Text::make('zip')->postalCode(field: 'country'), // the rule on a text field: a code, or a field
```

- **Value:** `{ "street", "unit", "city", "region", "postalCode", "country" }`, and `{ "lat", "lng" }` in `point` with `point()`. Empty parts are left out. A street, a unit, a city, a region or a postal code fills an address (`Address::FILLING_PARTS`): without one, it is `null`, or its point alone (`{ "point" }`). A country alone, which the admin may set by itself, is no address. `region` is a value of the `region` type for the country (`QC`), `country` a value of the `country` type.
- **Checks.** Once the address is filled, as clients count it, the parts of `requiredParts()` are required (`street`, `city` and `country` without it; not the country when the field has `country()`, which saving sets), every part is a string of 255 characters at most, the country is one of `countries()` (or any code), and the postal code is one of the address's country. Errors are keyed by the path of the part: `address.postalCode`, `details.venue.street`, `address.fr.city` for a localized address. The point is checked whenever it is there. `required()` requires a filled address: a country or a point alone fails it.
- **The `postalCode` rule** (`Panneau\Rules\PostalCode`) is a portable rule: `{ "rule": "postalCode", "value": "CA" }`, or `{ "field": "country" }` for another field of the same object, as for `before`. It checks the format of Canada (`H2X 1Y4`, without the letters Canada Post doesn't use) and of the United States (`12345`, `12345-6789`) once normalized; postal codes of other countries, or without a country, pass. `PostalCode::normalize()` and `PostalCode::isValid()` do the same in a project.
- **Saving** trims the parts, puts the country in capitals (or `country()` on an address that has parts and no country), the regions of Canada and the United States as their codes (`Québec`, `Quebec` and `qc` are `QC`), and the postal code in the format of its country (`h2x1y4` is `H2X 1Y4`, `123456789` is `12345-6789`; elsewhere in capitals, spaces collapsed). `Address::normalize()` does the same.
- **Lists.** Without columns, a list shows an `address` field with the other simple types, on one line.
- **Model.** The attribute needs a JSON column with the `array` cast.
- **Addresses kept in separate columns.** `php artisan panneau:convert-addresses` writes the address of each row in a JSON column, from the columns of `panneau.addresses.columns`: the target `table.column`, and the source of each part, a column of the same table, or `column:path.in.json` for a key of a JSON column (`city:fr` for a localized column, `data:address.street` for a group of 1.x).

  ```php
  // config/panneau.php
  'addresses' => [
      'country' => 'CA', // the country of an address without one
      'columns' => [
          'venues.address' => ['street' => 'address', 'unit' => 'address2', 'city' => 'city',
              'region' => 'province', 'postalCode' => 'postal_code', 'country' => 'country'],
      ],
  ],
  ```

  - Parts are normalized as saving does. Countries become codes from their names in English or French (`Canada`, `États-Unis`) with `ext-intl`, and two-letter codes are put in capitals. Regions of Canada and the United States become their codes from their names in English or French, in any case, with or without accents (`Québec`, `Qc`, `Qué.`).
  - A country, a region of Canada or the United States, or a postal code it can't convert stays as typed, and `-v` lists it by its shape (`a9a 9a`), never its value: its form refuses it until it is fixed. `--dry-run` counts and lists without writing, `--country` replaces `panneau.addresses.country`, and arguments convert some targets only.
  - A row whose target already holds an address is left alone: running it again changes nothing, and keeps the addresses edited since. The target can be one of the sources (a text column of streets that becomes the JSON column of addresses). Rows are read and written by their `id`, without their models.

### Links

The `link` type of [panneau-js decision 0055](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0055-links.md): the link of a button, a card or a menu item.

```php
Link::make('cta')
    ->pages(PagesResource::class, EventsResource::class) // the resources of page links
    ->resolvePageUrlUsing(fn (object $page, string $resource): ?string => route($resource.'.show', $page))
    ->types(['page', 'url', 'email', 'file', ['id' => 'anchor', 'label' => 'Anchor', 'pattern' => '^#[\w-]+$']])
    ->libraries([DocumentsResource::class])              // the libraries of file links
    ->newTab('external')                                 // choose (the default), external or never
    ->linkLabel('required'),                             // optional (the default), required or none
```

- **Value:** `{ "type", "url", "page", "file", "label", "newTab" }`, or `null`. `url` is the `href`: `https://…` for `url` links, `mailto:…` for `email` links, `tel:+…` (E.164) for `tel` links, the text typed for a site's types. `label` is a string per locale (`{ "fr": "…" }`), or a string in a `localized()` link, whose every part is per locale already. `newTab` is always there.
- **Types.** `types()` lists built-in names and a site's types (an `id`, a `label`, an optional `pattern` and `placeholder`). Without it: `url`, `email` and `tel`, after `page` with `pages()`. A `page` type without `pages()` is left out, as clients do. `label()` is the label of the field: the setting of link labels is `linkLabel()`.
- **Checks**, keyed by the path of each part: `link.type` must be one of the types; `link.url` is required for the types with a URL, an `http://` or `https://` URL for `url` links, `mailto:` and an email address (and a query, if any) for `email` links, `tel:` and a number in E.164 for `tel` links, the `pattern` of a site's type; `link.page` must be an item of the resources of `pages()`, within their scope, and `link.file` a media of the libraries, checked as a `media` field checks its media; `link.label` is required (a locale at least) with `linkLabel('required')`. Labels are 255 characters at most.
- **Requests** send a page as its id, or `{ "id", "resource" }` when the field has several resources, and a file as a `media` field's value. Saving keeps the parts of the type only: the repository receives `{ "type", "page": { "id", "resource" } }` for a page, the `url` of other types, the filled labels, and `newTab`, false with `newTab('never')`, and with `newTab('external')` true for `url` and `file` links and a site's types that point to another site (`https://`, `//`), false for the others. The `url` of `page` and `file` links is ignored.
- **Items** give a page as a reference, `{ "id", "resource", "label" }`, with the URL of `resolvePageUrlUsing()` in `url` (left out without it: a site resolves the page itself), and a file as a `MediaReference`, with the URL of its file in `url`, for the fields at the top of a resource. In groups and blocks, values give what the repository received, as `media` values do.
- **Model.** The attribute needs a JSON column with the `array` cast.

### File

A `file` field holds files that belong to the item, outside the media library: a PDF, an attachment. Panneau stores them on a disk and keeps their stored value in the item.

```php
File::make('terms')
    ->accept(['application/pdf'])   // portable rules, in the definition
    ->maxSize(10_000_000)
    ->disk('s3')                    // default: the app's default disk
    ->directory('terms')            // default: files
    ->visibility('private'),        // default: private
File::make('attachments')->multiple()->max(5),
```

- **Definition.** `multiple`, `accept` and the `accept` and `maxSize` rules, like `media`. The disk, directory and visibility stay on the server.
- **Requests** send, for each file:
  - `{ "upload": "upl_…" }`, a completed direct upload of the user, to add or replace it;
  - a file in `multipart/form-data`, when uploads are not direct;
  - the stored file the item gave, to keep it;
  - `null` to remove it (an empty list with `multiple()`).
- **Validation.** An upload that is unknown, still open, expired or another user's is a `422` on the value's path (`terms`, `attachments.1`, `sections.2.file`). `accept` and `maxSize` are checked on the MIME type and size that the upload recorded when it completed, or that were detected for a file in the request, never on what the client sends next to them. A stored file sent back must be one of the item's own: it is found by its URL (without the query string, which changes for temporary URLs) or its name. Its path, disk or URL from the client are never used.
- **Saving.** Each new file is copied from the upload (`readStream()`) or the request to `{directory}/{ulid}/{name}` on the field's disk, with a safe version of its name. The repository receives its stored value, `{ "disk", "path", "name", "mime", "size" }`, or a list of them. Files that the save replaced or removed are deleted once the repository saved the item; new files are deleted if the save fails (a `creating` or `updating` hook, or the repository, throws). Deleting the item leaves its files, since a soft-deleted item can come back: delete them in the `deleted()` hook if needed, with `File::storedFiles()`.
- **Items** give the protocol's `StoredFile`, `{ "name", "url", "mime", "size" }`. A public file gets the disk's `url()`, a private file a `temporaryUrl()` that lives `panneau.files.url_ttl` seconds (3600 by default), and `url` is null when the disk gives neither.
- **Model.** With `EloquentRepository`, the attribute needs a JSON column (`$table->json('terms')->nullable()`) with the `array` cast. In a `Group` or a `Repeater`, the file's stored value is part of the group's or the blocks' JSON.
- **Not localized.** A file field can't be `localized()`.
- **In an action**, a `File` parameter is validated the same way, but nothing is stored: `$values->upload('file')` gives the upload.

### Slug

Laravel side of [panneau-js decision 0032](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0032-field-checks-and-slugs.md): a slug that checks its value with the backend as it is typed. See [decisions/0011](decisions/0011-slug-checks.md).

```php
Slug::make('slug')->from('title')->localized()
    ->unique(fn (Builder $query, array $values) => $query->where('parent_id', $values['parent'] ?? null))
    ->url(fn (string $slug, ?string $locale, array $values, ?string $id): ?string => $this->pageUrl($slug, $locale, $values))
    ->disabledWhen(fn (array $values, ?string $id): bool => ($values['handle'] ?? null) === 'home'),
```

- **`unique(?Closure $scope = null)`.** No other item of the resource, within its scope, has the slug: per locale for a localized slug, excluding the item being updated. Saving refuses a taken slug with a `422` on its path (`slug`, `slug.fr`). The closure narrows the lookup further, such as per parent.
- **`url(Closure $url)`** gives the URL of a slug, or null. The field shows it as a link, and the URL without the slug at its end as the prefix in front of the slug (`https://example.com/fr/vacances/` for `…/vacances/ete`). When the URL doesn't end with the slug as typed (it encodes it, or adds a suffix), there is no prefix.
- **`disabledWhen(Closure $disabled)`:** the field doesn't apply to some items, such as the home page's slug. The check answers `disabled`, and the field is read-only. It doesn't change saving.
- **`check`.** Any of the three puts `"check": true` in the field's definition. A slug without them is the plain `slug` type, with a fixed `settings.prefix` if any.
- **The form's values.** The closures receive the form's values, keyed by field name, as they would be saved: relations as ids (the related item's public id). They are the values the client sent, over those of the item being edited, since an update sends only what changed, and they are not validated yet: use them to compute a URL or a scope, nothing else.
- **The check endpoint.** `POST {path}/api/resources/{resource}/fields/{field}/check`, with `{ "value", "locale", "id", "values" }`, answers `{ "available", "suggestion", "prefix", "url", "disabled" }`:
  - `available` is false when another item has the value; an empty value is always available. `suggestion` is then the value with `-2`, `-3`…, the first that is free among `Slug::SUGGESTIONS` (50), or null.
  - It runs with the permission to create (`id` null), or to update the item `id`, which is found within the resource's scope (`404` otherwise). A field that doesn't exist at the top of the resource, or has no check, is a `404`. A request without `value`, or with a locale that isn't one of `panneau.locales`, is a `422`. A localized slug without `locale` is checked in the default locale.
  - It isn't authoritative: saving validates uniqueness again.
  - The endpoint is generic: any field type can override `hasCheck()` and `check()` of `Field`. Only fields at the top of a resource check: the definition leaves `check` out of fields in groups, blocks and actions.
- **The lookup.** `EloquentRepository` looks the value up with `slugQuery()`: the column named after the field, or the locale's key of a JSON column (`slug->fr`) for a localized slug, within the scope, deleted items included (a unique index counts them too). The closure of `unique()` receives that query. Override `slugQuery()` to look elsewhere, such as a table of translations. Other repositories implement `ChecksSlugs`:

  ```php
  interface ChecksSlugs
  {
      public function slugExists(string $field, string $value, ?string $locale, ?string $exceptId, array $scope, array $values): bool;
  }
  ```

  They scope the lookup with `$values` themselves: the closure of `unique()` is for Eloquent queries. `unique()` on a repository that has neither throws a `LogicException`.
- **Only at the top of a resource.** A slug in a group or a block isn't unique within the resource: `unique()` is ignored there.

## Actions

Laravel side of the [shared decision on actions](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0017-actions.md). An action is a class listed in the resource's `actions()`.

```php
final class PublishPages extends Action
{
    public function scope(): array
    {
        return [ActionScope::Item, ActionScope::Selection];
    }

    public function fields(): array
    {
        return [Boolean::make('notify')->label(__('Notify subscribers'))];
    }

    public function confirm(): ?Confirm
    {
        return Confirm::make(__('Publish :count pages?'));
    }

    public function authorize(User $user, object $page): bool
    {
        return $user->can('publish', $page);
    }

    public function handle(Collection $pages, ActionValues $values, ActionResult $result): ActionResult // Entities in Folklore projects
    {
        foreach ($pages as $page) {
            if ($page->title === null) {
                $result->failed($page, __('The title is required.'));

                continue;
            }

            $page->publish(notify: $values->boolean('notify'));
            $result->succeeded($page);
        }

        return $result->refresh(ActionRefresh::Items);
    }
}
```

- **Declaration.** `label()`, `icon()`, `scope()`, `placement()`, `destructive()`, `confirm()`, `fields()` and `layout()` are serialized into the definition. The action id is derived from the class name (`publish-pages`), or set with `id()`.
- **Kinds.** Server actions extend `Action`. `LinkAction::make('preview')->url(...)` and `ClientAction::make('copy-link')->component('copy-link')` cover the two other kinds, with no PHP logic.
- **Built-in item actions** ([panneau-js decision 0040](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0040-row-links-and-open-mode.md)). `show`, `edit`, `duplicate` and `delete` exist on every resource without being declared. The app shows each one on the items whose ability allows it: `view`, `update`, `duplicate`, `delete`; a missing or `false` ability hides it. It labels them and gives them their icons.
  - **Abilities.** Every item has the four: `view`, `update` and `delete` from the policy; `duplicate` from the policy's `duplicate()`, or else `create` on the resource, and `false` unless the resource opts in with `duplication()` (below), when the repository isn't `Duplicable`, or for a media library, whose copies would share the media's file (`ResourceRegistry::duplicable()`).
  - **`itemActions()`** says which ones the app shows, in which order: `['edit', 'delete']`, `[]` for none. Null, the default, shows the four, and is left out of the definition. An unknown id throws. **It only hides buttons:** the API still answers what the policy allows. To forbid an action, the policy refuses it, which hides the button too:

    ```php
    public function itemActions(): array
    {
        return ['edit', 'delete']; // the list of orders shows no show or duplicate
    }

    // OrderPolicy: no order is deleted, from the list or through the API
    public function delete(User $user, Order $order): bool
    {
        return false;
    }
    ```

  - **`delete`** is the action `delete`, which answers for every resource (`POST /resources/{resource}/actions/delete`, with selections and dry runs), authorized by the policy's `delete`. It isn't in the definition: declare a `DeleteAction` (or a subclass) to change its label or confirmation, and the declared one replaces it.
  - **`duplicate`** is off until the resource opts in: only it knows what a copy needs (its blocks, its media, its children), and a copy that leaves them out is worse than none. `duplication()` returns what a copy includes; the repository has to be `Duplicable` too (`EloquentRepository` is).

    ```php
    public function duplication(): ?Duplication
    {
        return Duplication::make()->relations(['tags', 'sections', 'sections.media']);
    }

    public function duplicating(object $page, object $copy, Request $request): void
    {
        // Before the copy is saved: a draft of the current user
        $copy->title = $page->title.' (copy)';
        $copy->published = false;
        $copy->published_at = null;
        $copy->author_id = $request->user()->getAuthIdentifier();
    }
    ```

    - `POST /resources/{resource}/{id}/duplicate` answers `201` with the copy and its `ETag` (an app that mounts `resourceRoutes(only: [...])` without `duplicate` has no duplicate: the ability is false); `403` when the item's `duplicate` ability is false (it needs `view` on the item, since the copy is answered in full), `404` outside the scope, and `409` when the copy still hits a unique value of the table (nothing is saved).
    - **What is copied.** `EloquentRepository` copies the attributes with `replicate()` (so a `BelongsTo` keeps its parent), and the relations of `relations()`, which it checks when the definition is built (a relation the model doesn't have, or without a return type, throws there): `BelongsToMany` and `MorphToMany` attach the copy to the same items with the same pivot values (an order, a handle); `HasOne`, `HasMany`, `MorphOne` and `MorphMany` copy the children, and `sections.media` the relations of the copied children. Another kind of relation throws.
    - **What is never copied by default:** relations that `relations()` doesn't list, the files of media (a copy points to the same media), and what isn't a relation of the model. The `creating`/`created`/`saved` hooks of the resource and the preparation of the fields (`Form::prepare()`) don't run.
    - **Unique slugs** (`Slug::unique()`) get a free value: the item's with `-copy`, then `-copy-2`, `-copy-3`…, in each locale.
    - **Hooks.** `duplicating($item, $copy, $request)` changes the copy before it is saved (the invariants of `creating()`, such as the author, apply to copies there), and `duplicated($item, $copy, $request)` runs once it is saved with its relations. With Eloquent, all of it runs in one transaction: an exception rolls the copy back.
  - **`show` and `edit`** are links the app resolves to its routes. `EditAction` and `ShowAction` replace them: `EditAction::make()->url('/editor/{id}')`, `->route(...)`, `->label(...)`, `->placement(...)`, sent as `{ "id": "edit", "kind": "link", "scope": ["item"], … }`. A `show` with a URL would replace the link to the panel, and disappear with `open('edit')`: link to the item on the site with a link of its own, `LinkAction::make('site')->url(...)`. They don't run: `POST /resources/{resource}/actions/edit` is a `404`. The ids `show` and `edit` are reserved for links: a server or client action that has one throws when the actions are resolved.
- **What opens an item.** `Resource::open()` returns `'panel'` (the default, left out of the definition): the title of an item opens its panel, next to the list. With `'edit'` (sent as `"open": "edit"`), it opens its form, the show route redirects there, and the app leaves out `show`. An item that can't be updated still opens in its panel. Another value throws when the definition is built. Use it when the panel adds nothing to the form (orders, users).
- **Selection:**
  - The controller resolves the selection through the resource's repository and `scope()`: it applies the ids, or the list filters and search of a query selection, then the exclusions.
  - A selection never reaches items outside the resource's scope.
- **Authorization:**
  - `authorize()` runs for every item. By default it calls the Policy method named after the action (`publishPages`), and falls back to `update`.
  - Items that are not allowed are reported as `skipped` with the reason `forbidden`.
  - `authorizeResource()` decides whether the action appears at all.
  - Item abilities serialize the allowed actions.
- **Dry runs.** An action that returns `true` from `supportsDryRun()` declares `dryRun` in the definition. A request with `"dryRun": true` then resolves the selection and checks each item like a run, and answers the summary of the items it would run on and those it would skip, without calling `handle()` and without validating the parameters. Lists use it to say how many items of a query selection will be skipped ([panneau-js#44](https://github.com/folkloreinc/panneau-js/issues/44)).
  - It is opt-in, since a dry run only checks abilities: opt in when `handle()` doesn't skip or fail items for other reasons. `DeleteAction` does.
  - A dry run of an action that doesn't support them is a `400`, and nothing runs.
- **Validation.** Parameters are validated like resource fields, with portable rules and server-only rules. `handle()` receives only validated values.
- **Results.** `ActionResult` collects:
  - succeeded, failed and skipped items;
  - a message;
  - what to refresh;
  - an optional redirect;
  - an optional download, served through a temporary signed URL.
- **Queued actions** (**not built yet**: today a selection above the limit is refused, see [Action requests](#http-layer)):
  - An action that implements `ShouldQueue` runs on the queue. It does the same when the selection is larger than `config('panneau.actions.sync_limit')` (500 by default).
  - Items are processed in chunks (`chunkSize()`), through a job batch. This needs a repository with the `ChunkedQuery` capability.
  - Progress and results are stored in a `panneau_action_runs` table, and `ActionRunFinished` is dispatched at the end.
  - An action that can't be queued refuses selections above the limit with a `422`.
- **Built-in actions:** the item actions above (`delete`, which answers dry runs, and `duplicate`, for the resources that opt in); the others are **not built yet**.
  - `RestoreAction`: for repositories that are `Restorable`.
  - `ExportAction`: CSV, or XLSX with an optional dependency; queued.
  - `ImportAction`: file field and column mapping; validates each row with the resource's rules; queued.

  Resources enable them in `actions()`, and can extend them.

## Theme

`config('panneau.theme')` is sent as the definition's `theme`, the protocol's `DefinitionTheme` ([panneau-js decision 0024](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0024-theme-model.md)). Its keys are the protocol's, as they appear in JSON:

```php
'theme' => [
    'extends' => 'brand',      // themes registered by JS plugins, by id: a string or a list
    'colorScheme' => 'auto',   // light, dark or auto
    'density' => 'comfortable', // compact, comfortable, touch or auto
    'palette' => ['blue' => '#0055ff'],
    'tokens' => ['panneau-color-accent' => 'var(--panneau-palette-blue)'],
    'dark' => ['panneau-color-surface' => '#101014'],
    'tones' => ['brand' => '#0055ff'],
    'components' => ['button' => ['variants' => ['pill' => ['panneau-button-radius' => '999px']]]],
    'densities' => ['compact' => ['panneau-control-height' => '1.75rem']],
    'fonts' => [['family' => 'Geist', 'src' => "url(/fonts/geist.woff2) format('woff2')"]],
],
```

- A theme defined in JS (`defineTheme()` of `@panneau/ui`) goes in the `themes` of a plugin, by id, in the project's entry, and the config names it in `extends`. The config only holds what changes per project or environment, such as the accent color.
- `resolveTheme()` of `@panneau/core` merges the extended themes, in order, then the config's own values.
- Null values and empty arrays are left out, so that an empty map isn't sent as a JSON list. Without `panneau.theme`, or when nothing is left, the definition has no `theme` and the app uses the default theme of `@panneau/ui`.
- The package sends the theme as configured. Token names and values, tone and variant names, and fonts follow the restrictions of the protocol's schema (no `{`, `}`, `;` or `<` in values, for example): a theme that breaks them makes the definition invalid.
- The tests check a definition with a theme against `definition.schema.json`.

## Pages and menus

The app shell of `@panneau/app` reads the definition's `pages` and `menus` (#33). A page is a component that a JS plugin registers (or a built-in page), at `{basePath}/pages/{id}`; the page `home` is the home page of the app. A menu is a list of entries; `main` is the sidebar.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Panneau\Navigation\MenuItem;
use Panneau\Navigation\Page;

// In a service provider
Panneau::pages([
    Page::make('reports', 'reports')        // id, and the component a JS plugin registers
        ->label(__('Reports'))
        ->props(['period' => 'month']),
    Page::make('post-stats', 'post-stats')->params(['id']), // {basePath}/pages/post-stats/{id}
]);

Panneau::menu('main', fn (?Authenticatable $user): array => [
    MenuItem::resources(),                                     // every resource, in their order
    MenuItem::resource(PostsResource::class)->label(__('Posts'))->icon('file-text'), // a class or an id
    MenuItem::page('reports'),
    MenuItem::link(__('Site'), url('/'))->external(),
    MenuItem::separator(),
    MenuItem::group(__('Content'), [MenuItem::resource('tags')])->icon('folder'),
    ...($user?->is_admin ? [MenuItem::resource(UsersResource::class)] : []), // by role
]);
```

- **Where they are declared.** In `config/panneau.php`, as `pages` (a map of id to page) and `menus` (a map of name to entries), in the protocol's shape, or in a service provider with `Panneau::pages()` and `Panneau::menu()`, which take the builders or the same arrays. `Panneau::pages()` adds pages; `Panneau::menu()` sets a whole menu. **The service provider wins:** a page declared there replaces the page of the config with the same id, and a menu the menu of the same name; a later call wins over an earlier one.
- **Closures.** `Panneau::pages()` and `Panneau::menu()` also take a closure that returns the pages or the entries. It is called each time the definition is built, through the container (`$app->call()`), with the user (or null) as `$user`: labels are translated in the request's locale, and a menu can depend on the user, such as their role, or on the current context, with a parameter of type `Panneau\Context\Context` (see [Current context](#current-context)). Arrays are evaluated once, when they are declared: `__()` in an array of a service provider gives the boot locale, and the config can't translate.
- **Pages.** `Page::make($id, $component)`, with `label()`, `path()` (a custom path), `props()` (the props of the component) and `params()` (the names of the path segments after the page, which the component gets as `params`). `can($ability)` shows the page only to the users who have a Gate ability (`'stats.view'`), or a closure that gets the user: for the others, the page has `"abilities": { "view": false }`, and the app leaves it out of menus and shows a forbidden page on its route ([panneau-js decision 0046](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0046-abilities-in-the-ui.md)). It only hides the page: the endpoints its component calls check the user themselves.
- **Entries.** `MenuItem::resources()`, `resource()`, `page()`, `link()`, `separator()` and `group()`, with `label()`, `icon()` (a Lucide name) and `external()`. A resource or a page without a label gets its own in the app. `MenuItem::make($type, [...])` makes an entry of a type a plugin provides, which is sent as it is.
- **Checked when the definition is built.** Resource classes become resource ids. An entry that names a resource that isn't registered, a page that isn't declared, or a page with `params` (a page about one thing, which the app leaves out of menus), and a link without a URL, throw a `LogicException`: the app would leave them out without a word.
- **Left out when there are none**, so that definitions stay as they were. Without `menus.main`, the app lists every resource, then the pages without params. A menu declared empty is sent empty. Null values and empty `props` and `params` are left out, so that an empty map isn't sent as a JSON list.
- **Not filtered.** The app hides the resources the user can't list (`abilities.viewAny`). Pages and other entries are not filtered by abilities: a closure that gets the user builds a menu for their role.
- The tests check a definition with pages and menus against `definition.schema.json`, and the conformance app has a page and a `main` menu.

## Current context

The current context is what the admin works on, such as a client, then a contest ([panneau-js decision 0045](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0045-current-context.md), #147, [decisions/0015](decisions/0015-current-context.md)). The request can fix a value, such as the client of its domain, and the user picks the others in the app. The app sends the values it has with every request, in a `Panneau-Context` header (`contest=12`), and keeps them in its URL (`/panneau/contest/12/resources/entries`). The API checks each value for the user, resources apply them in their scope, and the definition is built for them.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Panneau\Context\Context;
use Panneau\Context\ContextParam;

// In a service provider
Panneau::context(fn (?Authenticatable $user): array => [
    ContextParam::make('client')
        ->label(__('Client'))
        ->allLabel(__('All clients'))        // where the user picks it: the label of no value
        ->resource(ClientsResource::class)   // its items, within its scope, that the user can view
        // On a client's domain, the client of the domain: the user can't change it
        ->fixed(fn (Request $request): ?string => Client::idForDomain($request->getHost())),
    ContextParam::make('contest')
        ->label(__('Contest'))
        ->required()
        ->resource(ContestsResource::class)  // whose scope reads the client
        ->default(fn (?Authenticatable $user): ?string => $user?->last_contest_id), // without a value
    ContextParam::make('season')
        ->options(['2026' => __('Season 2026'), '2025' => __('Season 2025')]), // inline choices
]);

final class EntriesResource extends Resource
{
    public function scope(Request $request): array
    {
        return array_filter([
            'client_id' => $this->context()->get('client'),         // null: all clients
            'contest_id' => $this->context()->getOrFail('contest'), // a 403 context problem without one
        ], fn (mixed $value): bool => $value !== null);
    }
}

Panneau::menu('main', fn (Context $context): array => [
    MenuItem::resource(EntriesResource::class)->label(__('Entries of :contest', ['contest' => $context->label('contest')])),
]);
```

- **Params.** `ContextParam::make($name)`: a name that is an identifier, and not a first segment of the client routes (`resources`, `pages`, `media`, `search`, `account`, `auth`). `label()` (defaults to the name), `required()` (the app shows a page to pick a value until it has one), `allLabel()` for an optional param. The params are in order, and each one depends on those before it. `Panneau::context()` takes the params or a closure called through the container with `$user`; a later call replaces the params.
- **Choices**, one source per param:
  - `options()`: inline choices, sent in the definition. Labels keyed by value, or a list of `['value' => …, 'label' => …, 'description' => …]` (or `ContextOption`), or a closure that returns them.
  - `resource()`: the items of a registered resource, by class or id. A value is found with the repository's `find()`, within the resource's `scope()`, and the user must be allowed to `view` it. The choices are the first items of its `query()`, searched like its list (or by its title), filtered by `view`. The label is the item's title (`itemTitle()`), and `Context::item()` gives the item.
  - `search()` and `find()`, for anything else: `search` gets `$q` and returns choices, `find` gets `$value` and returns its label (or a choice), or null when the user can't use it.
- **Closures** (`options`, `search`, `find`, `default`, and `fixed`, which also gets `$request`) are called through the container with `$user` and the `Context` of the params before as `$context` (or any parameter of type `Context`). While a param's resource is queried, the current `Context` holds the values before it only: the resource's `scope()` reads what the param depends on.
- **Checked on every request.** The `ResolveContext` middleware (alias `panneau.context`) is on every route of the API, after the authentication, and in the groups of `Panneau::resourceRoutes()` and `uploadRoutes()`. It reads the header, checks each value in order, and fills `Panneau\Context\Context`. A value the user can't use is a `403` problem of type `urn:panneau:problem:context`, with its `param`. Names that aren't params are ignored. Responses get `Vary: Panneau-Context`, problems too.
- **Fixed by the request.** `fixed()` gives the value that the request fixes, such as the client of its domain, or null when it fixes none (the user then picks it, as any other param). It is checked like any other value: the user must be allowed it, or the request is the `403` context problem. The definition marks the param `fixed`, and the app shows it without a field and keeps it out of its path and of the header. Another value in the header is the `403` context problem of the param: a link from another admin, or a header, can't widen it.
- **Defaults.** A param without a value gets its `default()`, a value or null, checked like any other: a default the user can't use is left out. For a required param, that is the user's only contest, or the last one.
- **`Context`** is bound once per request: inject it, or call `$this->context()` in a resource. `get($name)` and `getOrFail($name)` (the `403` problem without a value, for a resource that needs one), `has()`, `all()`, `label()`, and `item()` for a param of a resource. It is empty without a context, and outside the routes of Panneau.
- **The definition** has `context.params`: each param's `name`, `label`, `required`, `fixed`, `allLabel`, inline `options`, and `value` and `valueLabel`, what it was built for. Menus and pages declared as closures get the current context (`Context $context`), so they can depend on it.
- **`GET /context/{param}/options?q=…`** (`panneau.api.context.options`) gives the first 20 choices that match `q`, for the values of the params before it. An unknown param is a `404`.
- **The page of the app** reads the values in the prefix of its path, and inlines the definition built for them, with the defaults. A value the user can't use leaves the definition out of the boot config: the app fetches it, and handles the `403`. `Panneau::bootConfig($user, path: 'contest/12/resources/entries')` does the same for a project's own page.
- **Without a context**, nothing changes: the middleware does nothing, the definition has no `context`, and responses don't vary.
- **Not filtered by roles.** The choices are what the resources, policies and closures allow.

## HTTP layer

- **Routes are opt-in.** Nothing is registered until the project calls `Panneau::routes()`, usually in `routes/panneau.php`. Every route gets the middleware from `config('panneau.middleware')`, `['web', 'panneau.auth']` by default. See [decisions/0002](decisions/0002-secure-by-default.md).
  - The API is under `{panneau.path}/api` (`/panneau/api` by default), with routes named `panneau.api.*`.
  - The routes can be mounted under a prefix with its own parameter, such as `tenants/{tenant}`: the controllers read their parameters by name.
  - `panneau.auth` requires a user on the guard of `panneau.auth.guard`, and the `viewPanneau` gate. Without that gate, nobody gets in.
  - `panneau.context` (`ResolveContext`) checks the values of `Panneau-Context`, on every route of the API but login and logout (see [Current context](#current-context)).
  - Every other path under `{panneau.path}` serves the page of the app (route `panneau.app`), since the app routes on the client. The route is registered last: the project's own routes under that path go before `Panneau::routes()`. The project's routes registered before outside that path (with the prefix of the group that calls `Panneau::routes()`, such as `tenants/{tenant}`) are moved after Panneau's, so that a page route of the site, such as `/{page}`, doesn't catch `/panneau` when the site's routes are loaded first (a `RouteServiceProvider` listed before the provider that loads `routes/panneau.php`). Routes loaded after Panneau's never shadow them. `Panneau::routes(app: false)` registers only the API.
  - **Parts of the API.** A project with its own admin front can mount only what it uses, under its own group: `Panneau::uploadRoutes()` (nothing when uploads are not enabled) and `Panneau::resourceRoutes($resources, only: [...])`, where `$resources` are ids (null for all) and `only` picks among `index`, `store`, `show`, `update`, `destroy`, `duplicate`, `actions` and `check` (the field check endpoint). The routes keep their `panneau.api.*` names, after the group's name, and render their errors as problems. They don't get `panneau.middleware`: the group authenticates. They get `ResolveContext`, after the group's middleware. Add `RenderProblems` first to the group's middleware so that its errors are problems too.

    ```php
    Route::prefix('workspaces/{workspace}')
        ->middleware([RenderProblems::class, 'web', 'auth', ResolveWorkspace::class])
        ->group(function (): void {
            Panneau::uploadRoutes();
            Panneau::resourceRoutes(['media'], only: ['store']);
        });
    ```
- **The page of the app** follows the entry contract of `@panneau/app` ([panneau-js decision 0019](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0019-app-entry.md)):
  - a Blade view (`panneau::app`, or `panneau.app.view`) with the project's Vite entry (`panneau.app.entry`) and an empty `<div id="panneau">`;
  - `<meta property="csp-nonce">` with the CSP nonce of Vite, when the request has one (`Vite::useCspNonce()`, which also puts it on the scripts of the entry), for `@panneau/app` and React Aria. The view gets it as `$nonce`;
  - the boot config in `<script type="application/json" id="panneau-boot">`: `apiBaseUrl`, `basePath`, `locale`, `auth` (the `auth` of the definition, for the login page of the app), `loginUrl`, and the definition for the user unless `panneau.app.inline_definition` is off, so the app renders without fetching it. `Panneau::bootConfig()` builds it for projects that render the app in their own page. The tests check it against the protocol's `boot.schema.json`;
  - in the `external` auth mode, guests are redirected to `panneau.auth.login_url`, with the intended URL kept in the session. In the `panneau` mode, they get the page without the definition, and the app shows its login page at the URL they asked for ([panneau-js decision 0037](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0037-login-page-token-and-logout.md)). The API answers them with a `401` in both modes.
- **Generic controllers implement the protocol:**
  - definition, resources, field checks, actions and action runs;
  - uploads (media are resources) and search;
  - **not built yet:** auth, account, dashboard widgets, preview.
- **Authorization.** Every operation calls the Policy of the item, or of `itemClass()` for `viewAny` and `create`. Actions authorize each item (see [Actions](#actions)). A resource without a policy is denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Validation.** Requests validate against the field rules, and only `validated()` data reaches the repository.
- **Query.** Filters, sorts and search only accept what the resource's list declares. The query string can't reach arbitrary columns.
- **Responses:**
  - Errors on Panneau routes are rendered as `application/problem+json`, including those of the other middleware. Other routes keep the app's error rendering.
  - Single items send an `ETag`.
  - Updates check `If-Match` and answer `412` on a version conflict, with the current item. Without `If-Match`, the update goes through.
- **Action requests:**
  - A selection of ids reports missing and forbidden items as `skipped`. When it has a single id, they are a `404` or `403` instead.
  - A query selection goes through the list's filters and search, and the resource's scope.
  - Selections above `panneau.actions.sync_limit` are refused with a `422` until queued actions exist.
  - A dry run (`"dryRun": true`) keeps the selection's rules, limit and errors, skips the validation of `values`, and changes nothing.
  - Parameter errors are keyed by their path inside `values` (`note`, not `values.note`), like the errors of a form.
- **Contract tests.** Feature tests validate every response against the protocol's OpenAPI document.

## Auth

See [decisions/0005](decisions/0005-auth.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0012-authentication.md).

- **`panneau` mode.** The package registers its own auth routes, on the guard set in `config('panneau.auth.guard')`, which can be a separate `panneau` guard and user provider. It must be a session guard.
  - **Built:** the login page of the app ([panneau-js decision 0037](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0037-login-page-token-and-logout.md)), served to guests at any path of the app; `POST /auth/login` with an email and a password (`remember` optional), throttled to 5 failed attempts a minute per email and IP address; `POST /auth/logout` (`204`), which invalidates the session. A user that the `viewPanneau` gate refuses can't log in, and gets the error of wrong credentials. Logout skips the gate, so that a refused user can still log out.
  - **Not built yet** (slice "App and content"): password reset, 2FA, passkeys and email verification with Fortify actions, OAuth providers with Socialite, the login with a token (`auth.token`).
- **`external` mode.** The site keeps its own login. Panneau only checks the session on the site's guard and the `viewPanneau` gate.
  - Guests are sent to `panneau.auth.login_url`.
  - `panneau.auth.logout_url` is where the browser goes to log out (`auth.logoutUrl`), the site's own logout. It has to accept a `GET` navigation: Laravel's default logout route is a `POST`, so the site needs a route that logs out and redirects. Without it, the app has no "Log out" button.
- **The user of the definition.** `user` has the id of `getAuthIdentifier()`, and the `name` and `email` of the user: its attributes (an Eloquent model, `GenericUser`), or else its public methods `name()` and `email()`, for users that are entities (laravel-folklore's `Folklore\Entities\User`). Without a name, the email is the name.
- **Isolation.** Panneau never changes the site's Fortify configuration, views or guards.
- **Roles module (optional).** See [decisions/0014](decisions/0014-roles-module.md). Off by default (`panneau.roles.enabled`): projects with their own policies or roles leave it off.
  - **Roles are config.** `panneau.roles.roles` defines each role: a `label`, `permissions`, and `owned` for a role that reaches only the items its users own. A permission is `{resource}.{ability}` (`contests.viewAny`, `contests.update`, `contests.export` for an action), `{resource}.*`, `*`, or a name listed in `panneau.roles.permissions` (`stats.view`), for pages, fields and the project's own checks. `panneau.roles.grantable` lists the permissions that can be given to one user on top of their roles.
  - **Users get roles and grants** from a `RoleStore`: `DatabaseRoleStore` by default, on `panneau_role_user` and `panneau_permission_user` (a morph type and an id, so any user model works; the migrations load when the module is on, and publish with the `panneau-roles-migrations` tag). The `Roles` and `Grants` fields (`Panneau\Roles\Fields`) assign them on the project's resource of users: they are kept by the store, not on the item, and only users with `roles.assign` can change them. `php artisan panneau:role {user} {roles*} --grant=…` sets them from the command line, for the first admin.
  - **The Gate answers the permissions of Panneau.** A `Gate::before()` allows a permission the user has through a role or a grant, and answers nothing otherwise, nor for abilities that aren't Panneau's: `$user->can('contests.export')` works anywhere, and the project's own gates still apply. Without a `viewPanneau` gate of the project, users with a role can open the admin.
  - **`ResourcePolicy`** is the base of the policy of a resource that uses roles: it names its resource (`protected string $resource = 'contests'`) and checks `viewAny`, `view`, `create`, `update`, `delete`, `deleteAny` and `runAction()` against the permissions. `panneau:install` generates such a policy when the module is on.
  - **Ownership.** A user whose roles are all `owned` reaches only the items they own. A resource says which with `OwnedResource::ownerScope($user)`, a scope (`['client_id' => $user->client_id]`, or a list of accepted values), which joins `repositoryScope()`, so lists, items, updates, deletes, duplicates, actions, checks and the search stay within it: an item outside it is a `404`. `ResourcePolicy` checks it again on the item, and refuses an owned user on a resource that doesn't implement `OwnedResource` (one owned users see in full returns `[]`).

## Uploads

See [decisions/0007](decisions/0007-direct-uploads.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0025-presigned-uploads.md). The browser sends files to an S3-compatible disk itself, in parts for large ones, with URLs that Panneau signs. An upload is not a media: values reference it.

```php
// config/panneau.php
'uploads' => [
    'enabled' => true,
    'disk' => 's3',            // a disk with the s3 driver, and league/flysystem-aws-s3-v3
    'path' => 'panneau-uploads',
    'url_ttl' => 900,          // seconds a signed URL lives
    'lifetime' => 86400,       // seconds an upload lives
    'max_size' => null,        // bytes, checked at completion
],

// AppServiceProvider
Gate::define('uploadPanneauFiles', fn (User $user): bool => $user->isEditor());

// routes/console.php
Schedule::command('panneau:prune-uploads')->hourly();
```

- **Routes.** `POST uploads/sign` signs the requests of Uppy 6, and `POST uploads/{upload}/complete` checks the file on the disk and answers the upload. The definition announces `"uploads": { "mode": "presigned" }`. Without `enabled`, neither exists.
- **Signing.** The first request of a file starts an upload, with a key Panneau chooses (`{path}/upl_…`). Later requests are signed only for the user's open uploads, by key, and for the multipart upload recorded first.
- **Authorization.** The `uploadPanneauFiles` gate. Without it, uploads are denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Values.** A [`File` field](#file) takes `{ "upload": "upl_…" }` and copies the upload's file to its own disk when the item is saved. A [library on the mediatheque](#on-laravel-mediatheque) ingests it instead. In an action, `$values->upload('file')` gives the completed upload that the parameter references, for the same user, or null. `$upload->readStream()` reads its file, and `$upload->disk` and `$upload->path` locate it.
- **Lifetime.** Uploads are temporary. `panneau:prune-uploads` deletes the expired ones, completed or not, with their files, and aborts their multipart uploads. Copy or move a file you keep: file fields copy it.
- **Table.** `panneau_uploads`. The package loads its migration when uploads are enabled; `vendor:publish --tag=panneau-migrations` copies it into the project instead.
- **Storage.** The `UploadStorage` contract signs, stats and deletes. `S3UploadStorage` is bound by default; bind another implementation for a different storage.

## Media

See [decisions/0009](decisions/0009-media-libraries.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0026-media-libraries.md). A media library is a resource of kind `media`: listing, search, filters, scope, policies, actions, trash and restore are those of resources. A `media` field references the media of a library.

### Libraries

A library extends `MediaResource`. Panneau adds its `file` field, the original, before the fields of `fields()`; `file()` configures it. Its items get a read-only `media`, the protocol's `MediaInfo`, and its list a `type` filter.

```php
final class ImagesResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image'];                        // the scope, and the default `accept` of the file
    }

    protected function file(File $file): File
    {
        return $file->maxSize(50_000_000);       // required by default; never in fields()
    }

    public function fields(): array
    {
        return [Text::make('name'), Text::make('alt')->localized(), Text::make('credit')];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->search(['name'])->layouts(['gallery', 'table']); // the type filter is added
    }

    public function scope(Request $request): array
    {
        return ['workspace_id' => $request->user()->workspace_id];    // given to the media it makes
    }
}
```

- **Making a media** is `POST /resources/{library}` with `{ "file": { "upload": "upl_…" } }`, or the file in `multipart/form-data`. Replacing its file is a `PATCH` with a new `file` (Eloquent libraries only, see below). `media` is computed by the backend: what the client sends there is ignored.
- **Media types.** `mediaTypes()` lists the types of the library: `image`, `video`, `audio`, `document`, `font`, `other`, or a type of the mediatheque. Empty, the default: every type. They are part of the scope (under the `type` key), so lists, items and `media` fields only reach media of those types, and a file of another type is refused with a `422` on `file`. They also give the file's default `accept`; an explicit `accept()` replaces it, and should stay within the types. The file's default `maxSize` is `maxFileSize()`: none on a `MediaResource`, the mediatheque's limit on a `MediathequeResource`; an explicit `maxSize()` replaces it.
- **The `type` filter** (`filter[type]=image`, or a list) is added before the filters of `list()`, unless it declares one. Its options are the library's types. A type the library doesn't hold gives no media.
- **Scope.** `scope()` works as for any resource, and its values are set on the media the library makes: a tenant column, or a column that splits a table into several libraries.
- **Labels.** In `media` values, a media's `label` is its `itemTitle` (the first text field by default), in the current locale for a localized field, or else the name of its file.
- **Reserved names.** `fields()` can't declare `file` or `media`.

### On `laravel-mediatheque`

`MediathequeResource` is a library on [`folklore/laravel-mediatheque`](https://github.com/folkloreinc/laravel-mediatheque) 2 (`^2.0@alpha`, 2.0.0-alpha.8 or later, suggested, not required). Its repository, `MediathequeRepository`, works with the model of `mediatheque.models.media`.

- **Ingest.** A direct upload is ingested with `Mediatheque::ingest(Source::disk($upload->disk, $upload->path, $name), name: …, attributes: $scope)`: the mediatheque copies it on the storage, detects its type from its content, and processes it in its queue. A file of the request is ingested the same way. A file the mediatheque refuses, or of a type outside the library, is a `422` on `file`, and leaves neither a row nor a file.
- **Size limit.** The file's `maxSize` defaults to the largest file the mediatheque ingests for the library's types: the largest of their `max_size` (`mediatheque.types.{type}.max_size`, or `ingest.max_size`), and none when one of them has no limit. So the app refuses a file before sending it, instead of the mediatheque after. An explicit `maxSize()` in `file()` wins.
- **Fields** are attributes of the media: `name`, and the columns the application adds to the media table. The values other than `file` and `name` are set after the ingest, like `EloquentRepository` does.
- **`media`** comes from the mediatheque: `type`, `status` (`pending` until it is probed and processed), `error` for a failed media (`{ code, message }`, from its latest failed run or the rendition of it that failed), the original's MIME type and size, `url` (the main rendition, or the original; temporary for a private media), `thumbnailUrl`, `variants` (the main renditions, such as `h264`), `width`, `height`, `duration`, `metadata`, and `transform`, the media's own transform (`$media->transform()`, `laravel-mediatheque` 2.0.0-alpha.8 or later).
- **Thumbnails.** With [`folklore/laravel-image`](https://github.com/folkloreinc/laravel-image) 2, the thumbnail of an image, or of a video's poster, is an image URL of its `media` resolver: a signed URL 480 pixels wide, or the preset of `thumbnailPreset()`. Without it, or before the mediatheque knows the image's checksum, it is the URL of the original image, or of the poster or first thumbnail of other types.
- **Replacing the file.** A new `file` (a direct upload, or a file of the request) replaces the media's original with `Mediatheque::replaceOriginal()` (`laravel-mediatheque` 2.0.0-alpha.5 or later). The media keeps its id, so its references keep working; its renditions are made again, and its `media.status` is `pending` until then. A file the mediatheque refuses, of another type than the media's, or outside the library's types, is a `422` on `file`, and the media and its files stay as they were. The original sent back as the item gave it, or left out, is kept.
- **Ingest and replace options.** Extend `MediathequeRepository` and override `ingest()` to choose the visibility or the pipeline of the media, or `replace()` to choose the options of a new original or to allow a file of another type (`replaceOriginal($media, $source, allowTypeChange: true)`); return the subclass from the library's `repository()`.
- **Policies** are registered on the media model: `Gate::policy(config('mediatheque.models.media'), MediaPolicy::class)`.
- **Ids** are the mediatheque's uids (`med_…`).
- **Tests.** A library stores its media in the database, and `Mediatheque::fake()` keeps them in memory: a library refuses the fake's media with an explanation. Test a library with a real ingest, on a fake disk, without its jobs, and check the media's row, its file, and the mediatheque's events and jobs:

  ```php
  Storage::fake(config('mediatheque.source'));
  Bus::fake();
  Event::fake([MediaCreated::class]);

  $this->postJson('/panneau/api/resources/media', ['file' => ['upload' => $upload], 'name' => 'Portrait'])
      ->assertCreated();

  Event::assertDispatched(MediaCreated::class, fn (MediaCreated $event) => $event->media->name() === 'Portrait');
  Bus::assertDispatched(ProbeMedia::class);
  $this->assertDatabaseHas('medias', ['name' => 'Portrait', 'type' => 'image']);
  ```

### Two libraries on one backend

Libraries on the same model, or on the mediatheque, keep apart by their types and their scope:

```php
final class ImagesResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image'];
    }

    public function fields(): array
    {
        return [Text::make('name'), Text::make('alt')->localized()];
    }
}

final class PressKitResource extends MediathequeResource
{
    public function mediaTypes(): array
    {
        return ['image', 'document'];
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }

    public function scope(Request $request): array
    {
        return ['library' => 'press'];            // a column of the media table, set on its media
    }
}
```

An image of the press kit is also an image of `ImagesResource`, which isn't scoped by `library`; scope both libraries by the column to keep them apart.

### Without the mediatheque

A library on an Eloquent model returns it from `model()`, and gets an `EloquentMediaRepository`. The `file` field stores the file like any [`File` field](#file); the repository fills the `type` column from its MIME type.

```php
final class DocumentsResource extends MediaResource
{
    public function model(): string
    {
        return Asset::class;                     // casts: 'file' => 'array'
    }

    public function mediaTypes(): array
    {
        return ['document'];
    }

    protected function file(File $file): File
    {
        return $file->disk('s3')->directory('documents');   // private by default
    }

    public function fields(): array
    {
        return [Text::make('name')];
    }
}

// The migration
Schema::create('assets', function (Blueprint $table) {
    $table->id();
    $table->json('file')->nullable();
    $table->string('type')->index();
    $table->string('name')->nullable();
    $table->timestamps();
    $table->softDeletes();                       // optional: deleted media can be restored
});
```

- **`media`** is computed from the stored file: `type` (the `type` column, or the MIME type's), `mime`, `size`, `url` (the disk's URL, or a temporary URL for a private file), and `thumbnailUrl`, the same URL for an image. There is no status: the media is ready. Its `transform` is the value of the library's `transform` field, when it declares one (a `Json` field on a JSON column, for example).
- **Replacing the file** with a `PATCH` stores the new file and deletes the old one once the item is saved, like a `File` field. Its type follows the new file: keep `accept` within the library's types.
- Override `media()` to add what the project knows, such as image sizes from its own image service.

### The `media` field

```php
Media::make('cover')->resource(ImagesResource::class)->accept(['image/png', 'image/jpeg']),
Media::make('gallery')->resource(ImagesResource::class)->multiple()->max(12),
Media::make('attachment')->resource(DocumentsResource::class)->transforms(false), // a column of ids
Media::make('files')->resource([ImagesResource::class, DocumentsResource::class]), // several libraries
Media::make('image'),                                                              // every library
```

- **The libraries** are named by `resource()`, by class or id: one, or several (a list, or several arguments) in the order the picker's switcher shows them ([panneau-js decision 0027](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0027-media-picker.md)). The definition gives the id of one library, or the list of ids. Without `resource()`, the field allows every library of the definition. `libraries()` gives the libraries as named, and `ResourceRegistry::mediaLibraries()` the ones a field allows.
- **Requests** send an id (`"med_01H…"`), or `{ "id", "resource", "transform" }` with the media's library and the edits of the image for this use (the protocol's `MediaTransform`: `crop`, `rotate`, `flipX`, `flipY`, `focalPoint`). The references the item gave can be sent back.
  - **One library:** an id, or `{ "id", "transform" }`. A `resource` naming that library is accepted.
  - **Several libraries:** ids are unique only within their library, so each media needs a `resource` among the field's libraries. A missing or unknown `resource` is a `422` on the value's path (`The cover allows several libraries: name the library.`).
- **Validation.** Ids are found through the repository of their library, within its scope and its types: an unknown id, a deleted media, or one of another tenant or of another type is a `422` on the value's path (`cover`, `gallery.2`, `blocks.1.image`). An invalid transform is a `422` too. `accept` and `maxSize` are checked on the MIME type and size of the media found, never on what the client sends.
- **Values.** The repository receives `{ "id": "…" }`, with the media's `resource` when the field allows several libraries, and a `transform` when the value has one (only the protocol's properties), or a list of them with `multiple()`. With Eloquent, the attribute needs a JSON column with the `array` cast. With `transforms(false)`, it receives the id only, or a list of ids, and transforms are ignored: the field can't keep the library of each media, so it allows one library only. Naming several throws a `LogicException`, and so does validating such a field without `resource()` when the definition has several libraries.
- **Items** give `MediaReference`s: `{ "id", "resource", "label", "media", "transform" }`, where `resource` is the media's library, and `label` and `media` come from it. Each value is resolved in its own library: the `resource` it keeps, or the field's library when it allows one only. A value saved without a `resource` in a field that now allows several libraries is not resolved: migrate such values to `{ "id", "resource" }`. A media that left its library keeps its id, library and transform. The media of a page of items are found in one query per library. In groups and blocks, values give `{ "id", "resource", "transform" }` as they were saved.
- **From a relation.** An attribute or `resolveUsing()` that gives media items (models of the mediatheque through `HasMedias`, for example) is turned into references too, of the first of the field's libraries whose items are of their class (`itemClass()`, the model by default), or of its only library. An object of none of them gives its `id` only.
- **Image edits** are rendered by `folklore/laravel-image` 2 URLs, from the media and the transform.

### The `image` field

`Image` extends `Media`: a `media` field for images, with the image editor of `@panneau/media` ([panneau-js decision 0031](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0031-image-editor-and-image-field.md)). Its libraries, requests, values and references are those of `Media`, and its `accept` defaults to `image/*`.

```php
Image::make('cover')
    ->resource(ImagesResource::class)
    ->ratios(['16:9', '4:3'])                       // the ratios the editor suggests
    ->rotate(false)                                 // no rotation and flips (true by default)
    ->focalPoint()                                  // the focal point (true by default)
    ->transformBase('choose')                       // media (the default), original, or choose
    ->crop(['16:9', '1:1'], required: true),        // the crop rule
```

- **Options.** `ratios()`, `rotate()`, `focalPoint()` and `transformBase()` are given in the definition when they are set. A ratio is `width:height` in whole numbers, and an invalid one, or another base, throws an `InvalidArgumentException`.
- **The `crop` rule** is a portable rule of `media` and `image` fields alike: `crop(ratios: [...], required: true)` gives `{ "rule": "crop", "value": { "ratios": [...], "required": true } }`. The server checks the transform of each media, as it checks `accept` and `maxSize`, with a `422` on the entry's path (`cover`, `gallery.2`):
  - with `required`, each media needs a crop for this use: an id alone, or `{ "id" }`, has none;
  - with `ratios`, a crop must have one of them once rendered: a quarter turn swaps its width and its height. A crop has a ratio when its height is within one pixel of its width divided by the ratio, so a 1601×900 crop is 16:9.

  A field with `transforms(false)` keeps no crop, so it can't have the rule (`LogicException`).
- **Transforms** (`Panneau\Media\MediaTransform`) are the protocol's `MediaTransform`, checked and normalized before they reach the repository:
  - `crop`, `rotate`, `flipX`, `flipY` and `focalPoint`, as above. A rotation of 0 and false flips are dropped;
  - `base`: without it, a transform for one use starts from the original file. With `media`, it applies over the media's own transform, and its crop and focal point are in pixels of the media's version. `original` is kept as sent;
  - `baseTransform`: with `base: "media"`, the media's own transform it was made on, itself a valid transform without `base` or `baseTransform` (one level deep);
  - other keys, which editor tools add, are kept as they are, through save and read. Renderers ignore the ones they don't know.

  An invalid `crop`, `rotate`, flip, `focalPoint`, `base` or `baseTransform` is a `422`.
- **The media's own transform.** `MediaResource::media()` gives `transform`, from `mediaTransform()`: the library's `transform` field, or the mediatheque's `transform` on a `MediathequeResource`. References carry it in their `media`, so the editor draws a transform made over it, and sees that it changed since `baseTransform`. A value that isn't a valid transform gives none. An Eloquent library that declares a `transform` field edits it with a `PATCH` of that field; editing it in the app (the picker's details, an editor on a library's pages) is not decided yet (decision 0031).

## Search and dashboard

See [decisions/0010](decisions/0010-global-search.md) and the [shared decision](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0029-go-to-palette.md).

```php
// config/panneau.php
'search' => [
    'enabled' => true,   // false: no route, and "search": { "enabled": false } in the definition
    'rate_limit' => 60,  // searches per minute for a user (or an IP address); null or 0: no limit
],
```

- **Global search.** `GET {path}/api/search?q=&limit=&resources=` searches the resources in the global search, in the order of the definition, and answers the ones with results: at most `limit` items each (5 by default, 20 at most), and 20 in all. Once 20 items are found, the other resources are not searched.
  - **Which resources.** `search` of a resource's `features()` when set, or else whether its list has a search (`$list->search([...])`). Its repository must be `Searchable`, and the user must be allowed `viewAny`, as for its list. The definition's `features.search` says the same.
  - **Like the list.** Each resource is searched through its repository's `query()`: the list's search fields and default sort, the resource's scope, page 1. Items are serialized as in the list, with their abilities, and `total` is the total of the page.
  - **Parameters.** They are read like `readSearchQuery()` of `@panneau/protocol`: `q` trimmed, from 2 to 255 characters; `limit` an integer from 1 to 20; `resources` ids of resources the user can search. An invalid parameter is a `422` problem, with `errors` keyed by parameter. A resource the user can't list is refused like one that doesn't exist.
  - **Rate limit.** The route uses the `panneau-search` rate limiter: `panneau.search.rate_limit` searches per minute for a user, or an IP address without one. Over it, a `429` problem with `Retry-After`. A project changes the limit, or its key, by defining its own `panneau-search` limiter with `RateLimiter::for()`. The limiter counts in the app's cache.
- **`EloquentRepository`** searches with a `LIKE` on the search fields. A search with no search fields finds nothing.
- **Laravel Scout** (optional, `laravel/scout` 11). When the model uses `Laravel\Scout\Searchable`, `EloquentRepository` searches through Scout instead, for the global search, the list's `q` and the query selections of actions:
  - the engine searches the model's searchable data (`toSearchableArray()`), not the list's search fields;
  - the rest of the query is Scout's `query()` callback: the scope, ids, exclusions, filters and sorts, applied by the database as without Scout;
  - the `database` and `collection` engines run that callback before paginating, so pages and totals are exact. Engines with their own index (Meilisearch, Algolia, Typesense) run it only on the items of the page they found. The scope is also given to the engine as `where` clauses (`whereIn` for a list), so **the scope's columns must be filterable attributes of the index**. The callback still keeps the items outside the scope out. Filters, ids and exclusions can make such pages shorter, and the order is the engine's;
  - override `scoutSearch()` to give the engine more of the query, and `searchesWithScout()` to keep `LIKE` queries for a searchable model;
  - in a list paged with cursors, Scout pages by number behind the cursors, since it has none.
- **Dashboard widgets** (**not built yet**, 5.x) are classes that declare their type and options, and return their data. They are registered per role in the service provider.

## laravel-folklore

- **Entities and repositories stay.** They remain the way Folklore projects access data. Only their contracts stop extending Panneau's: `Entity` keeps its own `id()`, and `Entities` no longer extends `Panneau\Contracts\Repository`.
- **Plugin**, built in `laravel-folklore` 2, in PHP only ([its decision 0018](https://github.com/folkloreinc/laravel-folklore/blob/v2.x/docs/decisions/0018-panneau-plugin-php-only.md)); **not built yet**. It registers:
  - the `ResourceRepository` adapter for `Folklore\Contracts\Repositories\Entities`, on its typed query, which implements `ChecksSlugs` for unique slugs;
  - the `Page`, `Pages`, `Blocks` and `PageSlug` fields, PHP classes that configure the built-in types: `Page` and `Pages` are relations to the pages resource, `Blocks` a repeater whose types come from the block type registry, and `PageSlug` a `Slug` with `unique()`, whose `url()` the pages module gives (the page's URL, which follows its parent), and `disabledWhen()` for the home page ([panneau-js decision 0032](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0032-field-checks-and-slugs.md));
  - what its Entities need to reference media of a library;
  - the policies' base class for Entity contracts, if useful.

  No JS: projects stop copying the `PageSlug` components.

## Supported versions

5.x requires PHP 8.3+ and Laravel 12+ (see [decisions/0006](decisions/0006-supported-versions.md)). The CI tests every supported PHP × Laravel combination.
