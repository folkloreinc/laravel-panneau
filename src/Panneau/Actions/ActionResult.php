<?php

namespace Panneau\Actions;

use Closure;
use DateTimeInterface;

/**
 * The result of an action: what happened to each item, a message, what to refresh, and an
 * optional redirect or download.
 *
 * Items are counted as succeeded unless they are reported as failed or skipped.
 */
final class ActionResult
{
    /**
     * @var array<string, true>
     */
    private array $succeeded = [];

    /**
     * Items that did not succeed, keyed by id.
     *
     * @var array<string, array{id: string, status: 'failed'|'skipped', message?: string, reason?: string}>
     */
    private array $results = [];

    private ?string $message = null;

    private ?ActionRefresh $refresh = null;

    /**
     * @var string|array{route: string, params?: array<string, string|int>}|null
     */
    private string|array|null $redirect = null;

    /**
     * @var array{url: string, filename?: string, expiresAt?: string}|null
     */
    private ?array $download = null;

    /**
     * @param  Closure(object): string  $idOf  Gives the public id of an item.
     */
    public function __construct(private Closure $idOf) {}

    /**
     * @param  object|string  $item  An item, or its id.
     */
    public function succeeded(object|string $item): self
    {
        $id = $this->idOf($item);
        unset($this->results[$id]);
        $this->succeeded[$id] = true;

        return $this;
    }

    public function failed(object|string $item, ?string $message = null): self
    {
        $id = $this->idOf($item);
        unset($this->succeeded[$id]);
        $this->results[$id] = array_filter(
            ['id' => $id, 'status' => 'failed', 'message' => $message],
            fn (?string $value): bool => $value !== null,
        );

        return $this;
    }

    /**
     * @param  string  $reason  `forbidden`, `not_found`, `unchanged`, or another reason.
     */
    public function skipped(object|string $item, string $reason = 'unchanged', ?string $message = null): self
    {
        $id = $this->idOf($item);
        unset($this->succeeded[$id]);
        $this->results[$id] = array_filter(
            ['id' => $id, 'status' => 'skipped', 'reason' => $reason, 'message' => $message],
            fn (?string $value): bool => $value !== null,
        );

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function refresh(ActionRefresh $refresh): self
    {
        $this->refresh = $refresh;

        return $this;
    }

    /**
     * Sends the user to a path, or to a named route of the definition.
     *
     * @param  array<string, string|int>  $params
     */
    public function redirect(string $path, ?string $route = null, array $params = []): self
    {
        $this->redirect = $route !== null
            ? array_filter(['route' => $route, 'params' => count($params) > 0 ? $params : null])
            : $path;

        return $this;
    }

    /**
     * A short-lived URL that the UI opens.
     */
    public function download(string $url, ?string $filename = null, ?DateTimeInterface $expiresAt = null): self
    {
        $this->download = array_filter([
            'url' => $url,
            'filename' => $filename,
            'expiresAt' => $expiresAt?->format(DateTimeInterface::ATOM),
        ], fn (?string $value): bool => $value !== null);

        return $this;
    }

    /**
     * Whether an item was reported as failed or skipped.
     */
    public function reported(object|string $item): bool
    {
        return isset($this->results[$this->idOf($item)]);
    }

    /**
     * The result in the protocol's form.
     *
     * @param  list<string>  $ids  The ids of the items the action ran on: those not reported
     *                             as failed or skipped count as succeeded.
     * @return array<string, mixed>
     */
    public function toArray(array $ids = []): array
    {
        $succeeded = $this->succeeded;
        foreach ($ids as $id) {
            if (! isset($this->results[$id])) {
                $succeeded[$id] = true;
            }
        }
        $statuses = array_count_values(array_map(fn (array $result): string => $result['status'], $this->results));

        return array_filter([
            'message' => $this->message,
            'summary' => [
                'succeeded' => count($succeeded),
                'failed' => $statuses['failed'] ?? 0,
                'skipped' => $statuses['skipped'] ?? 0,
            ],
            'results' => array_values($this->results),
            'refresh' => $this->refresh?->value,
            'redirect' => $this->redirect,
            'download' => $this->download,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function idOf(object|string $item): string
    {
        return is_string($item) ? $item : ($this->idOf)($item);
    }
}
