<?php
namespace Arrow\ORM;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 14.08.12
 * Time: 10:41
 * To change this template use File | Settings | File Templates.
 */
class Loader
{

    private static $classes = null;

    public static function registerAutoload()
    {
        self::$classes = array(
            'arrow\\orm\\debug\\resultprinter'     => '/debug/ResultPrinter.php',
            'arrow\\orm\\abstractmismatch'         => '/schema/AbstractMismatch.php',
            'arrow\\orm\\abstractsynchronizer'     => '/schema/AbstractSynchonizer.php',
            'arrow\\orm\\basedomainclassgenerator' => '/schema/BaseDomainClassGenerator.php',
            'arrow\\orm\\basetracker'              => '/schema/behaviours/BaseTracker.php',
            'arrow\\orm\\criteria'                 => '/Criteria.php',
            'arrow\\orm\\dataset'                  => '/persistent/DataSet.php',
            'arrow\\orm\\joineddataset'            => '/persistent/JoinedDataSet.php',
            'arrow\\orm\\datasourcemismatch'       => '/schema/DatasourceMismatch.php',
            'arrow\\orm\\db'                       => '/db/DB.php',
            'arrow\\orm\\isqlgenerator'            => '/db/ISQLGenerator.php',
            'arrow\\orm\\exception'                => '/Exception.php',
            'arrow\\orm\\field'                    => '/schema/Field.php',
            'arrow\\orm\\foreignkey'               => '/schema/ForeignKey.php',
            'arrow\\orm\\foreignkeyreference'      => '/schema/ForeignKeyReference.php',
            'arrow\\orm\\ibehaviour'               => '/interfaces/IBehaviours.php',
            'arrow\\orm\\idatabaseconnector'       => '/persistent/dbConnectors/IDatabaseConnector.php',
            'arrow\\orm\\iextension'               => '/schema/behaviours/IExtension.php',
            'arrow\\orm\\index'                    => '/schema/Index.php',
            'arrow\\orm\\ischemaelement'           => '/interfaces/ISchemaElement.php',
            'arrow\\orm\\ischemareader'            => '/interfaces/ISchemaReader.php',
            'arrow\\orm\\ischemawriter'            => '/interfaces/ISchemaWriter.php',
            'arrow\\orm\\itracker'                 => '/schema/behaviours/ITracker.php',
            'arrow\\orm\\joincriteria'             => '/JoinCriteria.php',
            'arrow\\orm\\joinrelation'             => '/JoinRelation.php',
            'arrow\\orm\\loader'                   => '/Loader.php',
            'arrow\\orm\\rowset'                   => '/RowSet.php',
            'arrow\\orm\\mysql'                    => '/db/Mysql.php',
            'arrow\\orm\\mysqlconnector'           => '/persistent/dbConnectors/MysqlConnector.php',
            'arrow\\orm\\mysqlsynchronizer'        => '/schema/synchronizers/MysqlSynchronizer.php',
            'arrow\\orm\\persistentfactory'        => '/persistent/PersistentFactory.php',
            'arrow\\orm\\persistentobject'         => '/persistent/PersistentObject.php',
            'arrow\\orm\\resolvedmismatch'         => '/schema/ResolvedMismatch.php',
            'arrow\\orm\\schema'                   => '/schema/Schema.php',
            'arrow\\orm\\schemaexception'          => '/schema/SchemaException.php',
            'arrow\\orm\\schemamismatch'           => '/schema/SchemaMismatch.php',
            'arrow\\orm\\schemareader'             => '/schema/SchemaReader.php',
            'arrow\\orm\\schemawriter'             => '/schema/SchemaWriter.php',
            'arrow\\orm\\table'                    => '/schema/Table.php',
            'arrow\\orm\\tree'                     => '/schema/behaviours/extensions/Tree.php',
            'arrow\\orm\\extensions\\treenode'     => '/extensions/TreeNode.php',
            'arrow\\orm\\extensions\\sortable'     => '/extensions/Sortable.php'
        );
        spl_autoload_register(array(__CLASS__, 'includeClass'));

    }

    //@codeCoverageIgnoreStart
    public static function unregisterAutoload()
    {
        return spl_autoload_unregister(array(__CLASS__, 'includeClass'));
    }

    // @codeCoverageIgnoreEnd

    public static function includeClass($class)
    {
        if (strpos($class, "Arrow\\ORM\\ORM_") === 0) {

            DB::getDB()->loadBaseModel($class);
            //\Arrow\Models\Loader::registerLoadedClass($class, $file);
        }
        $cn = strtolower($class);
        if (isset(self::$classes[$cn])) {
            /*\Arrow\Models\Loader::registerLoadedClass($cn, __DIR__ . "/" . self::$classes[$cn]);*/
            require __DIR__ . "/" . self::$classes[$cn];
        }

    }


}
