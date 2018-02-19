<?php

namespace Arrow\ORM\DB\Connectors;


/**
 * @author     Pawel Giemza
 * @version    1.0
 * @package    Arrow
 * @subpackage Orm
 * @link       http://arrowplatform.org/
 * @copyright  2009 3code
 * @license    GNU LGPL
 *
 * @date 2009-03-06
 */

use Arrow\ORM\DB\DBInferface;
use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\JoinCriteria;
use Arrow\ORM\Schema\AbstractSynchronizer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use function substr;
use function var_dump;

/**
 * Generates MySQL satement using ORM objects
 *
 * This class connects ORM to Mysql databases and generates sql statement from ORM objects (criteria, selector).
 */
class ArrayDBInterface implements DBInferface
{



    private $dbArray;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __construct($connection)
    {
        $this->dbArray = $connection;

/*        $this->logger = new Logger('name');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        $this->logger->info( "inicjuje", $this->dbArray);*/

    }

    /**
     * @return array
     */
    public function getDB()
    {
        $this->logger && $this->logger->info( "Pobieram", $this->dbArray);
        return $this->dbArray;
    }


    /**
     * Returns rows
     *
     * @param String $table
     * @param Criteria $criteria
     *
     * @return Array
     */
    public function select($table, $criteria)
    {
        return $this->dbArray[$table];
    }

    /**
     * Inserts new row into database
     *
     * @param String $table
     * @param Array $arrData
     *
     * @return int (id of object inserted into table)
     */
    public function insert($table, $data): string
    {
        $this->dbArray[$table][] = $data;

        $this->logger && $this->logger->info( "dodaje", $this->dbArray);

        $keys = array_keys($this->dbArray[$table]);

        return end($keys);
    }

    /**
     * Updates db
     *
     * @param String $table
     * @param Array $data Data to replace
     * @param Criteria $criteria
     *
     * @return String
     */
    public function update($table, array $data, $criteria){

        $criteriaData = $criteria->getData();
        if (isset($criteriaData['conditions'])) {
            print_r($criteriaData['conditions']);
        }

        $this->logger && $this->logger->info( "aktualizuje", $this->dbArray);

    }

    /**
     * Deletes rows specified by Criteria
     *
     * @param String $table
     * @param Criteria $criteria
     */
    public function delete($table, $criteria)
    {
    }


    public function getSynchronizer(): AbstractSynchronizer
    {
        return null;
    }

}
