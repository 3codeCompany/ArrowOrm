<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 23.05.2017
 * Time: 15:21
 */

namespace Arrow\ORM\Schema;


class Connection implements ISchemaElement
{

    /**
     * @var ConnectionTable[]
     */
    public $tables = [];
    public $name = "";

    public function toArray()
    {
        // TODO: Implement toArray() method.
    }

    public function toString()
    {
        // TODO: Implement toString() method.
    }


}