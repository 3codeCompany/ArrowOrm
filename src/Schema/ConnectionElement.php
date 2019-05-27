<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 23.05.2017
 * Time: 15:21
 */

namespace Arrow\ORM\Schema;


class ConnectionElement implements ISchemaElement
{

    /**
     * @var Table
     */
    protected $table;
    protected $local;
    protected $foreign;
    protected $additionalConditions = [];


    function __construct(Table $table, $local, $foreign, $additionalConditions)
    {
        $this->table = $table;
        $this->foreign = $foreign;
        $this->local = $local;
        $this->additionalConditions = $additionalConditions;
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
     * @return ConnectionElement
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
     * @return ConnectionElement
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
     * @return ConnectionElement
     */
    public function setForeign($foreign)
    {
        $this->foreign = $foreign;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalConditions()
    {
        return $this->additionalConditions;
    }

    /**
     * @param array $additionalConditions
     * @return ConnectionElement
     */
    public function setAdditionalConditions($additionalConditions)
    {
        $this->additionalConditions = $additionalConditions;
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