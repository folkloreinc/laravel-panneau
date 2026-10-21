<?php

namespace Panneau\Tests\Unit\Forms;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Panneau\Forms\SaveContext;
use Panneau\Tests\TestCase;
use Panneau\Uploads\Upload;
use RuntimeException;
use stdClass;

class SaveContextTest extends TestCase
{
    public function test_saved_runs_the_after_save_callbacks_with_the_item(): void
    {
        $context = new SaveContext;
        $calls = [];
        $context->afterSave(function (object $item) use (&$calls): void {
            $calls[] = 'saved '.$item->id;
        });
        $context->afterFailure(function () use (&$calls): void {
            $calls[] = 'failed';
        });

        $item = new stdClass;
        $item->id = 12;
        $context->saved($item);
        $context->saved($item);
        $context->failed();

        $this->assertSame(['saved 12'], $calls);
    }

    public function test_failed_runs_the_after_failure_callbacks(): void
    {
        $context = new SaveContext;
        $calls = [];
        $context->afterSave(function () use (&$calls): void {
            $calls[] = 'saved';
        });
        $context->afterFailure(function () use (&$calls): void {
            $calls[] = 'failed';
        });

        $context->failed();
        $context->saved(new stdClass);

        $this->assertSame(['failed'], $calls);
    }

    public function test_an_exception_of_a_callback_is_reported_and_the_others_still_run(): void
    {
        $reported = [];
        $this->mock(ExceptionHandler::class)
            ->shouldReceive('report')
            ->andReturnUsing(function (RuntimeException $exception) use (&$reported): void {
                $reported[] = $exception->getMessage();
            });
        $context = new SaveContext;
        $calls = [];
        $context->afterSave(fn () => throw new RuntimeException('Disk unavailable'));
        $context->afterSave(function () use (&$calls): void {
            $calls[] = 'second';
        });

        $context->saved(new stdClass);

        $this->assertSame(['Disk unavailable'], $reported);
        $this->assertSame(['second'], $calls);
    }

    public function test_shared_values_are_made_once(): void
    {
        $context = new SaveContext;
        $made = 0;
        $make = function () use (&$made): stdClass {
            $made++;

            return new stdClass;
        };

        $this->assertSame($context->shared('key', $make), $context->shared('key', $make));
        $this->assertSame(1, $made);
    }

    public function test_upload_resolves_references_with_the_resolver(): void
    {
        $upload = new Upload(['uid' => 'upl_1']);
        $context = new SaveContext(uploads: fn (mixed $value): ?Upload => is_array($value) && ($value['upload'] ?? null) === 'upl_1' ? $upload : null);

        $this->assertSame($upload, $context->upload(['upload' => 'upl_1']));
        $this->assertNull($context->upload(['upload' => 'upl_2']));
        $this->assertNull((new SaveContext)->upload(['upload' => 'upl_1']), 'Without a resolver, no upload resolves.');
    }
}
