<?php
namespace Folklore\Panneau;

use Composer\Script\Event;

class PanneauInstaller
{
    /**
     * Install panneau
     *
     * @param \Composer\Script\Event $event
     * Event to echo output.
     */
    public static function install(Event $event)
    {
        self::installNpmPackage();
        self::copyFiles();
        self::copyTranslations();
    }

    /**
     * Update panenau
     *
     * @param \Composer\Script\Event $event
     * Event to echo output.
     */
    public static function update(Event $event)
    {
        self::updateNpmPackage();
        self::copyFiles();
        self::copyTranslations();
    }

    public static function installNpmPackage()
    {
        exec('npm install panneau');
    }

    public static function updateNpmPackage()
    {
        exec('npm update panneau');
    }

    public static function copyFiles()
    {
        $panneauPath = self::getRootPath().'/node_modules/panneau/dist/';
        $vendorPath = self::getPackagePath().'/src/vendor/';
        echo shell_exec('cp -R '.$panneauPath.' '.$vendorPath);
    }

    public static function copyTranslations()
    {
        $scriptPath = self::getPackagePath().'/scripts/copyTranslations.js';
        echo shell_exec($scriptPath);
    }

    protected static function getRootPath()
    {
        return getcwd();
    }

    protected static function getPackagePath()
    {
        return __DIR__.'/..';
    }
}
