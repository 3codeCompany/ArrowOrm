<?php
namespace Arrow\ORM\Interfaces;
/**
 * SchemaWriter interface
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
interface ISchemaWriter
{
    /**
     * Writes schema to given file
     *
     * @param Schema $schema
     * @param string $file
     */
    public function writeSchemaToFile(Schema $schema, $file);
}

?>