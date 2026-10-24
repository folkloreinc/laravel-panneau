# 0007: Panneau owns direct uploads

- Status: accepted
- Date: 2026-10-21
- Issue: [#81](https://github.com/folkloreinc/laravel-panneau/issues/81)

## Context

Laravel side of the [shared decision on direct uploads](https://github.com/folkloreinc/panneau-js/blob/v5.x/docs/decisions/0025-presigned-uploads.md). The browser sends large files to S3-compatible storage itself, with Uppy 6, and the backend signs each request.

Not every file is a media: an import, a file field or an attachment doesn't belong in the media library. `laravel-mediatheque` 2 had started to sign uploads on its own routes, tied to its media policy and its media disk. An app with imports and no media library would have needed it all the same.

## Decision

- **The uploads are Panneau's.** When `panneau.uploads.enabled` is on, `Panneau::routes()` registers `POST uploads/sign` and `POST uploads/{upload}/complete`, and the definition announces `"uploads": { "mode": "presigned" }`.
- **A table of its own.** `panneau_uploads` keeps each upload: its id (`upl_…`), its user, its disk and path, the multipart upload id, its status (`open`, `completed`), and the file's name, MIME type and size once completed.
- **Signing follows the rules of Uppy 6:**
    - The first request of a file starts an upload, and the server chooses its key, `{path}/{upl_…}`.
    - Later requests are signed only for an open upload of the same user, by its key.
    - The first multipart upload id is recorded, and others are refused.
    - Signing uses the disk's S3 client (`createPresignedRequest`), with no request to the storage.
- **Completion** reads the object's size and MIME type from the storage. A file larger than `panneau.uploads.max_size` is deleted, and refused with a `422`. Completing twice gives the same upload.
- **Authorization.** The `uploadPanneauFiles` gate authorizes both routes. Without the gate, uploads are denied, unless the project calls `Panneau::allowWithoutPolicies()`.
- **Values reference uploads.** `ActionValues::upload('file')` resolves `{ "upload": "upl_…" }` to a completed upload of the same user, or null. `Upload::readStream()` reads its file.
- **Uploads are temporary.** Each lives `panneau.uploads.lifetime` seconds. `panneau:prune-uploads` deletes the expired ones, used or not, with their files, and aborts their multipart uploads. Whatever keeps a file (a media, an import) copies or moves it before then.
- **Storage is a contract.** `UploadStorage` signs, stats and deletes. `S3UploadStorage` implements it for disks with the `s3` driver. A project can bind its own.
- **`laravel-mediatheque` only ingests.** It has no upload routes. `POST /media` with an upload hands its file to the mediatheque's ingestion.

## Consequences

- Direct uploads work without `laravel-mediatheque`.
- Projects schedule `panneau:prune-uploads`, and publish the migration when they don't let the package load it.
- `POST /media` from an upload waits for the media API.
- The `File` field takes an upload as its value and copies its file to the field's disk when the item is saved. It prepares its value with the extension point of [0008](0008-field-preparation.md).
