<?php namespace Arrow\ORM;
/**
 * SchemaReader interface
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
interface ISchemaReader
{

    /**
     * Reads schema from file
     *
     * @param string $file
     *
     * @return Schema
     */
    public function readSchemaFromFile($file);

}

?>