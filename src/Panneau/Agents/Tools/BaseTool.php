<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Panneau\Agents\Tool;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Panneau\Events\AgentToolCalled;
use Throwable;

/**
 * What the tools of Panneau share: arguments validated against their rules, the arguments that
 * aren't declared refused, and errors turned into results with the problem the API answers. A
 * tool that writes dispatches `AgentToolCalled` after each call that succeeds.
 *
 * @internal
 */
abstract class BaseTool implements Tool
{
    public function __construct(
        protected readonly ValidatorFactory $validator,
        protected readonly Config $config,
    ) {}

    final public function handle(ToolCall $call): ToolResult
    {
        try {
            $arguments = $this->validated($call->arguments);
            $data = $this->run($call, $arguments);
        } catch (Throwable $exception) {
            $result = ToolResult::fromThrowable($exception, (bool) $this->config->get('app.debug', false));
            if (($result->status() ?? 500) >= 500) {
                report($exception);
            }

            return $result;
        }
        if (! $this->readOnly() && $this->wrote($arguments, $data)) {
            $this->called($call, $arguments, $data);
        }

        return ToolResult::data($data);
    }

    /**
     * Dispatches `AgentToolCalled` for a call that wrote. What it wrote is saved: a listener that
     * throws is reported, and never turns the result into an error that the agent would retry.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $data
     */
    private function called(ToolCall $call, array $arguments, array $data): void
    {
        try {
            event(new AgentToolCalled(
                $call->user,
                $call->agent,
                $this->name(),
                is_string($arguments['resource'] ?? null) ? $arguments['resource'] : null,
                $this->touchedItems($arguments, $data),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The validation rules of the arguments, by argument.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * The arguments that don't go together, by argument: an argument of the other mode is an
     * error, never ignored.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     */
    protected function conflicts(array $arguments): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments  Validated.
     * @return array<string, mixed>
     */
    abstract protected function run(ToolCall $call, array $arguments): array;

    /**
     * Whether a call of a tool that writes wrote something: one that only previewed dispatches
     * no `AgentToolCalled`.
     *
     * @param  array<string, mixed>  $arguments  Validated.
     * @param  array<string, mixed>  $data  The result of the call.
     */
    protected function wrote(array $arguments, array $data): bool
    {
        return true;
    }

    /**
     * The ids of the items a call of a tool that writes created or changed, for
     * `AgentToolCalled`.
     *
     * @param  array<string, mixed>  $arguments  Validated.
     * @param  array<string, mixed>  $data  The result of the call.
     * @return list<string>
     */
    protected function touchedItems(array $arguments, array $data): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(array $arguments): array
    {
        $rules = $this->rules();
        $validator = $this->validator->make($arguments, $rules);
        $validator->after(function ($validator) use ($arguments, $rules): void {
            foreach (array_keys($arguments) as $name) {
                if (! array_key_exists((string) $name, $rules)) {
                    $validator->errors()->add((string) $name, __('Unknown argument `:name`.', ['name' => $name]));
                }
            }
            foreach ($this->conflicts($arguments) as $name => $message) {
                $validator->errors()->add($name, $message);
            }
        });

        return array_intersect_key($validator->validate(), $rules);
    }
}
