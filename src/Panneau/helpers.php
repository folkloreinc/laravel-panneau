<?php

if (!function_exists('panneau')) {
    /**
     * Get the panneau instance
     *
     * @return \Panneau\Panneau The panneau instance
     */
    function panneau($service = null)
    {
        return app(!is_null($service) ? 'panneau.'.$service : 'panneau');
    }
}

if (!function_exists('document')) {
    /**
     * Get the document instance
     *
     * @param string $name The document name
     * @return array $attributes The document attributes
     * @return \Panneau\Support\Document The document instance
     */
    function document($name, $attributes = [])
    {
        $document = app('panneau')->document($name);
        if (!is_null($document)) {
            $document->setAttributes($attributes);
        }
        return $document;
    }
}

if (!function_exists('block')) {
    /**
     * Get the block instance
     *
     * @param string $name The block name
     * @return array $attributes The block attributes
     * @return \Panneau\Support\Block The block instance
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
     * @return \Panneau\Support\Schemas\Field The field instance
     */
    function field($name, $attributes = [])
    {
        $field = app('panneau')->hasField($name)
            ? app('panneau')->field($name)
            : app($name);
        if (!is_null($field)) {
            $field->setAttributes($attributes);
        }
        return $field;
    }
}
