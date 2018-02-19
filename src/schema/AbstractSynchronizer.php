<?php
namespace Arrow\ORM\Schema;
/**
 * Base class for all synchronizers
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
abstract class AbstractSynchronizer
{

    /**
     * Synchronization mode
     * Schema have prioryty
     *
     * @var int
     */
    const MODE_SCHEMA_TO_DS = 0;


    /**
     * Synchronization mode
     * Datasource have prioryty
     *
     * @var int
     */
    const MODE_DS_TO_SCHEMA = 1;

    /**
     * Synchronization mode
     * Everything adden will be synchronize to schema or datasource
     * to delete element, have to delete it in both.
     *
     * @var int
     */
    const MODE_ALL = 1;

    private $ignoreForeignKeys = false;




    /**
     * Resolve single mismatch
     *
     * @param \PDO $ds
     * @param AbstractMismatch $mismatch
     * @param int $mode
     *
     * @return ResolvedMismatch
     */
    abstract public function resolveMismatch(AbstractMismatch $mismatch, $mode = self::MODE_SCHEMA_TO_DS);

    /**
     * Return all mismatches ( SchemaMismatch and DatasourceMismatch )
     * <code>
     * <?php namespace Arrow\ORM;
     * $mismatches = $synchro->getSchemaMismatches($db, $schema);
     * foreach($mismatches as $mismatch){
     *      print $mismatch->toString()."\n";
     * }
     *
     * @param Schema $schema
     * @param \PDO $ds
     *
     * @return AbstractMismatch []
     */
    abstract public function getSchemaMismatches(Schema $schema);

    /**
     * Synchronize datasource with schema using mode flag
     *
     * @param Schema $schema
     * @param \PDO $ds
     * @param int $mode
     *
     * @return ResolvedMismatch []
     */
    abstract public function synchronize(Schema $schema, $mode = self::MODE_SCHEMA_TO_DS);

    public function setForeignKeysIgnore($set)
    {
        $this->ignoreForeignKeys = $set;
    }

    public function getForeignKeysIgnore()
    {
        return $this->ignoreForeignKeys;
    }

    /**
     * @return boolean
     */
    public function isPreventRemoveActions()
    {
        return $this->preventRemoveActions;
    }

    /**
     * @param boolean $preventRemoveActions
     */
    public function setPreventRemoveActions($preventRemoveActions)
    {
        $this->preventRemoveActions = $preventRemoveActions;
    }

}

?>