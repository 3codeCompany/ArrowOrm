<?php
namespace Arrow\ORM;
use Arrow\ORM\DB\DB;
use Arrow\ORM\DB\DBManager;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 14.08.12
 * Time: 10:41
 * To change this template use File | Settings | File Templates.
 */
class Loader
{
    private static $classes = null;
    private static $dir;

    public static function registerAutoload($dir)
    {
        self::$dir = $dir;
        spl_autoload_register(array(__CLASS__, 'includeClass'));
    }

    //@codeCoverageIgnoreStart
    public static function unregisterAutoload()
    {
        return spl_autoload_unregister(array(__CLASS__, 'includeClass'));
    }

    // @codeCoverageIgnoreEnd

    public static function includeClass($class)
    {
        if (strpos($class, "Arrow\\ORM\\ORM_") === 0) {
            $file = self::$dir . DIRECTORY_SEPARATOR . str_replace(array("Arrow\\ORM\\", "\\"), array("", "_"), $class) . ".php";
            if (file_exists($file)) {
                require $file;
            } else {
                DBManager::getDefaultRepository()->loadBaseModel($class);
            }
        }
    }
}
