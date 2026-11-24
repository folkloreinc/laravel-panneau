<?php

namespace Panneau\Agents;

use Panneau\Http\Problem;
use Throwable;

/**
 * The result of a tool: its structured data, or an error with the problem details the API
 * would answer (`type`, `title`, `status`, and `detail` or `errors` when there are some).
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $problem
     */
    private function __construct(
        public ?array $data,
        public ?array $problem,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function data(array $data): self
    {
        return new self($data, null);
    }

    /**
     * @param  array<string, list<string>>  $errors  The messages by argument.
     */
    public static function problem(int $status, ?string $title = null, ?string $detail = null, array $errors = []): self
    {
        $extra = $errors !== [] ? ['errors' => $errors] : [];
        $type = $status === 422 && $errors !== [] ? Problem::VALIDATION : 'about:blank';

        return new self(null, Problem::body($status, $title, $type, $detail, $extra));
    }

    /**
     * The problem the API answers for an exception. Details of server errors are only shown
     * in debug mode.
     */
    public static function fromThrowable(Throwable $exception, bool $debug = false): self
    {
        return new self(null, Problem::bodyFromThrowable($exception, $debug));
    }

    public function isError(): bool
    {
        return $this->problem !== null;
    }

    /**
     * The HTTP status of the problem, or null for data.
     */
    public function status(): ?int
    {
        return isset($this->problem['status']) && is_int($this->problem['status']) ? $this->problem['status'] : null;
    }
}
