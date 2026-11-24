<?php

namespace Panneau\Agents\Tools;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Panneau\Agents\Tool;
use Panneau\Agents\ToolCall;
use Panneau\Agents\ToolResult;
use Throwable;

/**
 * What the read tools share: arguments validated against their rules, the arguments that
 * aren't declared refused, and errors turned into results with the problem the API answers.
 *
 * @internal
 */
abstract class ReadTool implements Tool
{
    public function __construct(
        protected readonly ValidatorFactory $validator,
        protected readonly Config $config,
    ) {}

    public function readOnly(): bool
    {
        return true;
    }

    final public function handle(ToolCall $call): ToolResult
    {
        try {
            $arguments = $this->validated($call->arguments);

            return ToolResult::data($this->run($call, $arguments));
        } catch (Throwable $exception) {
            $result = ToolResult::fromThrowable($exception, (bool) $this->config->get('app.debug', false));
            if (($result->status() ?? 500) >= 500) {
                report($exception);
            }

            return $result;
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
