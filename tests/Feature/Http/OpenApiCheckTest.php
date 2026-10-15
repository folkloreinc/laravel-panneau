<?php

namespace Panneau\Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Panneau\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Makes sure responses of the API that don't match openapi.json fail the tests.
 */
class OpenApiCheckTest extends TestCase
{
    public function test_a_body_that_does_not_match_fails(): void
    {
        Route::get(self::API.'/definition', fn () => ['protocol' => 2]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not match openapi.json');

        $this->getJson(self::API.'/definition');
    }

    public function test_an_undocumented_status_fails(): void
    {
        Route::get(self::API.'/resources/{resource}', fn () => response()->json([], 409));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('which openapi.json does not document');

        $this->getJson(self::API.'/resources/pages');
    }

    public function test_a_wrong_content_type_fails(): void
    {
        Route::get(self::API.'/resources/{resource}/{id}', fn () => response()->json(['title' => 'Not found'], 404));

        $this->expectException(AssertionFailedError::class);

        $this->getJson(self::API.'/resources/pages/1');
    }

    public function test_an_undocumented_operation_fails(): void
    {
        Route::put(self::API.'/resources/{resource}/{id}', fn () => response()->json([]));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No operation');

        $this->putJson(self::API.'/resources/pages/1');
    }
}
