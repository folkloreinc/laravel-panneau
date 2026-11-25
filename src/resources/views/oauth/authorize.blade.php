@php
    // The consent page of OAuth for the MCP server (decision 0028), set with Passport::authorizationView(),
    // in the language of the interface the user chose (ApplyInterfaceLocale on its route)
    $ids = array_map(fn ($scope) => $scope->id, $scopes);
    $writes = in_array(\Panneau\Http\Middleware\AuthorizeAgents::WRITE, $ids, true);
    $others = array_filter($scopes, fn ($scope) => ! in_array($scope->id, \Panneau\Agents\Mcp\OAuthMetadata::SCOPES, true));
    $panneau = app(\Panneau\Definition\DefinitionBuilder::class)->name();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('panneau::oauth.title', ['client' => $client->name]) }} · {{ $panneau }}</title>
    <style>
        :root { color-scheme: light dark; --text: #1a1a1a; --muted: #5f6368; --background: #f5f5f4; --surface: #fff; --border: #d6d3d1; --primary: #1a1a1a; --on-primary: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --text: #f5f5f4; --muted: #a8a29e; --background: #1c1917; --surface: #292524; --border: #44403c; --primary: #f5f5f4; --on-primary: #1c1917; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; font: 16px/1.5 system-ui, -apple-system, sans-serif; color: var(--text); background: var(--background); }
        main { width: 100%; max-width: 420px; padding: 32px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
        .panneau { margin: 0 0 24px; font-size: 14px; font-weight: 600; color: var(--muted); }
        h1 { margin: 0 0 16px; font-size: 20px; line-height: 1.3; }
        p, ul { margin: 0 0 16px; }
        ul { padding-left: 20px; }
        .muted { color: var(--muted); font-size: 14px; }
        .buttons { display: flex; gap: 8px; margin-top: 24px; }
        .buttons form { flex: 1; }
        button { width: 100%; padding: 10px 16px; font: inherit; font-weight: 600; border-radius: 6px; border: 1px solid var(--primary); cursor: pointer; }
        .authorize { color: var(--on-primary); background: var(--primary); }
        .refuse { color: var(--text); background: transparent; border-color: var(--border); }
    </style>
</head>
<body>
    <main>
        <p class="panneau">{{ $panneau }}</p>
        <h1>{{ __('panneau::oauth.heading', ['client' => $client->name]) }}</h1>
        <p>{{ $writes ? __('panneau::oauth.writes') : __('panneau::oauth.reads') }}</p>
        @if ($others !== [])
            <ul>
                @foreach ($others as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif
        <p class="muted">{{ __('panneau::oauth.user', ['user' => $user->email ?? $user->getAuthIdentifier()]) }}</p>
        <div class="buttons">
            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="refuse">{{ __('panneau::oauth.refuse') }}</button>
            </form>
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="authorize">{{ __('panneau::oauth.authorize') }}</button>
            </form>
        </div>
    </main>
</body>
</html>
