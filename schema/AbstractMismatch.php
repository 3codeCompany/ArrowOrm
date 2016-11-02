<?php namespace Arrow\ORM;
/**
 * Abstact mismatch ( schema -> db , or db -> schema )
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
abstract class AbstractMismatch
{

    /**
     * Element doesn't exists
     *
     * @var int
     */
    const NOT_EXISTS = 1;
    /**
     * Element doesn't equals with schema ( difrent size, type .... )
     *
     * @var int
     */
    const NOT_EQUALS = 2;
    /**
     * Elements index doesn't equals ( elements not in right order )
     *
     * @var int
     */
    const INDEX_NOT_EQUALS = 3;
    /**
     * If oldName is used and matched we can use mismatch name type
     *
     * @var int
     */
    const NAME_NOT_EQUALS = 4;

    /**
     * Schema
     *
     * @var Schema
     */
    public $schema;

    /**
     * Datasource
     *
     * @var \PDO
     */
    public $datasource;

    /**
     * Returning mismatch data as array
     *
     * @return array
     */
    abstract public function toArray();

    /**
     * Returning mismatch information in string
     *
     * @return string
     */
    abstract public function __toString();
}

?>