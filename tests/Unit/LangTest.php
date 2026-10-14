<?php

namespace Panneau\Tests\Unit;

use Illuminate\Support\Facades\Lang;
use Panneau\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LangTest extends TestCase
{
    public function testFieldsKeysUsedInSourceExist()
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../../src/Panneau')
        );
        $keys = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                preg_match_all(
                    '/[\'"](panneau::fields\.[a-z_]+)[\'"]/',
                    file_get_contents($file->getPathname()),
                    $matches
                );
                $keys = array_merge($keys, $matches[1]);
            }
        }
        $keys = array_unique($keys);

        $this->assertNotEmpty($keys);
        foreach (['en', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $this->assertTrue(Lang::has($key, $locale, false), "Missing {$key} in {$locale}");
            }
        }
    }

    public function testImagesAndDocumentsLabelsAreDistinct()
    {
        foreach (['en', 'fr'] as $locale) {
            $this->assertNotEquals(
                trans('panneau::fields.add_image', [], $locale),
                trans('panneau::fields.add_document', [], $locale)
            );
            $this->assertNotEquals(
                trans('panneau::fields.no_images', [], $locale),
                trans('panneau::fields.no_documents', [], $locale)
            );
        }
    }
}
