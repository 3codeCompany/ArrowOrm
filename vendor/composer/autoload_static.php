<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit710d163a2908b962c10348a9a943355a
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'Arrow\\ORM\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Arrow\\ORM\\' => 
        array (
            0 => '/',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit710d163a2908b962c10348a9a943355a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit710d163a2908b962c10348a9a943355a::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}