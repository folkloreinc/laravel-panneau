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

if (!function_exists('page')) {
    /**
     * Get the page instance
     *
     * @param string $name The page name
     * @return array $attributes The page attributes
     * @return \Folklore\Panneau\Support\Page The page instance
     */
    function page($name, $attributes = [])
    {
        $page = app('panneau')->page($name);
        if (!is_null($page)) {
            $page->setAttributes($attributes);
        }
        return $page;
    }
}

if (!function_exists('block')) {
    /**
     * Get the block instance
     *
     * @param string $name The block name
     * @return array $attributes The block attributes
     * @return \Folklore\Panneau\Support\Block The block instance
     */
    function block($name, $attributes = [])
    {
        $block = app('panneau')->block($name);
        if (!is_null($block)) {
            $block->setAttributes($attributes);
        }
        return $block;
    }
}

if (!function_exists('field')) {
    /**
     * Get the field instance
     *
     * @param string $name The field name
     * @return array $attributes The field attributes
     * @return \Folklore\Panneau\Support\Field The field instance
     */
    function field($name, $attributes = [])
    {
        $field = app('panneau')->field($name);
        if (!is_null($field)) {
            $field->setAttributes($attributes);
        }
        return $field;
    }
}

if (!function_exists('bubble')) {
    /**
     * Get the bubble instance
     *
     * @param string $name The bubble name
     * @return array $attributes The bubble attributes
     * @return \Folklore\Panneau\Support\Bubble The bubble instance
     */
    function bubble($name, $attributes = [])
    {
        $bubble = app('panneau')->bubble($name);
        if (!is_null($bubble)) {
            $bubble->setAttributes($attributes);
        }
        return $bubble;
    }
}
