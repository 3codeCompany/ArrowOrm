<?php namespace Arrow\ORM\Persistent;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 27.08.12
 * Time: 09:43
 * To change this template use File | Settings | File Templates.
 */
class JoinRelation
{
    public $baseClass;
    public $baseField;

    public $joinClass;
    public $joinField;

    public function __construct($baseClass, $baseField, $joinClass, $joinField)
    {
        $this->baseClass = $baseClass;
        $this->baseField = $baseField;
        $this->joinClass = $joinClass;
        $this->joinField = $joinField;

    }
}
