# 0025: Five tools for agents, built on one operation each, shipped read first

- Status: proposed
- Date: 2026-11-23
- Amends: [0022](0022-agents-on-laravel-mcp-and-laravel-ai.md)
- Shared decision: [panneau-js decision NNNN](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/NNNN-five-tools-for-agents.md)

## Context

[0022](0022-agents-on-laravel-mcp-and-laravel-ai.md) plans the agents of Panneau on `laravel/mcp` and `laravel/ai`: operations shared with the controllers, one thin tool per tool of the protocol, two adapters. None of it is built.

A review on 2026-11-23, before the first line of code, changed the tools. [panneau-js decision NNNN](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/NNNN-five-tools-for-agents.md) cuts the nine tools of panneau-js decision 0090 to five:

- `describe`: without `resource`, the list of resources, without their actions; with one, a compact description of the resource, in the `parts` asked for (`fields`, `list`, `actions`). It replaces the definition served as an MCP resource, and `valuesSchema`.
- `find_items`: without `resource`, the global search; with one, a page of its list.
- `get_item`, with a `fields` argument.
- `save_item`: without `id`, it creates; with `id`, it updates the item at the `version` that was read, which is required.
- `run_action`, which also takes `duplicate`.

`get_action_run` is out of this version, and a resource gets a `description` in the protocol.

The review also found what 0022 missed in `laravel-panneau`:

- **A JSON Schema of the values says less than the definition.** `Field::valueSchema()` would give a model the shape of a value, but not the label, the help, the options or the linked resource of a field, which is what it needs to fill one in.
- **The built-in actions aren't in the definition.** `delete`, `restore` and `duplicate` are known to the app: `DefinitionBuilder` adds `delete` only when the user can't delete any item, or when the resource has a trash. An agent that reads the definition wouldn't know it can run them.
- **Nothing runs in a queue.** `ActionController` refuses a selection over `panneau.actions.sync_limit`: there is no run for `get_action_run` to read.
- **A save replaces whole values.** A field that is sent is validated and saved whole (`ValidationContext`): a group or a list of blocks is never patched in part.
- **Two questions need a real client:** whether the metadata of the protected resource can carry two scopes beside the route of `laravel/mcp`, and whether the tool loop of `laravel/ai` can be interrupted for a confirmation.

## Decision

The rest of 0022 stands: the adapters, the connections, the signed confirmations, the traces, the limits, and everything off by default.

### Five tools, one operation per operation

- **`Panneau\Agents\Tools` has five classes**, one per tool: `Describe`, `FindItems`, `GetItem`, `SaveItem` and `RunAction`.
- **`Panneau\Operations` keeps one class per operation:** `ListItems`, `ShowItem`, `Search`, `CreateItem`, `UpdateItem`, `DuplicateItem`, `DeleteItem` and `RunAction`. The controllers call them one for one, as in 0022.
- **A merged tool picks its operation from its arguments.** `find_items` calls `Search` without `resource`, and `ListItems` with one. `save_item` calls `CreateItem` without `id`, and `UpdateItem` with one. `run_action` calls `RunAction`, also for `delete`, `restore` and `duplicate`.
- **An argument of the other mode is a `422` problem**, never ignored: a `version` without an `id`, or the filters of a list without a `resource`.

### `ResourceDescription` replaces `valueSchema` and `DescribeResource`

- **`Field::valueSchema()` and the `DescribeResource` operation of 0022 aren't built.** `Panneau\Agents\ResourceDescription` builds the compact description of `describe`, from the definition of the user: what they can't see or do isn't in it.
- **It is pure code:** it reads a definition and returns an array, with no request, no repository and no query.
- **What it gives:**
    - of each field: its name, type, label and help, whether it is localized, required or read-only, its options, and the resource it links to;
    - of the list: its filters and its sorts;
    - the types of the resource;
    - the actions, with their fields.
- **A field gives its part by a method** that compound fields and the fields of plugins override, as they would have for `valueSchema()`.
- **It adds the built-in actions the user can run:** `delete`, `restore` and `duplicate`. The definition has them only in the two cases of `DefinitionBuilder`, so the description takes them from the resource and the user's abilities.

### `description()` on a resource

`Resource` gets `description()`, null by default, serialized in the definition. `describe` gives it in the list of resources: it is how a project tells an agent what a resource is for.

### Reads

- **`ShowItem` takes `fields`**, as `ListItems` does. The HTTP API doesn't change: only `get_item` passes them.
- **`get_action_run` waits for queued actions.** Today a selection over `panneau.actions.sync_limit` is refused, and `run_action` answers that problem.

### Writes

- **`save_item` passes its `version` as the `If-Match`** of `UpdateItem`. A conflict is the `412` problem, with the current item.
- **Its description says that it replaces the whole value of each field it sends.** Groups and blocks are never partial: an agent reads the item, changes the value and sends it whole. A field it doesn't send is left as it is.

### Order of delivery

One pull request after the other:

1. **Read only.**
    - the read operations out of the controllers: `ListItems`, `ShowItem` and `Search`;
    - `ResourceDescription`, the `Panneau\Agents\Tool` contract, and `describe`, `find_items` and `get_item`;
    - `McpTool`, `PanneauServer`, `Panneau::mcpRoutes()` and the token guard;
    - `panneau:agent-token {user} {--write}`, a command that creates a personal token until the account page does.
2. **Writes.** The write operations and `RunAction` out of the controllers, then `save_item`, and `run_action` with its confirmations.
3. **OAuth and the connections of the account:** `Mcp::oauthRoutes()`, the scopes, the consent page, and the endpoints that list and revoke connections. Before it, a trial with a real client settles the two open questions: the metadata with two scopes, and the interruption of the tool loop of `laravel/ai`.
4. **The context in the URL.**
5. **The assistant on `laravel/ai`**, after the MCP server.

## Consequences

- The first release of the agents can't change anything: a project tries them on its content with a read token, before any write exists.
- Five tools cost a model less to read than nine, and `describe` with `parts` keeps a large resource out of the context until it is needed.
- The operations stay small and tested one by one. Only the tools know about modes, and their tests cover the arguments of the wrong mode.
- Fields of plugins describe themselves with the method of `ResourceDescription`, not with a JSON Schema. A field that doesn't override it is described by its type and its common properties.
- The move of the operations out of the controllers comes in two pull requests, reads then writes, and neither changes the API.
- Until step 3, a connection is a personal token made on the command line. Until step 4, the MCP server works for a Panneau without a context.
- The open questions may change the OAuth scopes or the confirmations of the assistant: a later decision records what the trial finds.
