# 0030: A guest of the OAuth authorization goes to the app with `next`

- Status: proposed
- Date: 2026-11-25
- Amends: [0028](0028-oauth-for-the-mcp-server.md)

## Context

[0028](0028-oauth-for-the-mcp-server.md) sends a guest at Passport's authorization to the login of the auth mode, with the authorization as the intended URL of the session. In the `panneau` mode that is the app's page, whose login is done by the JS app through the API and never follows the intended URL of the session: the user had to open the authorization again.

## Decision

- **In the `panneau` mode, the guest is redirected to the app's URL with a `next` query parameter**: the path and query string of the authorization request, relative and URL-encoded (`/panneau?next=%2Foauth%2Fauthorize%3Fclient_id%3D…`). The whole query is kept as received (`state`, the PKCE parameters, `scope` with its spaces).
- **The app goes to `next` with a full page load** once the user is logged in, since the authorization is served by Laravel, not by the app. It only follows a relative path of its own origin ([panneau-js#508](https://github.com/folkloreinc/panneau-js/issues/508)).
- The session still keeps the authorization as its intended URL (`redirect()->guest()`), and the server ignores `next` on the app's page: only the app reads it.
- **Unchanged:** the `external` mode, which goes to `panneau.auth.login_url` without `next`, and the `prompt=none` and no-login cases, which Passport answers.

## Consequences

- The user who connects an agent while logged out logs in and lands on the consent page, without opening the authorization again.
