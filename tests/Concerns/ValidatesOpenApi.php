<?php

namespace Panneau\Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\Errors\ErrorFormatter;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks responses of the Panneau API against the protocol's OpenAPI document: the operation
 * and the status must be documented, and the content type and body must match.
 */
trait ValidatesOpenApi
{
    use ValidatesProtocol;

    private const OPENAPI_URI = 'https://github.com/folkloreinc/panneau-js/blob/v5.x/packages/protocol/schema/openapi.json';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $openApi = null;

    /**
     * @param  TestResponse<Response>  $response
     */
    protected function assertMatchesOpenApi(string $method, string $path, TestResponse $response): void
    {
        $operation = self::findOperation(strtolower($method), $path);
        Assert::assertNotNull($operation, sprintf('No operation for %s %s in openapi.json.', strtoupper($method), $path));
        [$template, $definition] = $operation;
        $label = sprintf('%s %s (%s)', strtoupper($method), $path, $template);

        $status = $response->getStatusCode();
        $responses = $definition['responses'] ?? [];
        Assert::assertArrayHasKey((string) $status, $responses, sprintf('%s answered %d, which openapi.json does not document.', $label, $status));
        $expected = $responses[(string) $status];

        if (! isset($expected['content'])) {
            Assert::assertSame('', (string) $response->getContent(), sprintf('%s: a %d has no body.', $label, $status));

            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        $mediaType = null;
        foreach (array_keys($expected['content']) as $candidate) {
            if (str_starts_with($contentType, $candidate)) {
                $mediaType = $candidate;
            }
        }
        Assert::assertNotNull($mediaType, sprintf('%s: content type "%s", expected %s.', $label, $contentType, implode(' or ', array_keys($expected['content']))));

        $schema = self::absoluteRefs($expected['content'][$mediaType]['schema']);
        $result = self::protocolValidator()->validate(
            json_decode((string) $response->getContent()),
            json_decode((string) json_encode($schema)),
        );
        $errors = $result->error() !== null ? (new ErrorFormatter)->format($result->error()) : [];
        Assert::assertTrue($result->isValid(), sprintf(
            "%s: the %d response does not match openapi.json.\n%s\n%s",
            $label,
            $status,
            json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            mb_substr((string) $response->getContent(), 0, 2000),
        ));
    }

    /**
     * The operation of a path relative to the API, preferring templates with the fewest
     * parameters.
     *
     * @return array{string, array<string, mixed>}|null
     */
    private static function findOperation(string $method, string $path): ?array
    {
        $matches = [];
        foreach (self::openApi()['paths'] as $template => $item) {
            if (! isset($item[$method])) {
                continue;
            }
            $pattern = '#^'.preg_replace('#\\\\\{[^/]+?\\\\\}#', '[^/]+', preg_quote($template, '#')).'$#';
            if (preg_match($pattern, $path) === 1) {
                $matches[substr_count($template, '{')][] = [$template, $item[$method]];
            }
        }
        if (count($matches) === 0) {
            return null;
        }
        ksort($matches);

        return reset($matches)[0];
    }

    /**
     * Makes the `$ref`s of an inline schema point into openapi.json.
     */
    private static function absoluteRefs(mixed $schema): mixed
    {
        if (! is_array($schema)) {
            return $schema;
        }
        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#')) {
                $schema[$key] = self::OPENAPI_URI.$value;
            } else {
                $schema[$key] = self::absoluteRefs($value);
            }
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function openApi(): array
    {
        return self::$openApi ??= json_decode((string) file_get_contents(__DIR__.'/../protocol/openapi.json'), true);
    }
}
