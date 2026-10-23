<?php

namespace Panneau\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Panneau\Contracts\EmbedResolver;

/**
 * `GET /embeds?url=`: what a URL embeds, for `embed` fields, from the `EmbedResolver`. A URL it
 * can't embed is a `422` on `url`.
 *
 * @internal
 */
final class EmbedController
{
    /**
     * Name of the rate limiter of the route.
     */
    public const RATE_LIMITER = 'panneau-embeds';

    public function __construct(
        private readonly EmbedResolver $resolver,
        private readonly ValidationFactory $validation,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $url = $this->validation->make(
            $request->query(),
            ['url' => ['required', 'string', 'url:http,https', 'max:2048']],
        )->validate()['url'];

        $value = $this->resolver->resolve($url);
        if ($value === null) {
            throw ValidationException::withMessages(['url' => __("This URL can't be embedded.")]);
        }

        return new JsonResponse(['data' => $value]);
    }
}
