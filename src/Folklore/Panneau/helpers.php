<?php

if (! function_exists('panneau')) {
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
