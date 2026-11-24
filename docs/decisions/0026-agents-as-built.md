# 0026: The agents as built: laravel/mcp 1, Sanctum first, plain JSON Schema, and examples

- Status: proposed
- Date: 2026-11-24
- Amends: [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md), [0025](0025-five-tools-read-first.md)
- Shared decision: [panneau-js decision 0106](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0106-five-tools-for-agents.md)

## Context

[0022](0022-agents-on-laravel-mcp-and-laravel-ai.md) and [0025](0025-five-tools-read-first.md) planned the agents before a line of code. Three of the four steps of 0025 are now built: the five tools read and write, behind a personal token, with the context in the URL. OAuth, the connections of the account and the assistant are not.

Building them, and a first trial with a real client on 2026-11-24, changed some of what the two decisions say:

- **`laravel/mcp` reached 1.0.** 0022 was written against 0.7 and planned for a package that could break. It is at 1.2, and its API is the one 0022 describes.
- **A personal token needs no OAuth server.** 0022 made Passport the default guard, for OAuth. The first step ships a token made on the command line, which Sanctum does with one trait.
- **The tools describe their arguments once, for two adapters.** 0022 gave a tool `schema(JsonSchema)` and `annotations()`, which are the types of `laravel/mcp`.
- **The trial showed what a model pays for.** Each item of a list carried its map of abilities, the global search answered every field, and a field said it was a `money` without saying what a `money` looks like.

## Decision

The rest of 0022 and 0025 stands.

### Packages

- **`laravel/mcp` is at `^1.2`**, not `^0.7`. It stays suggested, never required, with `laravel/sanctum`.
- **The default guard of the MCP server is `sanctum`** (`panneau.agents.mcp.guard`), not `api` with Passport. The abilities of a token are read with `tokenCan()`, which the users of Sanctum and of Passport both have, so the guard can be either. Passport comes with OAuth.

### The contract of a tool

- **`Panneau\Agents\Tool` knows no package:** `name()`, `description()`, `inputSchema()` as a plain JSON Schema array, `readOnly()`, `destructive()` and `handle(ToolCall): ToolResult`. There is no `schema(JsonSchema)`, `outputSchema()`, `annotations()` or `writes()`: `McpTool` builds the annotations of MCP from `readOnly()` and `destructive()`.
- **`destructive()` is true for `run_action` only.** `save_item` writes what it is given; an action can delete.
- **The tools are a config key,** `panneau.agents.tools`: a project adds its own or leaves one out.
- **The operations take the user and their input as arguments,** and a `Request` only for the scope of the resource, the media of the values and the hooks of a resource. They don't take the current `Context`: it is resolved before them, by the middleware.
- **`duplicate` is not an action.** `run_action` runs `DuplicateItem` for the built-in `duplicate`, on one id, and answers the copy. 0025 sent it through `RunAction`.

### What the tools answer

- **`find_items` answers items without `abilities`,** in both modes, and the fields of the list's columns unless `fields` names others, in the global search too. `get_item` gives the abilities. The HTTP API doesn't change: the tool leaves them out.
- **A field gives an `example` of its value** when the value isn't a plain scalar: `Field::example()`, null by default, which `describe` adds to the description. It is what 0025 called the method by which a field gives its part, and it replaces the JSON Schema of the values for good. Each example is one `save_item` accepts. Fields inside repeaters and blocks give none yet.

### Confirmations

- **A confirmation is asked by one rule,** the one `describe` gives the agent: the action declares a `confirm`, or it destroys and can't be undone.
- **Its preview is the dry run** of the action when it answers dry runs, else the count of the selection.
- **The token is bound to the context** of the connection, with the user, the connection, the resource, the action and the arguments of 0022.
- **It isn't single use:** nothing is stored, so within its 5 minutes the same call runs again, on what is left of the selection.
- **The default message of an action that destroys and can't be undone says so.**

### Traces

- **`AgentToolCalled` is dispatched after a call that wrote,** never for a dry run or a confirmation that was asked. A listener that throws is reported, and doesn't change the answer: the agent would send the call again.

### The context

- **The route is `{panneau.path}/{panneau.agents.mcp.path}/{context?}`,** and `ResolveContext` reads the prefix with a `path` parameter. On that route the `Panneau-Context` header is ignored: a connection is made for one context.

## Open

- **The `agents` key of the definition** isn't built: the app can't tell that the MCP server is on. It comes with the connections of the account page.
- **The email verification and the two-factor requirement** that `panneau.auth` checks on the session aren't checked on a token. To decide with OAuth: whether a token made by a verified user stands for those checks, or the middleware of the agents repeats them.
- **The two questions of 0025** for a real client are still open: the metadata of the protected resource with two scopes, and the interruption of the tool loop of `laravel/ai`.

## Consequences

- A project turns the MCP server on with `panneau.agents.mcp.enabled`, `Panneau::mcpRoutes()` and `HasApiTokens` on its user model, and makes a token with `panneau:agent-token`. It needs neither Passport nor an OAuth client.
- A third adapter, for `laravel/ai`, has nothing of `laravel/mcp` to translate: the contract of a tool is plain data.
- A field of a plugin that takes a compound value overrides `example()`, or an agent has to read an item to learn its shape.
- An agent that needs the abilities of every item of a list calls `get_item` for each: lists are for finding, not for deciding what to do.
- A confirmation can be replayed for 5 minutes by the connection it was made for. A project that can't accept it listens to `AgentToolCalled`, or gives the action its own guard.
