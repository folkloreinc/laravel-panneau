# 0031: A search finds every word, in any field, whatever its case and its accents

- Status: proposed
- Date: 2026-11-25
- Issue: [#292](https://github.com/folkloreinc/laravel-panneau/issues/292)

## Context

`EloquentRepository` searched the `q` of a list with one `LIKE '%q%'` per search field, on the whole string. So `black dog` only found the items with that exact phrase in one field: not the item titled "The dog" and described as "black", nor "dog, black". And the comparison was the one of each column: `etude` didn't find "Étude" on PostgreSQL or SQLite, case counted on SQLite beyond ASCII, and a JSON path (`title->fr`) was compared in binary on MySQL, where even the case counted.

That search is the one of every list, of the pickers, of the query selections of actions, of the aggregates, of the global search and of the `find_items` tool of the agents: all read the repository's `query()`. People type a few words, in any order, without accents.

Laravel Scout is already supported for the models that use it ([0010](0010-global-search.md)): a search engine answers these needs and more. But most resources have no engine, and a project shouldn't have to install one, or a database extension, for the search of a list to be the one people expect.

## Decision

- **The search is split into words, on whitespace.** Every word must be found (`AND`), each in any of the search fields (`OR`). `black dog` finds the item whose title has "dog" and whose description has "black". A word is still found anywhere in a value, as before (`%word%`), so `bla do` finds it too. There is no phrase syntax (quotes are characters like others).
- **At most 8 words** (`SearchTerms::MAX_WORDS`), without duplicates. The words over that are ignored rather than refused: `q` is already cut at 255 characters, and the bound keeps the query short (a condition per word and field).
- **Case and accents are ignored, both ways, with nothing to install.** `searchedColumn()` gives the SQL a field is compared with, for the driver of the connection:
  - **MySQL and MariaDB:** `convert(<column> using utf8mb4) collate utf8mb4_unicode_ci`. Both servers have that collation, whatever their version and the collation of the column; the conversion makes it valid for columns in another character set, and for the text of a JSON path.
  - **PostgreSQL:** `translate(<column>::text, '<accented letters>', '<base letters>') ilike <word without those accents>`. It needs no extension: `unaccent` isn't available to every project (managed databases, rights of the role), and a search that fails where it isn't installed is worse than one with limits. `lower()` isn't used: on a database in the `C` locale it only folds ASCII letters, and PHP would then fold more than the database does.
  - **SQLite:** the same letters through nested `replace()`, under its `LIKE`, which ignores the case of ASCII letters.
  - **Other drivers:** the column as it is, compared the way its collation does.
- **The letters are a fixed list** (`SearchTerms::letters()`): the Latin letters that are a base letter with a diacritic, in both cases (the letters with a diacritic of Latin-1 and Latin Extended-A: `é`, `ç`, `ñ`, `ü`, `š`, `ő`…). Each of them equals its base letter under `utf8mb4_unicode_ci`, and a test checks it on every database, so the three databases answer the same for them. On PostgreSQL and SQLite, the word loses the same accents in PHP before it is compared.
- **`%`, `_` and `!` stay literal,** escaped with `!` in each word.
- **Scout doesn't change.** A model that uses `Laravel\Scout\Searchable` is still searched by its engine, which gets `q` as it was typed.
- **A project that needs more overrides `searchedColumn()`,** or uses Scout. On PostgreSQL, `searchedColumn()` can return `unaccent(<column>)`, or a generated column kept without accents with a trigram index; the words arrive without the accents of the list.

## Consequences

- **The results of a search of several words change.** It no longer finds the exact phrase only, but every item with all the words, anywhere in the search fields. More items are found, and an item that had the phrase is still found. A search of one word finds what it found, and the items that differ by accents (and, on a MySQL JSON path, by case).
- **Limits of the list of letters,** on PostgreSQL and SQLite:
  - Letters that aren't a base letter and a diacritic keep their own identity: `ø`, `đ`, `ł`, `ß`, and the ligatures `œ` and `æ` (`coeur` doesn't find "cœur": a letter can't stand for two).
  - Accented letters outside the list (Vietnamese, Greek, Cyrillic…) are compared with their accents. Without accents, the case of non-ASCII letters is the database's: `ILIKE` folds it on a PostgreSQL database with a UTF-8 locale, not in the `C` locale, and SQLite never does.
  - A letter written as a base letter followed by a combining accent (decomposed Unicode) isn't in the list. Text typed in a browser is composed.
  - MySQL and MariaDB ignore more than the list, since their collation covers every script; the three databases only agree on the list.
- **No index serves the search,** which was already true of `LIKE '%q%'`: every row of the query is read, now once per word and field until one matches. On SQLite, the nested `replace()` cost about 35 µs per value compared on a development machine (20,000 short rows, two fields, one word: 1.4 s). SQLite is the database of tests and small sites; a large table belongs behind Scout, on any database.
- **Collations of the project don't count anymore** on MySQL: a column in `utf8mb4_bin` to make a search exact is now searched without case or accents. `searchedColumn()` returns the column as it is to get that back.
- **The protocol doesn't change:** `q` is a string, and what a search finds is the server's.
- The tests run on SQLite with the suite, and on MySQL, MariaDB and PostgreSQL in CI (the `search` job), on a database given by `PANNEAU_TEST_MYSQL_URL`, `PANNEAU_TEST_MARIADB_URL` and `PANNEAU_TEST_PGSQL_URL`.
