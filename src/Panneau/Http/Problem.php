<?php

namespace Panneau\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Panneau\Exceptions\VersionConflictException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Errors of Panneau routes, as RFC 9457 problem details (`application/problem+json`).
 */
final class Problem
{
    public const CONTENT_TYPE = 'application/problem+json';

    public const VALIDATION = 'urn:panneau:problem:validation';

    public const VERSION_CONFLICT = 'urn:panneau:problem:version-conflict';

    /**
     * @param  array<string, mixed>  $extra  Extension members: `errors`, `current`…
     * @param  array<string, string>  $headers
     */
    public static function response(
        int $status,
        ?string $title = null,
        string $type = 'about:blank',
        ?string $detail = null,
        array $extra = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type' => $type,
            'title' => $title ?? (Response::$statusTexts[$status] ?? 'Error'),
            'status' => $status,
        ];
        if ($detail !== null && $detail !== '') {
            $body['detail'] = $detail;
        }

        return new JsonResponse(
            array_merge($body, $extra),
            $status,
            array_merge($headers, ['Content-Type' => self::CONTENT_TYPE]),
        );
    }

    /**
     * The problem for an exception. Details of server errors are only shown in debug mode.
     */
    public static function fromThrowable(Throwable $exception, bool $debug = false): JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => self::response(
                422,
                __('The given data was invalid.'),
                self::VALIDATION,
                extra: ['errors' => $exception->errors()],
            ),
            $exception instanceof VersionConflictException => self::response(
                412,
                __('The item was changed since it was loaded.'),
                self::VERSION_CONFLICT,
                extra: ['current' => $exception->current],
            ),
            $exception instanceof AuthenticationException => self::response(401),
            $exception instanceof AuthorizationException => self::response(
                $exception->hasStatus() ? (int) $exception->status() : 403,
                detail: self::customMessage($exception->getMessage(), 'This action is unauthorized.'),
            ),
            $exception instanceof ModelNotFoundException => self::response(404),
            $exception instanceof TokenMismatchException => self::response(419, 'CSRF token mismatch.'),
            $exception instanceof HttpExceptionInterface => self::response(
                $exception->getStatusCode(),
                detail: $exception->getMessage(),
                headers: array_map(strval(...), $exception->getHeaders()),
            ),
            default => self::response(500, detail: $debug ? $exception->getMessage() : null),
        };
    }

    private static function customMessage(string $message, string $default): ?string
    {
        return $message !== '' && $message !== $default ? $message : null;
    }
}
