<?php namespace Panneau;

use Panneau\Support\SchemasManager;
use Panneau\Contracts\Document\Factory as DocumentFactory;

class DocumentManager extends SchemasManager implements DocumentFactory
{
    protected function getTypesConfig()
    {
        return $this->app->config['panneau.documents'];
    }

    protected function getTypeConfig($name)
    {
        return $this->app->config["panneau.documents.$name"];
    }

    protected function getTypeClassFromConfig($config)
    {
        return \Panneau\Contracts\Document\Document::class;
    }

    public function document($name)
    {
        return $this->type($name);
    }

    public function hasDocument($name)
    {
        return $this->hasType($name);
    }

    public function documents()
    {
        return $this->types();
    }
}
