# 0032: A sixth tool, `upload_file`, sends the files of agents to a media library by a signed URL

- Status: proposed
- Date: 2026-11-25
- Issue: [#303](https://github.com/folkloreinc/laravel-panneau/issues/303)
- Amends: [0025](0025-five-tools-read-first.md)

## Context

An agent couldn't add a file through the MCP server. A `media` field takes the id of a media, and a media is created with a file: none of the five tools of [0025](0025-five-tools-read-first.md) carries one. Seen on a first project: the agent made an image, then had no way to send it. The file had to be added by hand in the admin, for the agent to link it afterwards.

The issue proposed to give the agent a direct upload ([0007](0007-direct-uploads.md)), with the file in base64 in the call for the small ones. A first version of this decision did that. Two things were wrong with it:

- **The file went through the model.** A file in base64 in the arguments of a tool is written by the model, token by token: slow, costly, bounded by its context, and wrong as soon as one character is. A model has the file on a disk, and a shell: it should send the file, not spell it.
- **An upload isn't where files live.** Files that editors reuse are media of a library ([0009](0009-media-libraries.md)): a row of `panneau_uploads` is a temporary file waiting for a save. Uploading there gave the agent a second task, creating the media from the upload, and made the MCP server depend on the direct uploads, their table and their S3 disk.

## Decision

- **One more tool, `upload_file`.** The list goes from five to six. Its arguments: `resource`, the id of a media library (a resource of kind `media`), not needed when the user can create in one library only; `name`, the name of the file with its extension; and `values`, the other fields of the media, as in `save_item`. It answers `{ resource, name, method: "PUT", url, expiresIn }`.
- **The tool takes no file. It answers a URL of Panneau.** The agent sends the file there itself, as the body of one HTTP `PUT`: `curl -T cover.png "$url"`. The answer of that `PUT` is the new media as `get_item` gives it, with a `201`, or the problem as JSON. The `id` of the media is then the value of a `media` field in `save_item`: the instructions of the server and the description of the tool say so.
- **The URL is a signed route of Laravel**, `PUT {path}/mcp/upload`, registered by `Panneau::mcpRoutes()` with the server. `URL::temporarySignedRoute()` signs it and bounds its life, `panneau.agents.uploads.url_ttl`, 900 seconds by default; the `signed` middleware (`ValidateSignature`) checks it. The signature is relative: it covers the path and the query, not the host, so the URL holds behind a proxy.
- **The signature is the authentication.** The route asks for no token: a model has no easy way to put the token of its connection in a shell command, and shouldn't hold it. What the URL was made for is one parameter, `upload`, encrypted with Laravel's encrypter: the user, the connection (its id and name), the library, the name of the file, the values and the context of the connection. Encrypted rather than plain and signed, so that the URL, which ends in logs and transcripts, shows no id of a user and no value.
- **The file becomes a media by the `CreateItem` operation**, the one of the API and of `save_item`, as the user of the URL, with the `file` field of the library set to the body. So the policy (`create`), the `accept` and `maxSize` of the field, the scope and the media types of the library, its hooks and the ingest of the mediatheque all apply as in the admin. The body is copied to a temporary file as a stream, never held in memory, and given to the field as an `UploadedFile`: the type is read from the contents, not from the name.
- **The user is checked again when the file arrives.** The route finds the user among those of `panneau.auth.guard`, makes them the user of the request, and checks what the MCP server checks for a token: the `viewPanneau` and `usePanneauAgents` gates, the verified address, two-factor when the project requires it. The context is resolved again for that user. A URL made for a user who lost access since is a `403`.
- **Nothing is written by the tool.** It checks that the resource is a library the user may create in, and signs. It is still a tool that writes, served only to a token with `panneau:write`: its URL creates. `AgentToolCalled` is dispatched by the route, once the media is created, with the tool `upload_file`, the library and the id of the media; a call of the tool dispatches none.
- **Limits.** The route shares the rate limit of the agents, `panneau.agents.rate_limit`, counted for the user of the URL. A body over `panneau.agents.uploads.max_size`, 100 MB by default, is a `413`, read no further than the limit; `null` leaves the limit to the field and to the web server. An empty body is a `400`. The `values` must fit in a URL of 4000 characters: over it, the tool answers a `422`, and the agent sets the other fields with `save_item` afterwards.
- **The URL can be used again until it expires.** Nothing is stored for a URL, so nothing marks it as used, as for the confirmations of [0026](0026-agents-as-built.md), which can be replayed for 5 minutes. Each `PUT` within its life creates a media. Whoever holds the URL can add files, of the types the library accepts, as that user, to that library, for fifteen minutes: no more than that, and nothing can be read with it. Revoking the token or the connection doesn't revoke the URLs it already got; they expire.
- **No file in the call, no remote URL.** There is no `content` in base64, for the reasons above. A tool that fetched a URL for the agent would make the server request an address chosen by a model, itself steered by content it read: a server-side request forgery, to be designed on its own if it is ever needed.
- **Direct uploads are untouched.** The tool works the same on a local disk and on S3, with or without `panneau.uploads.enabled`, and adds no table.

## Known limits

- **A `File` field outside a library.** A plain `File` or `Image` field of a resource, whose value is a direct upload, still can't be set by an agent. Files that agents add go to a library, and fields that take them are `media` fields.
- **A client without a shell.** The agent must be able to make an HTTP request with a file as its body. A chat client with no code execution can't; it couldn't have read a file into base64 either.
- **One request.** No multipart, no resuming: a very large file is one long `PUT`, within the limits of PHP and the web server, such as `client_max_body_size` on nginx.

## Consequences

- An agent with a writing token adds media to the libraries its user can create in, on a mediatheque library too, and links them in `media` fields.
- `panneau.agents.tools` has six tools. A project with a published `config/panneau.php` that lists the tools adds `Panneau\Agents\Tools\UploadFile` to get it; one that leaves it out keeps five, and the route then signs nothing anyone was given.
- The MCP server has a second route, that no token guards. Its security is the signature, the encryption and the checks above; a project that rotates `APP_KEY` invalidates the URLs in flight.
- The protocol describes five tools ([panneau-js decision 0106](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0106-five-tools-for-agents.md)): the schema of `upload_file` has to be added there. The package ships it first, as an extension, since a server may serve more tools than the protocol names.
- The assistant inside Panneau, when it is built, won't need the tool: its files come from the browser, through the fields.
