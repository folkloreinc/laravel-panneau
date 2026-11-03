<?php

namespace Panneau\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Panneau\Context\ContextOption;
use Panneau\Context\ContextResolver;
use Panneau\Http\Controllers\Concerns\ReadsRouteParameters;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `GET /context/{param}/options?q=…`: the first choices of a param of the context that match
 * `q`, for the values of the params before it (panneau-js decision 0045).
 *
 * @internal
 */
final class ContextOptionsController
{
    use ReadsRouteParameters;

    public function __construct(private readonly ContextResolver $resolver) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $options = $this->resolver->options(
            $this->routeParameter($request, 'param'),
            is_string($q) ? $q : '',
            $request->user(),
            $request,
        ) ?? throw new NotFoundHttpException(__('The context has no such param.'));

        return new JsonResponse(['data' => array_map(fn (ContextOption $option): array => $option->toArray(), $options)]);
    }
}
