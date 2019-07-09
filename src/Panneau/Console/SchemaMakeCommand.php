<?php

namespace Panneau\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SchemaMakeCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:panneau:schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a schema for panneau';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Schema';

    protected function getType()
    {
        $type = $this->option('type');
        if ($this->option('document')) {
            $type = 'document';
        } elseif ($this->option('block')) {
            $type = 'block';
        } elseif ($this->option('field')) {
            $type = 'field';
        }
        return $type;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return sprintf('%s/stubs/%s.stub', __DIR__, $this->getType());
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $type = $this->getType();
        if ($type === 'schema') {
            return $rootNamespace . '\Schemas';
        }
        return $rootNamespace . '\Schemas\\' . studly_case(str_plural($type));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the schema.']
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            [
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Type of the schema',
                'schema'
            ],
            [
                'document',
                'd',
                InputOption::VALUE_NONE,
                'Make a document schema'
            ],
            [
                'block',
                'b',
                InputOption::VALUE_NONE,
                'Make a block schema'
            ]
        ];
    }
}
