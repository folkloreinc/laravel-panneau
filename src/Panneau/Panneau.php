<?php

namespace App\Panneau;

use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Panneau\Contracts\Panneau as PanneauContract;
use Panneau\Contracts\Definition as DefinitionContract;
use Panneau\Contracts\Resource as ResourceContract;
use Panneau\Events\HandlingRequest;

class Panneau implements PanneauContract
{
    protected $app;

    protected $events;

    protected $resources = [];

    protected $resolvedResources = [];

    protected $booted = false;

    protected $bootedCallbacks = [];

    public function __construct($app, EventsDispatcher $events)
    {
        $this->app = $app;
        $this->events = $events;
        $this->resources = $this->app['config']->get('panneau.resources', []);
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isBooted()) {
            return;
        }

        $this->app['panneau.router']->boot();

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->fireAppCallbacks([$callback]);
        }
    }

    public function serving($listener)
    {
        $this->events->listen(HandlingRequest::class, $listener);
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param  callable[]  $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }

    public function resources(array $resources = null)
    {
        if (!is_null($resources)) {
            $this->resources = array_merge($this->resources, $resources);
            return $this;
        }

        if (!isset($this->resolvedResources)) {
            $this->resolvedResources = $this->resolveResources();
        }
        return $this->resolvedResources;
    }

    public function routes($options = [])
    {
        $this->app['panneau.router']->resources($options['resources'] ?? []);
    }

    public function definition(): DefinitionContract
    {
        return new Definition($this, $this->app);
    }

    public function router()
    {
        return $this->app['panneau.router'];
    }

    public function resource($id): ?ResourceContract
    {
        return $this->resources()->first(function ($resource) use ($id) {
            return $resource->id() === $id;
        });
    }

    protected function resolveResources()
    {
        return collect($this->resources)->map(function ($resource) {
            return is_string($resource) ? $this->app->make($resource) : $resource;
        });
    }
}
