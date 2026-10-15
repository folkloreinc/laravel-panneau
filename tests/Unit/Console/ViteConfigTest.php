<?php

namespace Panneau\Tests\Unit\Console;

use Panneau\Console\ViteConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ViteConfigTest extends TestCase
{
    /**
     * The vite.config.js of the Laravel 12 skeleton.
     */
    private const SKELETON = <<<'JS'
        import { defineConfig } from 'vite';
        import laravel from 'laravel-vite-plugin';
        import tailwindcss from '@tailwindcss/vite';

        export default defineConfig({
            plugins: [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.js'],
                    refresh: true,
                }),
                tailwindcss(),
            ],
            server: {
                watch: {
                    ignored: ['**/storage/framework/views/**'],
                },
            },
        });
        JS;

    private function edit(string $source): ViteConfig
    {
        $config = new ViteConfig($source);
        $this->assertTrue($config->addInput('resources/js/panneau.tsx'));
        $this->assertTrue($config->addPlugin('@vitejs/plugin-react', 'react', 'react()'));

        return $config;
    }

    public function test_the_laravel_skeleton(): void
    {
        $this->assertSame(<<<'JS'
            import { defineConfig } from 'vite';
            import laravel from 'laravel-vite-plugin';
            import tailwindcss from '@tailwindcss/vite';
            import react from '@vitejs/plugin-react';

            export default defineConfig({
                plugins: [
                    laravel({
                        input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/panneau.tsx'],
                        refresh: true,
                    }),
                    tailwindcss(),
                    react(),
                ],
                server: {
                    watch: {
                        ignored: ['**/storage/framework/views/**'],
                    },
                },
            });
            JS, $this->edit(self::SKELETON)->source());
    }

    public function test_a_single_input_multiline_lists_and_double_quotes(): void
    {
        $source = <<<'JS'
            import { defineConfig } from "vite"
            import laravel from "laravel-vite-plugin"

            export default defineConfig({
              plugins: [
                laravel({
                  input: "resources/js/app.js",
                  refresh: ["routes/**"],
                })
              ]
            })
            JS;

        $this->assertSame(<<<'JS'
            import { defineConfig } from "vite"
            import laravel from "laravel-vite-plugin"
            import react from "@vitejs/plugin-react"

            export default defineConfig({
              plugins: [
                laravel({
                  input: ["resources/js/app.js", "resources/js/panneau.tsx"],
                  refresh: ["routes/**"],
                }),
                react()
              ]
            })
            JS, $this->edit($source)->source());
    }

    public function test_multiline_inputs_and_inline_plugins(): void
    {
        $source = <<<'JS'
            import {
                defineConfig,
            } from 'vite';
            import laravel from 'laravel-vite-plugin';

            export default defineConfig({
                // Plugins [with brackets in a comment]
                plugins: [laravel({ input: [
                    'resources/js/app.js', // the site ]
                    'resources/css/app.css',
                ] })],
            });
            JS;

        $this->assertSame(<<<'JS'
            import {
                defineConfig,
            } from 'vite';
            import laravel from 'laravel-vite-plugin';
            import react from '@vitejs/plugin-react';

            export default defineConfig({
                // Plugins [with brackets in a comment]
                plugins: [laravel({ input: [
                    'resources/js/app.js', // the site ]
                    'resources/css/app.css',
                    'resources/js/panneau.tsx',
                ] }), react()],
            });
            JS, $this->edit($source)->source());
    }

    public function test_what_is_there_is_kept(): void
    {
        $source = str_replace("'resources/js/app.js'", "'resources/js/panneau.tsx'", self::SKELETON);
        $source = str_replace("import tailwindcss from '@tailwindcss/vite';", "import react from '@vitejs/plugin-react';", $source);
        $config = new ViteConfig($source);

        $this->assertFalse($config->addInput('resources/js/panneau.tsx'));
        $this->assertFalse($config->addPlugin('@vitejs/plugin-react', 'react', 'react()'));
        $this->assertSame($source, $config->source());
    }

    public static function unknownShapes(): iterable
    {
        yield 'no input' => ["import laravel from 'laravel-vite-plugin';\nexport default { plugins: [laravel(config)] };", true];
        yield 'no plugins' => ["import config from './vite.shared';\nexport default { input: ['a.js'] };", false];
        yield 'CommonJS' => ["const laravel = require('laravel-vite-plugin');\nmodule.exports = { plugins: [laravel({ input: ['a.js'] })] };", false];
        yield 'unclosed' => ["import laravel from 'laravel-vite-plugin';\nexport default { plugins: [laravel({ input: ['a.js' ", true];
    }

    #[DataProvider('unknownShapes')]
    public function test_unknown_shapes_are_left_alone(string $source, bool $inputFails): void
    {
        $config = new ViteConfig($source);

        $input = $config->addInput('resources/js/panneau.tsx');
        $plugin = $config->addPlugin('@vitejs/plugin-react', 'react', 'react()');

        $this->assertTrue($input === null || $plugin === null);
        if ($inputFails) {
            $this->assertNull($input);
        } else {
            $this->assertNull($plugin);
        }
    }
}
