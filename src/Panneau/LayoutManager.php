<?php

namespace Panneau;

use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Panneau\Layouts\Normal as NormalLayout;
use Panneau\Contracts\Layout\Factory as LayoutFactoryContract;
use InvalidArgumentException;
use Closure;

class LayoutManager extends Manager implements LayoutFactoryContract
{
    /**
     * Create an instance of the Imagine Gd driver.
     *
     * @return \Folklore\Mediatheque\Sources\LocalSource
     */
    protected function createNormalDriver($name, $config)
    {
        return new NormalLayout($config);
    }

    /**
     * Get a layout instance.
     *
     * @param  string  $name
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function layout($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($name)
    {
        $config = $this->getConfig($name);
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        } elseif (!is_null($config)) {
            $driver = $config['driver'];
            $method = 'create'.Str::studly($driver).'Driver';

            if (method_exists($this, $method)) {
                return $this->$method($driver, $config);
            }
        }
        throw new InvalidArgumentException("Driver [$name] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->customCreators[$driver]($this->app, $driver);
    }

    /**
     * Get the layout config
     *
     * @param  string  $name
     * @return array|null
     */
    protected function getConfig($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();
        return $this->app['config']['panneau.layouts.' . $name];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['panneau.layout'];
    }

    /**
     * Get the default layout name.
     *
     * @return string
     */
    public function getDefaultLayout()
    {
        return $this->getDefaultDriver();
    }

    /**
     * Set the default driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultLayout($name)
    {
        $this->app['config']['panneau.layout'] = $name;
    }

    /**
     * Get all of the created "layouts".
     *
     * @return array
     */
    public function getLayouts()
    {
        return $this->getDrivers();
    }

    /**
     * Check if a layout exists
     *
     * @param string $name The layout name
     * @return boolean
     */
    public function hasLayout($name)
    {
        return isset($this->app['config']['panneau.layouts.' . $name]);
    }
}
