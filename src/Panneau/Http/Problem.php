<?php

namespace Panneau\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Panneau\Exceptions\ContextRefusedException;
use Panneau\Exceptions\EmailUnverifiedException;
use Panneau\Exceptions\ItemInUseException;
use Panneau\Exceptions\PasswordConfirmationRequiredException;
use Panneau\Exceptions\TwoFactorRequiredException;
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
     * A value of `Panneau-Context` that the user can't use (panneau-js decision 0045).
     */
    public const CONTEXT = 'urn:panneau:problem:context';

    /**
     * A user whose email address isn't verified, with `panneau.auth.email_verification`
     * (panneau-js decision 0048).
     */
    public const EMAIL_UNVERIFIED = 'urn:panneau:problem:email-unverified';

    /**
     * A user who must have two-factor authentication and hasn't set it up (panneau-js decision
     * 0049).
     */
    public const TWO_FACTOR_REQUIRED = 'urn:panneau:problem:two-factor-required';

    /**
     * An operation that needs the password, which wasn't confirmed recently: a `423` (panneau-js
     * decision 0049).
     */
    public const PASSWORD_CONFIRMATION = 'urn:panneau:problem:password-confirmation';

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
            $exception instanceof ItemInUseException => self::response(
                409,
                __('This item is in use elsewhere: remove it from where it is used, then try again.'),
            ),
            $exception instanceof ContextRefusedException => self::response(
                403,
                __('This value of the context can\'t be used.'),
                self::CONTEXT,
                extra: ['param' => $exception->param],
                headers: ['Vary' => 'Panneau-Context'],
            ),
            $exception instanceof EmailUnverifiedException => self::response(
                403,
                __('Your email address is not verified.'),
                self::EMAIL_UNVERIFIED,
            ),
            $exception instanceof TwoFactorRequiredException => self::response(
                403,
                __('Two-factor authentication is required for this account.'),
                self::TWO_FACTOR_REQUIRED,
            ),
            $exception instanceof PasswordConfirmationRequiredException => self::response(
                423,
                __('Password confirmation required.'),
                self::PASSWORD_CONFIRMATION,
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
