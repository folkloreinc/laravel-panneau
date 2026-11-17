<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Panneau\Definition\InterfaceLocales;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the locale of Panneau's routes to the language of the interface the user chose
 * (panneau-js decision 0082), kept in the session. Without a choice, or one that
 * `panneau.interface_locales` doesn't have anymore, the app's locale stays.
 *
 * @internal
 */
final class ApplyInterfaceLocale
{
    public function __construct(
        private readonly Application $app,
        private readonly InterfaceLocales $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get(InterfaceLocales::SESSION_KEY) : null;
        if (is_string($locale) && $this->locales->has($locale)) {
            $this->app->setLocale($locale);
        }

        return $next($request);
    }
}
