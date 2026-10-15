<?php

namespace Panneau\Tests\Unit\Repositories;

use InvalidArgumentException;
use Panneau\Contracts\ResourceRepository;
use Panneau\Repositories\RepositoryAdapters;
use Panneau\Support\Facade as Panneau;
use Panneau\Tests\TestCase;
use TestApp\Repositories\Adapters\ArrayRepository;
use TestApp\Repositories\Adapters\LegacyPages;
use TestApp\Repositories\Adapters\LegacyRepository;
use TestApp\Repositories\Adapters\LegacyRepositoryAdapter;

class RepositoryAdaptersTest extends TestCase
{
    private function adapters(): RepositoryAdapters
    {
        return $this->app->make(RepositoryAdapters::class);
    }

    public function test_it_is_a_singleton(): void
    {
        $this->assertSame($this->adapters(), $this->app->make(RepositoryAdapters::class));
    }

    public function test_a_resource_repository_is_used_as_is(): void
    {
        $repository = new ArrayRepository;

        $this->assertSame($repository, $this->adapters()->resolve($repository));
        $this->assertTrue($this->adapters()->supports($repository));
    }

    public function test_a_class_name_is_resolved_from_the_container(): void
    {
        $repository = new ArrayRepository;
        $this->app->instance(ArrayRepository::class, $repository);

        $this->assertSame($repository, $this->adapters()->resolve(ArrayRepository::class));
    }

    public function test_a_repository_without_adapter_is_refused(): void
    {
        $this->assertFalse($this->adapters()->supports(new LegacyPages));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no adapter is registered');

        $this->adapters()->resolve(new LegacyPages);
    }

    public function test_an_adapter_class_is_registered_by_contract(): void
    {
        Panneau::repositoryAdapter(LegacyRepository::class, LegacyRepositoryAdapter::class);
        $this->app->bind(LegacyRepository::class, LegacyPages::class);

        $repository = $this->adapters()->resolve(LegacyRepository::class);

        $this->assertInstanceOf(LegacyRepositoryAdapter::class, $repository);
        $this->assertInstanceOf(LegacyPages::class, $repository->repository);
        $this->assertSame('Home', $repository->find('1', [])?->title);
    }

    public function test_an_adapter_can_be_a_closure(): void
    {
        $this->adapters()->register(
            LegacyRepository::class,
            fn (LegacyRepository $repository): ResourceRepository => new ArrayRepository($repository->all()),
        );

        $repository = $this->adapters()->resolve(new LegacyPages);

        $this->assertInstanceOf(ArrayRepository::class, $repository);
        $this->assertSame('Home', $repository->find('1', [])?->title);
    }

    public function test_the_adapter_registered_last_wins(): void
    {
        $this->adapters()->register(LegacyPages::class, fn (): ResourceRepository => new ArrayRepository(['class' => (object) []]));
        $this->adapters()->register(LegacyRepository::class, fn (): ResourceRepository => new ArrayRepository(['contract' => (object) []]));

        $this->assertNotNull($this->adapters()->resolve(new LegacyPages)->find('contract', []));

        $this->adapters()->register(LegacyPages::class, fn (): ResourceRepository => new ArrayRepository(['class' => (object) []]));

        $this->assertNotNull($this->adapters()->resolve(new LegacyPages)->find('class', []));
    }

    public function test_an_adapter_must_return_a_resource_repository(): void
    {
        $this->adapters()->register(LegacyRepository::class, fn (): object => new \stdClass);

        $this->expectException(InvalidArgumentException::class);

        $this->adapters()->resolve(new LegacyPages);
    }
}
