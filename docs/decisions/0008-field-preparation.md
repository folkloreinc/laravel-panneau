# 0008: Fields prepare their values before the repository saves them

- Status: proposed
- Date: 2026-10-21
- Issue: [#81](https://github.com/folkloreinc/laravel-panneau/issues/81)

## Context

Until now, a field turned a request value into a repository value during validation (`extract()`): a `Relation` keeps the ids. Every field's value lived in the item.

A `file` field ([0007](0007-direct-uploads.md)) keeps something outside the item: it copies the file of an upload to its disk, and stores where it put it. That work can't happen during validation, which must not write anything, and it needs what validation doesn't have: what to undo if the save fails, and what to delete once it succeeds. Repositories can't do it either: they receive plain values and never assume a field type ([0003](0003-resources.md)).

## Decision

- **`Field::prepare($value, $path, SaveContext $context)`** turns a validated value into the value the repository receives. `ResourceController` calls it through `Form::prepare()` after validation and before the `creating` and `updating` hooks. The default keeps the value; a localized field prepares each locale (`prepareValue()`).
- **`SaveContext`** describes one save: the mode, the item before the save, and the uploads of the user. A field registers callbacks with it:
    - `afterSave()` runs once the repository saved the item. The save can't be undone then: an exception is reported, and the other callbacks still run.
    - `afterFailure()` runs when a hook or the repository throws after the values were prepared.
    - `shared()` keeps a value for the whole save, such as what a field collects across the blocks of a repeater.
- **Nested fields.** `Group` and `Repeater` prepare their fields with their dot paths (`sections.2.file`). Groups and blocks are saved whole, so their fields without a value get `prepareAbsent($path)`: their previous value is gone. In a repeater, the path has `*` for the block's index (`sections.*.file`), since blocks move.
- **Validation resolves uploads too.** `ValidationContext` gets the same upload resolver as the save, so rules check a referenced upload (it exists, its MIME type and size) before anything is written.

## Consequences

- Fields from other packages can keep data outside the item (files, external records) without a custom controller.
- Actions don't prepare their values: an action receives them validated, and reads uploads with `ActionValues::upload()`.
- A field that writes in `prepare()` registers its cleanup with `afterFailure()`, and deletes what it replaced in `afterSave()`, never before.
