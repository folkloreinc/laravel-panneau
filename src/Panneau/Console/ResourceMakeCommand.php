<?php

namespace Panneau\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ResourceMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:panneau:resource {--json-schema} {--typed-model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a resource for panneau';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('json-schema')) {
            return __DIR__.'/stubs/resource-json-schema.stub';
        } elseif ($this->option('typed-model')) {
            return __DIR__.'/stubs/resource-typed-model.stub';
        } else {
            return __DIR__.'/stubs/resource.stub';
        }
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Resources';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the resource.'],
        ];
    }
}
