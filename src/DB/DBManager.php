<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 18.02.2018
 * Time: 11:52
 */

namespace Arrow\ORM\DB;


class DBManager
{

    /**
     * @var DBRepository[]
     */
    protected static $repositories;
    /**
     * @var string
     */
    protected static $defaultRepository;


    public static function addRepository($name, DbRepository $repository)
    {
        if (empty(self::$defaultRepository)) {
            self::$defaultRepository = $name;
        }
        self::$repositories[$name] = $repository;

    }

    public static function getRepository($name)
    {
        return self::$repositories[$name];
    }

    public static function setDefaultRepository($repo)
    {
        self::$defaultRepository = $repo;
    }

    public static function getDefaultRepository()
    {
        return self::$repositories[self::$defaultRepository];
    }
}