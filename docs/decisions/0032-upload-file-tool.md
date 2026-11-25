# 0032: A sixth tool, `upload_file`, gives agents the uploads of file fields

- Status: proposed
- Date: 2026-11-25
- Issue: [#303](https://github.com/folkloreinc/laravel-panneau/issues/303)
- Amends: [0007](0007-direct-uploads.md), [0025](0025-five-tools-read-first.md)

## Context

An agent couldn't set a `File` field, nor create a media, through the MCP server. The value of a file is a direct upload, `{ "upload": "upl_…" }` ([0007](0007-direct-uploads.md)): `save_item` refuses anything else, and none of the five tools of [0025](0025-five-tools-read-first.md) gives an upload. Seen on a first project: the agent made an image, then had no way to send it. The file had to be added by hand in the admin, for the agent to link it afterwards.

The issue proposed to reuse the direct uploads rather than add a second path for files, and left three questions open:

1. One tool with two modes, or two tools? 0025 keeps the list short on purpose.
2. A single `PUT`, or multipart for large files?
3. A presigned URL needs a disk that signs: what does a project on a local disk get?

## Decision

- **One more tool, `upload_file`, with three calls.** The list goes from five to six. What a call does is told by its arguments, as `save_item` creates or changes:
  - **`name` and `content`** store the file at once. `content` is the file in base64, or a `data:` URL in base64. The answer is a completed upload.
  - **`name` alone** starts an upload and answers `{ id, status: "open", method: "PUT", url, headers, expiresIn }`. The agent sends the file to the `url` with one HTTP `PUT` and the `headers`.
  - **`upload` alone** completes it, once the file is on the disk. Completing twice answers the same upload.

  A completed upload is `{ id, status: "completed", name, mime, size, value }`, where `value` is `{ "upload": "upl_…" }`: what `save_item` takes for a file field, and `run_action` for a `File` parameter. `upload` with `name` or `content` is an error, as every argument of the other mode of a tool is. One tool rather than two or three: the three calls are one task for a model, and a tool that completes would be listed, and described, for nothing on a site that only takes `content`.
- **A tool that writes.** It is served only to a token with `panneau:write`, like `save_item` and `run_action`: it stores a file. It is not destructive. Every call is authorized by the `uploadPanneauFiles` gate (`Authorizer::allowsUploads()`), the one of the routes of the uploads: without it, a `403`, unless `Panneau::allowWithoutPolicies()` was called.
- **The uploads are those of 0007.** Same table, same disk (`panneau.uploads.disk`), same path, same lifetime, same owner: an upload exists only for the user who made it, and `panneau:prune-uploads` removes it with its file, used or not. The tool adds no second storage for files. Completion is one method, `Uploads::complete()`, for the tool and for `POST uploads/{upload}/complete`: the size and the type the disk has, and the file deleted with its upload over `panneau.uploads.max_size`.
- **`content` is bounded** by `panneau.agents.uploads.max_content_size`, 5 MB by default and never over `panneau.uploads.max_size`. The file travels in the JSON of the call, a third larger, through the model's context for most clients: it is the way of small files, and of clients without a shell. A `content` clearly over the limit isn't decoded. The type of the file is detected in its contents (`Uploads::store()`), never taken from the client: the extension of the name only tells apart contents that are plain text or unknown.
- **A single `PUT`, no multipart.** A model drives one `curl -T` well; creating a multipart upload, signing each part, keeping the ETags and completing it is a protocol made for Uppy, and too many calls to get wrong. A single `PUT` takes up to 5 GB on S3, and `panneau.uploads.max_size` still bounds it at completion. The URL is signed for `panneau.uploads.url_ttl`. As with the browser, the type of the file is the `Content-Type` the `PUT` names, which the disk records: the tool answers the header to send, from the extension of the name.
- **On a local disk, `content` only.** Signing needs an `UploadStorage` that signs, which is `panneau.uploads.enabled` with an S3-compatible disk. Without it, `name` alone is a `422` that says so, and the description of the tool doesn't mention the URL. So that such a project gets files at all, **values reference uploads as soon as the MCP server is enabled** (`Uploads::accepted()`: `panneau.uploads.enabled` or `panneau.agents.mcp.enabled`), and the migration of the `panneau_uploads` table is loaded then too. This amends 0007, where `Uploads::resolve()` answered null without `panneau.uploads.enabled`. The routes of the uploads and the `uploads` key of the definition still need `panneau.uploads.enabled`: the browser of such a project keeps sending its files in `multipart/form-data`.
- **No file from a remote URL.** A tool that fetched a URL for the agent would make the server request an address chosen by a model, itself steered by content it read: a server-side request forgery, to internal addresses and metadata endpoints, that an allow-list of hosts only narrows. An agent that has a URL has the means to download the file and send it.
- **`describe` shows the value.** `File::example()` gives `{ "upload": "upl_…" }` (a list with `multiple()`), with a placeholder id. It is the one example of [0026](0026-agents-as-built.md) that doesn't save as it is: the id must be one of the user's uploads.
- **Traces.** `AgentToolCalled` is dispatched by a call that answers a completed upload, with no resource and no items. A call that only signs a URL wrote nothing.

## Consequences

- An agent with a writing token and the gate sets file fields and creates media, of a library on the mediatheque too, whose `file` field is a `File`.
- `panneau.agents.tools` has six tools. A project with a published `config/panneau.php` that lists the tools adds `Panneau\Agents\Tools\UploadFile` to get it; one that leaves it out keeps five.
- A project that enables the MCP server without direct uploads gets the `panneau_uploads` table on its next `migrate`, and must schedule `panneau:prune-uploads`. Its API accepts `{ "upload": "upl_…" }` values, for the uploads of the user only.
- A user with a writing token can store up to `max_content_size` per call, within `panneau.agents.rate_limit`, for `panneau.uploads.lifetime`. The gate decides who.
- The protocol describes five tools ([panneau-js decision 0106](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0106-five-tools-for-agents.md)): the schema of `upload_file` has to be added to `@panneau/protocol`, in `panneau-js`.
- The assistant inside Panneau, when it is built, serves the same tool; its files come from the browser, which may never need it.
