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

    public $useDBFKeys;

    /**
     * @var ConnectionElement[]
     */
    public $elements = [];
    public $name = "";

    public function toArray()
    {
        // TODO: Implement toArray() method.
    }

    public function toString()
    {
        // TODO: Implement toString() method.
    }

    /**
     * @return mixed
     */
    public function isUsingDBFKeys()
    {
        return $this->useDBFKeys;
    }

    /**
     * @param mixed $useDBFKeys
     */
    public function setUseDBFKeys($useDBFKeys): void
    {
        $this->useDBFKeys = $useDBFKeys;
    }

    /**
     * @return ConnectionElement[]
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * @param ConnectionElement[] $elements
     */
    public function setElements(array $elements): void
    {
        $this->elements = $elements;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }





}