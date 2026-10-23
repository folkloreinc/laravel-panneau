# 0009: Media libraries are resources

- Status: proposed
- Date: 2026-10-21
- Shared decision: [panneau-js 0026](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0026-media-libraries.md) ([folkloreinc/panneau-js#88](https://github.com/folkloreinc/panneau-js/pull/88))

## Context

The architecture planned a `MediaRepository` contract and `/media` endpoints, with an adapter for `laravel-mediatheque`. They were never built. The shared decision 0026 replaces them: a media library is a resource of kind `media`, with a `file` field and a read-only `media` on its items, and a `media` field is a relation to a library. The Laravel side has to fit that into resources backed by repositories ([0003](0003-resources.md)), with or without `laravel-mediatheque`, and without trusting what the client says about a media.

## Decision

- **`MediaResource`** is the base of a library. It serializes `kind: media`, and adds what the protocol requires, whatever the project overrides:
    - the `file` field, before the fields of `fields()`, which must not declare it (nor `media`). The project configures it with `file(File $file)`. Panneau reads the fields of a resource through `allFields()`, which adds it;
    - the `type` filter, before the filters of `list()` (through `makeList()`). It accepts any type name, so that asking for a type the library doesn't hold gives no media;
    - the `media` of each item, from `media($item)`, which the serializer adds after the values, even when the client asks for some fields only.
- **Media types are part of the scope.** `mediaTypes()` lists the types of a library (empty: every type). They are added to `scope()` under the `type` key by `repositoryScope()`, which the controllers give the repository, so a project that overrides `scope()` keeps them. They also give the default `accept` of the file. A list in a scope is a constraint: `EloquentRepository` applies it with `whereIn`, and doesn't fill it in on create. A media of another type is refused when it is made, with a `422` on `file`.
- **Two backends.**
    - *Eloquent*: a `MediaResource` with `model()` gets an `EloquentMediaRepository`. The model keeps the stored file of the `File` field in a JSON `file` column, and its type in a `type` column that the repository fills from the MIME type. `media()` is computed from the stored file: type, MIME type, size, URL (temporary for a private file), and the same URL as thumbnail for an image.
    - *`laravel-mediatheque` 2*: a `MediathequeResource` uses `MediathequeRepository`, an `EloquentRepository` of the model of `mediatheque.models.media`. Its `file` field (`MediathequeFile`) doesn't store the file: it gives the repository a `Source`, `Source::disk()` for a direct upload, which the repository ingests with `Mediatheque::ingest()`, with the scalar values of the scope as attributes. The ingest runs in a transaction with the rest of the save, and its original is deleted if the save fails. `media()` comes from the mediatheque: status, main rendition, thumbnail (a `laravel-image` URL when it is installed), renditions and metadata. Panneau works without the mediatheque: only these classes use it, and their tests are skipped without it.
- **The `media` field** references a library with `resource()`, optional when there is one library. Its values are found through the library's repository, within its `repositoryScope()`, so an unknown id, one of another tenant or of another type is a `422`. `accept` and `maxSize` are checked on the media found, never on the client's values. The repository receives `{ "id" }`, with the `transform` of the value when it has one, in a JSON column; with `transforms(false)`, the ids only, for a column of ids. Items give `MediaReference`s; the media of a page are found in one query per library.

## Consequences

- A library is configured like any resource: fields, list, policies, scope, actions. Two libraries can share a model or the mediatheque, scoped by type or by a column of the project.
- The file of a media on the mediatheque is replaced with the mediatheque's `replaceOriginal()`, since `laravel-mediatheque` 2.0.0-alpha.5 ([#93](https://github.com/folkloreinc/laravel-panneau/issues/93)). Before it, the `file` field of a `MediathequeResource` was `createOnly()`.
- In groups and blocks, `media` values give `{ "id", "transform" }`, without `label` and `media`, as relations in groups give no references.
