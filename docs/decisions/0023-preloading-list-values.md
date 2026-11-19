# 0023: Lists read the fields they ask for, and repositories preload what those fields read

- Status: proposed
- Date: 2026-11-19
- Issue: [#243](https://github.com/folkloreinc/laravel-panneau/issues/243), [folkloreinc/panneau-js#347](https://github.com/folkloreinc/panneau-js/issues/347)

## Context

A list serialized whole items, and loaded what their values read one item at a time. `ItemSerializer` called `toItem()`, which resolves every field, and only then kept the `fields` the client asked for. Nothing was eager loaded: each `Relation` field of each item lazy-loaded its relation, and the page of each `link` field was found with its own `find()`. Only the top-level `media` fields were found at once (`MediaReferences::preload()`). A list of 20 posts with a relation, a link to a page and a media made 64 queries; with 5 posts, 19.

Eager loading is Eloquent's, and resources are backed by repositories, not models ([0003](0003-resources.md)): a resource can't call `with()`, and the serializer can't either.

## Decision

- **An item asked for some fields reads only those.** With `fields`, an item has the fields asked for, with its title (`itemTitle()`) and its image (`itemImage()`), which every list layout shows; `id`, `version`, `abilities` and `type` are always there. The serializer resolves only the values of those fields, and not those the user can't see. Without `fields`, an item has every field, as before.
- **Unless the resource or the type has its own `toItem()`.** Its `toItem()` may compute a value from any other, so it gets every value, and the fields that weren't asked for are left out after, as before. `ResourceType::toItem()` gets every value of the resource for the same reason. The serializer finds out with reflection, once per class.
- **A repository capability preloads what fields read.** `Panneau\Contracts\Repositories\PreloadsFields::preloadFields(array $items, array $fields)` gets the items of a page and the fields about to be read from them, before they are serialized. It's optional, like the other capabilities: without it, each item loads what its fields read, as before. Fields are passed as `Field` objects, so a repository decides what it can load ahead from their names and types, without the resource knowing.
- **`EloquentRepository` eager loads relations named after fields.** For each field that names a relation of the model (a public method with a relation as its return type, as `fill()` finds them), it calls `loadMissing()` on the page of models. A field read with `resolveUsing()` is left out (`Field::isComputed()`): the closure may read anything, and loading a relation of the same name would be a guess. `newQuery()` doesn't change: the page is loaded first, then its relations, which also works for pages of cursors and of Scout searches.
- **The pages of links are found like media.** `MediaReferences`, which already finds the media of a page of items in one query per library, finds any item of a resource the same way (`queue()`, `loadQueued()`, `item()`), within the resource's scope for the request. `ItemSerializer::preload()` queues the media of `media` fields, the files of `link` fields and the pages of `link` fields of the fields being read, then loads them: one query (and the count of its page) for each library or resource. A page is found with `query()` and `withIds()`, as media are, rather than with `find()`.

## Consequences

- The same list makes 8 queries, with 5 posts or 20: the page and its count, the authors, the tags, then the pages and the media, each a page of their repository with its count. `?fields=title` makes neither the author, tag nor page queries.
- A list that asks for its columns (folkloreinc/panneau-js#347) no longer serializes rich text and blocks it doesn't show.
- Adding the title and the image to an item asked for some fields adds values the client didn't ask for. Clients ignore them, and they're what a gallery or a picker shows anyway.
- A custom repository gets the same savings by implementing `PreloadsFields`. A repository adapter (`Panneau::repositoryAdapter()`) implements it itself when the repository it wraps can preload.
- Relations of related items (the title of an author that is itself a relation, the parent of a page) and the media inside blocks aren't preloaded yet: they still load item by item.
- The global search serializes every field of its items and doesn't call `preload()` yet.
