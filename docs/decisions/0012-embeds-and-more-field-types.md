# 0012: Embeds come from an allowlist of oEmbed providers, and saving resolves them again

- Status: proposed
- Date: 2026-10-23
- Issue: [#100](https://github.com/folkloreinc/laravel-panneau/issues/100), [folkloreinc/panneau-js#111](https://github.com/folkloreinc/panneau-js/issues/111)

## Context

[panneau-js decision 0033](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0033-more-field-types.md) adds seven field types: `tel`, `range`, `duration`, `json`, `tags`, `embed` and `location`. Most of them are a value and its rules. `embed` is not: the backend resolves its URL with the provider's oEmbed (`GET /embeds?url=`), and its value carries HTML that sites render.

Resolving a URL means a request from the server. An oEmbed discovery would fetch the URL itself to find its endpoint: any user of the panel could make the server request any address, internal ones included. And the HTML of a value would come from the client, which any user who can edit an item controls.

## Decision

- **One class per type:** `Tel`, `Range`, `Duration`, `Json`, `Tags`, `Embed` and `Location`, with their settings as methods (`step()`, `suffix()`, `precision()`, `options()`, `allowCustom()`, `zoom()`, `center()`, `tiles()`). The server checks what the client checks: the default ends of a range (0 and 100), whole positive seconds, objects and arrays for `json`, strings and options for tags, the latitude and longitude of a location. The portable `json` rule becomes Laravel's `array`.
- **An `EmbedResolver` contract.** The default, `OEmbedResolver`, only resolves URLs that match the schemes of a provider in `panneau.embeds.providers` (YouTube, Vimeo, SoundCloud and Spotify by default), and only requests that provider's endpoint. There is no discovery: the URL itself is never fetched. Answers are cached `panneau.embeds.cache_ttl` seconds. A project binds another resolver to use an embed service.
- **`GET /embeds`** answers the resolver's value, or a `422` on `url` when it gives none. It is behind Panneau's middleware, and rate limited per user (`panneau-embeds`, `panneau.embeds.rate_limit`). `panneau.embeds.enabled` removes it.
- **Saving resolves again.** An `embed` field keeps only the URL of the request, and resolves it when the item is saved (from the cache, usually, since the field just asked for it). The title, the thumbnail and the HTML always come from the provider. A URL that doesn't change keeps the item's value, without a request; a URL that can't be resolved is saved alone, `{ "url" }`.

## Consequences

- Sites can render the `html` of an embed: it comes from a provider of the allowlist, never from a user.
- Saving an item with a new embed URL can wait for its provider, up to `panneau.embeds.timeout` seconds, when the cache doesn't have it.
- An embed whose provider is down when it is saved has no title or thumbnail until it is saved again with another URL.
- A new provider is a line of config. URLs of other sites can't be embedded until a project adds their provider, or binds its own resolver.
- `json` values are PHP arrays: an empty object is given back as an empty array.
