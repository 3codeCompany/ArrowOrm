<?php namespace Arrow\ORM\DB;

    /**
     * @author     Pawel Giemza
     * @version    1.1
     * @package    Arrow
     * @subpackage Orm
     * @link       http://arrowplatform.org/
     * @copyright  2009 3code
     * @license    GNU LGPL
     *
     * @date 2009-06-01
     */
use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\DataSet;
use Arrow\ORM\Schema\BaseDomainClassGenerator;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\SchemaReader;
use Arrow\ORM\Schema\Synchronizers\MysqlSynchronizer;

/**
 * Interfaces OrmPersistent with specific DB handling classes
 *
 */
class DB
{
    /**
     * List of database connections
     *
     * @var Array
     */
    private static $databases = array();
    private static $defaultDb = null;

    private $log = false;

    /**
     * @var \PDO
     */
    private $DB;
    /**
     * @var String Database engine type (mysql)
     */
    private $type;
    /**
     * @var String db orm name
     */
    private $name;

    /**
     * @var String path for generated classes
     */
    private $generatedClassPath;


    /**
     * @var String[] Schema files
     */
    private $schemaFiles = array();


    /**
     * Last executed query
     *
     * @var null
     */
    private $lastQuery = null;

    /**
     * Is synchronization with DB enabled
     *
     * @var bool
     */
    public $synchronizationEnabled = false;


    /**
     * @param      $type
     * @param      $name
     * @param \PDO $pdoReference
     * @param      $baseModelsPath
     * @param bool $isDefault
     *
     * @return DB
     * @throws Exception
     */
    public static function addDB($type, $name, \PDO $pdoReference, $baseModelsPath, $isDefault = false)
    {
        if ($type != Mysql::type()) {
            throw new Exception("Not implemented");
        }

        self::$databases[$name] = new DB($type, $name, $pdoReference, $baseModelsPath);

        if ($isDefault || self::$defaultDb == null) {
            self::$defaultDb = self::$databases[$name];
        }

        return self::$databases[$name];
    }

    /**
     * Return database connection object to database specified in parameter. If not specified default (i.e. first defined in XML file) database will be accessed
     *
     * @param String $dbName
     *
     * @return DB
     */
    public static function getDB($name = false)
    {
        if (!$name) {
            return self::$defaultDb;
        }

        if (isset(self::$databases[$name])) {
            return self::$databases[$name];
        }

        throw new Exception("Database `$name` not added to ORM context");
    }

    public function __construct($type, $name, \PDO $pdoReference, $generatedClassPath)
    {
        $this->type = $type;
        $this->name = $name;
        $this->DB = $pdoReference;
        $this->generatedClassPath = $generatedClassPath;
    }


    /**
     * @return String
     */
    public function getGeneratedClassPath()
    {
        return $this->generatedClassPath;
    }

    public function select(Criteria $criteria, $asSimpleData = false)
    {
        $class = $criteria->getModel();
        $type = $this->type;
        $type::setConnection($this->DB);
        $query = $type::select($class::getTable(), $criteria);

        try {
            $res = $this->query($query);
            return new DataSet($class::getClass(), $res, $criteria, $asSimpleData );
        } catch (\Exception $e) {
            print $e->getMessage() . PHP_EOL . $query;
            exit();
        }
    }


    public function insert(PersistentObject $object)
    {

        $type = $this->type;
        $type::setConnection($this->DB);
        $query = $type::insert($object::getTable(), $object->getData());
        return $this->execute($query);

    }

    public function update($data, Criteria $criteria)
    {
        $class = $criteria->getModel();
        $type = $this->type;
        $type::setConnection($this->DB);
        $query = $type::update($class::getTable(), $data, $criteria);
        return $this->execute($query);
    }


    public function delete(PersistentObject $object)
    {
        $type = $this->type;
        $type::setConnection($this->DB);
        $query = $type::delete($object::getTable(), Criteria::query($object::getClass())->c($object::getPKField(), $object->getPKey()));

        $this->execute($query);
    }

    public function join(JoinCriteria $criteria, $asSimpleData = false)
    {
        $type = $this->type;
        $type::setConnection($this->DB);

        $query = $type::join($criteria);

        //try {
        $res = $this->query($query);
        $class = $criteria->getModel();

        if ($asSimpleData) {
            return new DataSet($class::getClass(), $res, $criteria, $asSimpleData);
        } else {
            return new JoinedDataSet($class::getClass(), $res, $criteria->getResultMode());
        }
        /*        } catch (\Exception $e) {
                    print $e->getMessage() . PHP_EOL . $query;
                    exit();
                }*/
    }

    public function loadBaseModel($class)
    {

        $file = $this->generatedClassPath . str_replace(array("Arrow\\ORM\\", "\\"), array("", "_"), $class) . ".php";

        if (!file_exists($file)) {
            $this->synchronize();
            $reader = new \Arrow\ORM\SchemaReader();
            $schema = $reader->readSchemaFromFile($this->getSchemaFiles());
            $this->generateBaseModels($schema);
        }

        require $file;
    }

    public function synchronize()
    {
        $reader = new SchemaReader();
        $schema = $reader->readSchemaFromFile($this->getSchemaFiles());

        $this->generateBaseModels($schema);

        if($this->synchronizationEnabled){
            //todo synchronizacja z innymi bazami
            $synchronizer = new MysqlSynchronizer( $this->DB );
            $synchronizer->setForeignKeysIgnore(true);
            $mismaches = $synchronizer->getSchemaMismatches($schema, $this->DB);
            foreach ($mismaches as $m) {
                $synchronizer->resolveMismatch($m);
            }
        }

    }

    private function generateBaseModels(Schema $schema)
    {
        $generator = new BaseDomainClassGenerator();
        $generator->targetDir = $this->generatedClassPath;
        $generator->generate($schema);
    }

    private function getSchemaFiles()
    {
        return $this->schemaFiles;
    }

    public function addSchemaFile($file)
    {
        $this->schemaFiles[] = $file;
    }

    public function removeSchemaFile($file)
    {
        $key = array_search($file, $this->schemaFiles);
        if ($key !== false) {
            unset($this->schema[$key]);
        }

        throw new Exception("No schema file `{$file}` added to `{$this->name}` database ", 0);
    }

    public function getLastQuery(){
        return $this->lastQuery;
    }

    public function query($query){
        $this->lastQuery = $query;
        if($this->log){
            $start = microtime(true);
            $time = microtime(true) - $start;
            \FB::log($query,round($time * 1000, 3));
        }
        return $this->DB->query($query);
    }

    private function execute($query){
        $this->lastQuery = $query;
        $this->DB->exec($query);
        return $this->DB->lastInsertId();
    }


}