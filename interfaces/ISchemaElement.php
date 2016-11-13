<?php
namespace Arrow\ORM\Interfaces;
/**
 * Schema element interface
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
interface ISchemaElement
{

    /**
     * Returns element properties to array
     *
     * @return array
     */
    public function toArray();

    /**
     * Returns element information
     *
     * @return string
     */
    public function toString();
}

?>