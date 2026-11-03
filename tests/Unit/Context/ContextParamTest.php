<?php

namespace Panneau\Tests\Unit\Context;

use InvalidArgumentException;
use LogicException;
use Panneau\Context\Context;
use Panneau\Context\ContextOption;
use Panneau\Context\ContextParam;
use Panneau\Context\ContextResolver;
use Panneau\Exceptions\ContextRefusedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TestApp\Panneau\Resources\AuthorsResource;

class ContextParamTest extends TestCase
{
    public function test_a_param(): void
    {
        $param = ContextParam::make('contest')->label('Contest')->required()->allLabel('All contests')->resource(AuthorsResource::class);
        $param->check();

        $this->assertSame('contest', $param->name());
        $this->assertTrue($param->isRequired());
        $this->assertSame(AuthorsResource::class, $param->resourceReference());
        $this->assertSame(['name' => 'contest', 'label' => 'Contest', 'required' => true, 'allLabel' => 'All contests'], $param->toDefinition());
    }

    public function test_the_label_defaults_to_the_name_and_required_is_left_out(): void
    {
        $this->assertSame(['name' => 'client_group', 'label' => 'Client Group'], ContextParam::make('client_group')->options([])->toDefinition());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'not an identifier' => ['contest-id'],
            'a digit first' => ['1contest'],
            'a reserved segment' => ['resources'],
            'another reserved segment' => ['auth'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_a_name_is_an_identifier_that_is_not_reserved(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        ContextParam::make($name);
    }

    public function test_a_param_needs_one_source_of_choices(): void
    {
        foreach ([
            ContextParam::make('contest'),
            ContextParam::make('contest')->options([])->resource(AuthorsResource::class),
            ContextParam::make('contest')->search(fn (): array => []),
            ContextParam::make('contest')->find(fn (): ?string => null),
        ] as $param) {
            try {
                $param->check();
                $this->fail('The param was accepted.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('"contest"', $exception->getMessage());
            }
        }
        ContextParam::make('contest')->search(fn (): array => [])->find(fn (): ?string => null)->check();
    }

    public function test_options(): void
    {
        $options = ContextOption::list(['4' => 'Northwind', 12 => 'Summer', 'x' => ['value' => 'x', 'label' => 'X', 'description' => 'Ex'], new ContextOption('y', 'Y')], 'client');

        $this->assertSame([
            ['value' => '4', 'label' => 'Northwind'],
            ['value' => '12', 'label' => 'Summer'],
            ['value' => 'x', 'label' => 'X', 'description' => 'Ex'],
            ['value' => 'y', 'label' => 'Y'],
        ], array_map(fn (ContextOption $option): array => $option->toArray(), $options));

        $this->expectException(LogicException::class);
        ContextOption::list([['value' => '4']], 'client');
    }

    public function test_the_header(): void
    {
        $this->assertSame([], ContextResolver::parseHeader(null));
        $this->assertSame([], ContextResolver::parseHeader(''));
        $this->assertSame(
            ['client' => '4', 'contest' => 'Summer 2026', 'site' => 'a&b'],
            ContextResolver::parseHeader('client=4&contest=Summer+2026&client=5&empty=&site=a%26b&=x'),
        );
    }

    public function test_the_prefix_of_a_path(): void
    {
        $params = [ContextParam::make('client'), ContextParam::make('contest'), ContextParam::make('round')];

        $this->assertSame(['client' => '4', 'contest' => '12'], ContextResolver::parsePath('client/4/contest/12/resources/posts', $params));
        // An optional param without a value is left out of the prefix
        $this->assertSame(['contest' => 'a/b'], ContextResolver::parsePath('contest/a%2Fb/resources', $params));
        // Only in the order of the params, at the start of the path
        $this->assertSame([], ContextResolver::parsePath('resources/posts/client/4', $params));
        $this->assertSame(['contest' => '12'], ContextResolver::parsePath('contest/12/client/4', $params));
        $this->assertSame([], ContextResolver::parsePath('client', $params));
    }

    public function test_the_context(): void
    {
        $item = new \stdClass;
        $context = new Context(['client' => new ContextOption('4', 'Northwind', item: $item), 'contest' => new ContextOption('12', 'Summer')]);

        $this->assertSame(['client' => '4', 'contest' => '12'], $context->all());
        $this->assertSame('4', $context->get('client'));
        $this->assertSame('12', $context->getOrFail('contest'));
        $this->assertNull($context->get('round'));
        $this->assertTrue($context->has('client'));
        $this->assertFalse($context->has('round'));
        $this->assertSame($item, $context->item('client'));
        $this->assertNull($context->item('contest'));
        $this->assertSame('Summer', $context->label('contest'));
        $this->assertSame(['client' => '4'], $context->only(['client'])->all());

        try {
            $context->getOrFail('round');
            $this->fail('No exception.');
        } catch (ContextRefusedException $exception) {
            $this->assertSame('round', $exception->param);
        }
    }
}
