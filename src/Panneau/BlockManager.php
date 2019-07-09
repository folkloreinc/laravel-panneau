<?php namespace Panneau;

use Panneau\Support\SchemasManager;
use Panneau\Contracts\Block\Factory as BlockFactory;

class BlockManager extends SchemasManager implements BlockFactory
{
    protected function getTypesConfig()
    {
        return $this->app->config['panneau.blocks'];
    }

    protected function getTypeConfig($name)
    {
        return $this->app->config["panneau.blocks.$name"];
    }

    protected function getTypeClassFromConfig($config)
    {
        return \Panneau\Contracts\Block\Block::class;
    }

    public function block($name)
    {
        return $this->type($name);
    }

    public function hasBlock($name)
    {
        return $this->hasType($name);
    }

    public function blocks()
    {
        return $this->types();
    }
}
