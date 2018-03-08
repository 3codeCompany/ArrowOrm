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

use Arrow\ORM\DB\DBInterface;
use Arrow\ORM\Exception;
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
class ArrayDBInterface implements DBInterface
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
        $this->logger && $this->logger->info("Pobieram", $this->dbArray);
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
    public function insert(string $table, array $data, string $pKeyField): string
    {
        if (isset($data[$pKeyField]) && $data[$pKeyField] != null) {
            $this->dbArray[$table][$data[$pKeyField]] = $data;
        } else {
            $this->dbArray[$table][] = $data;
        }


        $this->logger && $this->logger->info("dodaje", $this->dbArray);

        $keys = array_keys($this->dbArray[$table]);

        $pKey = end($keys);

        $this->dbArray[$table][$pKey][$pKeyField] = $pKey;

        return $pKey;
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
    public function update($table, array $data, $criteria)
    {
        $criteriaData = $criteria->getData();
        foreach ($this->dbArray[$table] as $key => $row) {
            if ($this->passingConditions($criteriaData['conditions'], $row)) {
                foreach ($data as $fieldName => $fieldValue) {
                    $this->dbArray[$table][$key][$fieldName] = $fieldValue;
                }
            }
        }

        $this->logger && $this->logger->info("aktualizuje", $this->dbArray);

    }

    /**
     * Deletes rows specified by Criteria
     *
     * @param String $table
     * @param Criteria $criteria
     */
    public function delete($table, $criteria)
    {
        $criteriaData = $criteria->getData();
        foreach ($this->dbArray[$table] as $key => $row) {
            if ($this->passingConditions($criteriaData['conditions'], $row)) {
                unset($this->dbArray[$table][$key]);
            }
        }

    }


    private function passingConditions($conditions, $row)
    {
        foreach ($conditions as $condition) {
            if (!$this->passingCondition($condition, $row)) {
                return false;
            }
        }
        return true;
    }

    private function passingCondition($condition, $row)
    {
        $condExpression = $condition["condition"];
        $condValue = $condition["value"];
        $rowValue = $row[$condition["column"]];


        switch ($condExpression) {
            case Criteria::C_EQUAL:
                if ($condValue === "null") {
                    return is_null($rowValue);
                } else {
                    return $rowValue === $condValue;
                }
                break;
            default:
                throw new exception("not implemented");
        }

    }

    private function expressi()
    {
        switch ($condition) {

            case Criteria::C_EQUAL:
                if ($value === "NULL") {
                    $conditionString .= $column . " IS NULL ";
                } else {
                    $conditionString .= $column . " = " . $value;
                }
                break;
            case Criteria::C_NOT_EQUAL:
                if ($value === "NULL") {
                    $conditionString .= $column . " IS NOT NULL ";
                } else {
                    $conditionString .= $column . " != " . $value;
                }
                break;
            case Criteria::C_GREATER_EQUAL:
                $conditionString .= $column . " >= " . $value;
                break;
            case Criteria::C_GREATER_THAN:
                $conditionString .= $column . " > " . $value;
                break;
            case Criteria::C_LESS_EQUAL:
                $conditionString .= $column . " <= " . $value;
                break;
            case Criteria::C_LESS_THAN:
                $conditionString .= $column . " < " . $value;
                break;
            case Criteria::C_IN:
                //_add null handling
                $valuesIn = array();
                $null = false;
                foreach ($value as $addVal) {
                    if ($addVal === 'NULL') {
                        $null = true;
                    } else {
                        if (trim($addVal) !== '') {
                            $valuesIn[] = $addVal;
                        }
                    }
                }
                if (!empty ($valuesIn)) {
                    $addCondition = $column . " IN (" . implode(", ", $valuesIn) . ")";
                } else {
                    $addCondition = ' 0 ';
                }
                if ($null) {
                    $addCondition = "(" . $addCondition . "OR {$column} IS NULL " . ")";
                }
                $conditionString .= $addCondition;
                break;
            case Criteria::C_NOT_IN:
                $valuesIn = [];
                $null = false;
                foreach ($value as $addVal) {
                    if ($addVal === 'NULL') {
                        $null = true;
                    } else {
                        if (trim($addVal) !== '') {
                            $valuesIn[] = $addVal;
                        }
                    }
                }
                if (!empty($valuesIn)) {
                    $addCondition = $column . " NOT IN (" . implode(", ", $valuesIn) . ")";
                } else {
                    $addCondition = ' 1 ';
                }
                if ($null) {
                    $addCondition = "(" . $addCondition . "AND {$column} IS NOT NULL " . ")";
                }
                $conditionString .= $addCondition;
                break;
            case Criteria::C_BETWEEN:
                $conditionString .= $column . " BETWEEN {$value[0]} AND {$value[1]} ";
                break;
            case Criteria::C_LIKE:
                $conditionString .= $column . " LIKE " . $value;
                break;
            case Criteria::C_NOT_LIKE:
                $conditionString .= $column . " NOT LIKE " . $value;
                break;
            case Criteria::C_BIT_OR:
                $conditionString .= $column . " | " . $value[0] . " = " . $value[1];
                break;
            case Criteria::C_BIT_AND:
                $conditionString .= $column . " & " . $value;
                break;
            case Criteria::C_BIT_XOR:
                $conditionString .= $column . " ^ " . $value[0] . " = " . $value[1];
                break;
            case Criteria::C_OR_GROUP:
                $conditionString .= " OR ";
                break;
            case Criteria::C_AND_GROUP:
                $conditionString .= " AND ";
                break;
            case Criteria::START:
                $conditionString .= /* (($index>0)?" $value ":""). */
                    " ( ";
                break;
            case Criteria::END:
                $conditionString .= " )";
                break;
            case Criteria::C_CUSTOM:

                $conditionString .= $value;
                break;
            default:
                new \Exception("Criteria: Not recognized condition `$condition`", 0);
        }

    }


    public function getSynchronizer(): AbstractSynchronizer
    {
        return null;
    }

}
