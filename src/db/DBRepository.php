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
use Arrow\ORM\Exception;
use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\DataSet;
use Arrow\ORM\Persistent\PersistentObject;
use Arrow\ORM\Schema\BaseDomainClassGenerator;
use Arrow\ORM\Schema\ISchemaTransformer;
use Arrow\ORM\Schema\Schema;

/**
 * Interfaces OrmPersistent with specific DB handling classes
 *
 */
class DBRepository implements LoggerAwareInterface
{
    /**
     * List of database connections
     *
     * @var Array
     */
    private static $databases = array();
    private static $defaultDb = null;

    /**
     * @var ISchemaTransformer[]
     */
    protected $transformers = [];


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
    private $generatedClassesDir;


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
     * Prevent synchronizer to delete db structures
     *
     * @var bool
     */
    private $preventRemoveActions = true;


    /**
     * Track for schema change
     *
     * @var bool
     */
    public $trackSchemaChange = false;


    /**
     * Youngest schema file modify time
     *
     * @var null
     */
    private $lastSchemaChange = null;

    /**
     * @var LoggerInterface
     */
    private $logger = null;


    private $logLevel = null;

    private $getConfigCallback;


    protected $dbInterface;
    protected $connection;

    public function __construct(
        DBInferface $dbInferface,
        $connection,
        string $generatedClassesDir,
        callable $getConfigCalback
    )
    {
        $this->connectionInterface = $dbInferface;
        $this->connection = $connection;
        $this->generatedClassesDir = $generatedClassesDir;
        $this->getConfigCallback = $getConfigCalback;


    }


    public function log($level, $message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
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


    /**
     * @param $transformer ISchemaTransformer
     * @return $this
     */
    public function addTransformer(ISchemaTransformer $transformer)
    {
        $this->transformers[] = $transformer;
        return $this;
    }


    /**
     * @return String
     */
    public function getgeneratedClassesDir()
    {
        return $this->generatedClassesDir;
    }

    public function select(Criteria $criteria, $asSimpleData = false)
    {
        $class = $criteria->getModel();

        //$this->connectionInterface->setConnection($this->connection);
        $query = $this->connectionInterface->select($class::getTable(), $criteria);

        $res = $this->query($query);
        //return new DataSet($class, $res, $criteria, $asSimpleData);
        return new DataSet($class::getClass(), $res, $criteria, $asSimpleData);

    }


    public function insert(PersistentObject $object)
    {


        $query = $this->connectionInterface->insert($object::getTable(), $object->getData());
        return $this->execute($query);

    }

    public function update($data, Criteria $criteria)
    {
        $class = $criteria->getModel();


        $query = $this->connectionInterface->update($class::getTable(), $data, $criteria);
        return $this->execute($query);
    }


    public function delete(PersistentObject $object)
    {

        $this->connectionInterface->setConnection($this->connection);
        $query = $this->connectionInterface->delete($object::getTable(), Criteria::query($object::getClass())->c($object::getPKField(), $object->getPKey()));

        $this->execute($query);
    }

    public function join(JoinCriteria $criteria, $asSimpleData = false)
    {

        $this->connectionInterface->setConnection($this->connection);

        $query = $this->connectionInterface->join($criteria);

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


        $file = $this->generatedClassesDir . str_replace(array("Arrow\\ORM\\", "\\"), array("", "_"), $class) . ".php";

        if (!file_exists($file)) {
            $this->synchronize();
        }

        if (!file_exists($file)) {
            /*$reader = new SchemaReader();
            $schema = $reader->readSchemaFromFile($this->getSchemaFiles());
            print "<pre>";
            print_r($schema);
            exit();*/


            throw new Exception("File `$file` didn't generated");
        }

        if ($this->trackSchemaChange && $this->lastSchemaChange && $this->lastSchemaChange > filemtime($file)) {
            $this->synchronize();
        }


        require $file;
    }

    public function synchronize()
    {
        $schema = ($this->getConfigCallback)();

        $this->generateBaseModels($schema);
        foreach ($this->transformers as $generator) {
            $generator->transform($schema);
        }

        if ($this->synchronizationEnabled) {
            //todo synchronizacja z innymi bazami
            $synchronizer = $this->connectionInterface->getSynchronizer();

            $synchronizer->setPreventRemoveActions($this->isPreventRemoveActions());
            $synchronizer->setForeignKeysIgnore(true);
            $mismaches = $synchronizer->getSchemaMismatches($schema, $this->connection);
            foreach ($mismaches as $m) {
                $synchronizer->resolveMismatch($m);
            }
        }

    }

    private function generateBaseModels(Schema $schema)
    {
        $generator = new BaseDomainClassGenerator();
        $generator->targetDir = $this->generatedClassesDir;
        $generator->generate($schema);
    }

    private function getSchemaFiles()
    {
        return $this->schemaFiles;
    }


    public function addSchemaFile($file)
    {
        if ($this->trackSchemaChange) {
            $mktime = filemtime($file);
            if (!$this->lastSchemaChange || $mktime > $this->lastSchemaChange) {
                $this->lastSchemaChange = $mktime;
            }
        }

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

    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    public function query($query)
    {
        $this->lastQuery = $query;
        if ($this->logLevel == LogLevel::DEBUG) {
            $start = microtime(true);
        }

        $result = $this->connection->query($query);

        if ($this->logLevel == LogLevel::DEBUG) {
            $time = microtime(true) - $start;
            $this->log(LogLevel::DEBUG, "[ $time s]" . $query, debug_backtrace());
        }

        return $result;
    }

    private function execute($query)
    {
        $this->lastQuery = $query;

        if ($this->logLevel == LogLevel::DEBUG) {
            $start = microtime(true);
        }

        $this->connection->exec($query);

        if ($this->logLevel == LogLevel::DEBUG) {
            $time = microtime(true) - $start;
            $this->log(LogLevel::DEBUG, "[ $time s]" . $query, debug_backtrace());
        }


        return $this->connection->lastInsertId();
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
     * @return DB
     */
    public function setPreventRemoveActions($preventRemoveActions)
    {
        $this->preventRemoveActions = $preventRemoveActions;
        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return null
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * @param null $logLevel
     */
    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
    }


}
