<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 23.05.2017
 * Time: 15:21
 */

namespace Arrow\ORM\Schema;


class ConnectionTable implements ISchemaElement
{

    /**
     * @var Table
     */
    protected $table;
    protected  $local;
    protected  $foreign;


    function __construct(Table $table, $local, $foreign)
    {
        $this->table = $table;
        $this->foreign = $foreign;
        $this->local = $local;
    }

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param Table $table
     * @return ConnectionTable
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocal()
    {
        return $this->local;
    }

    /**
     * @param mixed $local
     * @return ConnectionTable
     */
    public function setLocal($local)
    {
        $this->local = $local;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getForeign()
    {
        return $this->foreign;
    }

    /**
     * @param mixed $foreign
     * @return ConnectionTable
     */
    public function setForeign($foreign)
    {
        $this->foreign = $foreign;
        return $this;
    }





    public function toArray()
    {
        // TODO: Implement toArray() method.
    }

    public function toString()
    {
        // TODO: Implement toString() method.
    }


}