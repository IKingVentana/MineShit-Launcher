<?php
namespace app\modules;

use std, gui, framework, app;


class AppVersion extends AbstractModule
{
    const VERSION = '1.0.13'; // Здесь всегда самая актуальная версия!
    
    /**
     * Получить версию приложения
     */
    public static function get()
    {
        return self::VERSION;
    }
}