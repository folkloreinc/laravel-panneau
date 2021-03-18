<?php

namespace Panneau\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Panneau\Events\HandlingRequest;

class DispatchHandlingRequestEvent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->isPanneau()) {
            HandlingRequest::dispatch($request);
        }

        return $next($request);
    }
}
