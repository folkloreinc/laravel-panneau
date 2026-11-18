# 0022: Agents on laravel/mcp and laravel/ai, over operations shared with the controllers

- Status: proposed
- Date: 2026-11-18
- Shared decision: [panneau-js decision 0090](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0090-agents-work-through-tools-as-their-user.md)

## Context

[panneau-js decision 0090](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0090-agents-work-through-tools-as-their-user.md) gives Panneau nine tools that AI agents use as their user: outside clients over MCP, signed in with OAuth 2.1 or a personal token, and an assistant inside Panneau whose answers stream to a panel of the app. It leaves to the backend how the tools run, how clients sign in and which model answers.

In `laravel-panneau`:

- **The operations live in the controllers.** `ResourceController`, `ActionController` and `SearchController` read the request, resolve the resource, authorize with the policies, apply the scope, validate the fields, prepare the values (`SaveContext`), call the repository and serialize the items. A tool that went around them would have to repeat all of it, and would drift from the API.
- **The guard of Panneau is a session guard** ([0005](0005-auth.md)). An MCP client sends a bearer token.
- **Laravel's packages:**
    - `laravel/mcp` (0.7) serves MCP servers over HTTP (`Mcp::web()`), takes tools as classes or instances, and wires its OAuth to Passport (`Mcp::oauthRoutes()`: the metadata of the MCP specification, dynamic client registration, the `mcp:use` scope).
    - `laravel/ai` (0.6) runs agents with tools on the providers of the project, keeps conversations, and streams answers in the UI message stream protocol of the AI SDK (`usingVercelDataProtocol()`).
    - Their tools are two different types: `Laravel\Mcp\Server\Tool` and `Laravel\Ai\Contracts\Tool`. Both describe their arguments with Laravel's JSON Schema builder (`Illuminate\JsonSchema`).
    - Neither package is at 1.0.

## Decision

### Optional, and off

- `laravel/mcp`, `laravel/ai`, and `laravel/passport` or `laravel/sanctum` are suggested, never required. Without them, nothing changes.
- **Off by default** ([0002](0002-secure-by-default.md)): `panneau.agents.mcp.enabled` and `panneau.agents.assistant.enabled` are `false`. The MCP server's routes are opt-in, with `Panneau::mcpRoutes()`; the assistant's routes join `Panneau::routes()` when it is on. The definition has `agents` only for what is on.
- **Who can use them:** the users who pass the `viewPanneau` gate, and the `usePanneauAgents` gate when the project defines one.

### One set of operations, for the controllers and the tools

- **The operations move out of the controllers** into `Panneau\Operations`: `ListItems`, `ShowItem`, `CreateItem`, `UpdateItem`, `RunAction`, `Search` and `DescribeResource`. Each one takes the user, the resource, its input as an array and the current `Context`, and does what the controller does today: the policies, the scope, the validation, `SaveContext`, the repository and the serializer. They throw the exceptions that `RenderProblems` already renders as problems.
- **The controllers read the request, call an operation and answer**, without a change of the API: the feature tests and the OpenAPI checks stay as they are. This move is the first pull request, before any tool.
- **The tools are thin.** `Panneau\Agents\Tools` has one class per tool of 0090, which implements `Panneau\Agents\Tool`: `name()`, `description()`, `schema(JsonSchema)`, `outputSchema(JsonSchema)`, `annotations()`, `writes()`, and `handle(ToolCall): ToolResult`. `handle()` calls the operation, and turns its result or its problem into a `ToolResult`.
- **Two adapters, one per package:**
    - `McpTool` extends `Laravel\Mcp\Server\Tool` around a Panneau tool. `PanneauServer` (a `Laravel\Mcp\Server`) adds an instance per tool in `boot()`. `annotations()` is overridden on the instance, since the annotations of `laravel/mcp` are attributes of a class. `shouldRegister()` leaves out the tools that write on a read connection. A result is `Response::structured()`, and a problem is `Response::error()` with the problem's JSON.
    - `AiTool` implements `Laravel\Ai\Contracts\Tool` around the same tool, with its `name()`, and gives the model the JSON of the result or of the problem.

    Only the adapters know these packages: a change of their API before 1.0 stays there. The constraints follow their minor versions (`^0.7`, `^0.6`), and CI tests against them.
- **`valuesSchema`.** Each field gives the JSON Schema of its value, `Field::valueSchema(JsonSchema $schema)`: by default from its type and its rules, wrapped in an object keyed by locale when it is localized. Compound fields and the fields of plugins give theirs. A field the user can't edit is left out.

### Connections

- **The MCP server is at `{panneau.path}/mcp`** (`panneau.agents.mcp.path`), followed by the prefix of the context, as in the app ([0015](0015-current-context.md)): `ResolveContext` reads the values from that prefix rather than from the header, and checks them the same way.
- **A token guard on the users of Panneau**, `panneau.agents.mcp.guard`: `api` with Passport (the default), or `sanctum`. It uses the user provider of `panneau.auth.guard`, which `panneau:install --agents` checks. The middleware is `auth:{guard}`, `panneau.agents` (the gates and the abilities of the connection), `panneau.context`, `panneau.interface-locale`, and the `panneau-agents` rate limiter.
- **OAuth with Passport, through `laravel/mcp`.** `Panneau::mcpRoutes()` calls `Mcp::oauthRoutes()`, for the metadata and the registration of clients, with Passport's routes for the authorization and the tokens.
    - **Scopes:** `mcp:use` reads; `panneau:write` also writes. Panneau registers `panneau:write` with `Passport::tokensCan()`, and serves the metadata of its protected resource with both scopes (`laravel/mcp` keeps its own route when one exists). A client that asks only for `mcp:use` gets a read connection.
    - **The consent page** is a Blade view of Panneau (`panneau::oauth.authorize`), as sober as the login page, which names the client and what it asks: read, or read and change. `panneau:install --agents` sets it with `Passport::authorizationView()`, unless the project already set its own for its API.
    - **A guest** at the authorization goes to the login of the auth mode, `panneau` or `external`, and comes back to it.
- **Personal tokens** are tokens of the guard's package, Passport's personal access tokens or Sanctum's, with the same scopes as OAuth: `mcp:use`, and `panneau:write` for a token that writes. `Panneau\Agents\Connections` lists and revokes the OAuth clients and the tokens of a user for the endpoints of the account page, through whichever package the guard uses.

### Writes

- **The `version` of `update_item`** goes to the operation as the `If-Match` of the request does: a conflict throws `VersionConflictException`, rendered as the `412` problem with the current item.
- **Confirmations** are signed with the app's key (`hash_hmac` over a JSON payload): the user, the connection, the resource, the action, a hash of the selection and the values, and an expiry 5 minutes later. `RunAction` checks the confirmation before it runs, and keeps nothing.
- **Traces:** `SaveContext` and `ActionContext` get the agent of the call (`AgentCaller`: the connection's id and name), null outside tools, so that resources, their hooks and revisions can record it. Every call of a tool that writes dispatches `Panneau\Events\AgentToolCalled`, with the user, the agent, the tool, the resource and the ids. Panneau doesn't keep a log of its own: a project listens.
- **Rate limits:** the `panneau-agents` rate limiter counts the calls of each user, from `panneau.agents.rate_limit` (60 a minute by default).

### The assistant inside Panneau

- **An agent of `laravel/ai`**, `Panneau\Agents\Assistant`, with the tools that the user's connection allows (the session's, which reads and writes), and instructions that Panneau writes from the definition: the name of the Panneau, its resources, the user's language, the page of the message, and that the values of items are data, not instructions. A project adds its own instructions in `panneau.agents.assistant.instructions`.
- **The provider and the model are the project's**, `panneau.agents.assistant.provider` and `.model`, with the keys of `config/ai.php`. Panneau ships no key and picks no default provider.
- **Streaming:** `->stream()->usingVercelDataProtocol()`. A tool that needs a confirmation answers the model that the user was asked, and the stream sends the `data-panneau-confirmation` part to the app, with the token. The next message, `{ "confirm" }` or `{ "cancel" }`, is handled by Panneau without the model: it runs the action, or doesn't, adds the result to the conversation, then lets the model go on.
- **Conversations** are kept in the conversation store of `laravel/ai` (its migrations), by user. `panneau:prune-conversations` deletes those older than `panneau.agents.assistant.retention_days` (30); the install schedules it.
- **Limits:** at most `panneau.agents.assistant.max_steps` (10) tool calls per message, and the `panneau-assistant` rate limiter for the messages of each user, so that costs stay in hand.

## Consequences

- The tools can't drift from the API: they run the same operations, and the tests of the operations cover both.
- The move of the operations is a refactor with no change of behaviour, worth doing on its own: other entry points (commands, imports) can use the operations too.
- A project turns the agents on with three settings and `Panneau::mcpRoutes()`, and installs Passport for OAuth. A project that already uses Passport for its API shares it: its clients and Panneau's are in the same tables, and Panneau's scopes are only `mcp:use` and `panneau:write`.
- `laravel/mcp` and `laravel/ai` before 1.0 may break: the adapters absorb it, and the minor constraints keep a release from breaking a project.
