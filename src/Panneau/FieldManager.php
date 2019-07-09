<?php namespace Panneau;

use Panneau\Support\SchemasManager;
use Panneau\Contracts\Field\Factory as FieldFactory;

class FieldManager extends SchemasManager implements FieldFactory
{
    /**
     * Wether instances are cached
     *
     * @var boolean
     */
    protected $cacheInstances = false;

    protected function getTypesConfig()
    {
        return $this->app->config['panneau.fields'];
    }

    protected function getTypeConfig($name)
    {
        return $this->app->config["panneau.fields.$name"];
    }

    protected function getTypeClassFromConfig($config)
    {
        return \Panneau\Contracts\Field\Field::class;
    }

    public function field($name)
    {
        return $this->type($name);
    }

    public function hasField($name)
    {
        return $this->hasType($name);
    }

    public function fields()
    {
        return $this->types();
    }
}
