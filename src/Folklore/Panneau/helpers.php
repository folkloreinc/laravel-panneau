<?php

if (!function_exists('panneau')) {
    /**
     * Get the panneau instance
     *
     * @return \Folklore\Panneau\Panneau The panneau instance
     */
    function panneau()
    {
        return app('panneau');
    }
}

// Missing method in Laravel 5.2
if (! function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param  string  $name
     * @return mixed
     */
    function resolve($name)
    {
        return app($name);
    }
}
