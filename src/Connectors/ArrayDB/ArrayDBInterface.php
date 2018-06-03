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
use Arrow\ORM\Schema\AbstractSynchronizer;
use Psr\Log\LoggerInterface;
use const PHP_EOL;
use const SORT_DESC;
use function addslashes;
use function array_pop;
use function array_slice;
use function debug_backtrace;
use function in_array;
use function is_numeric;
use function preg_match;
use function str_replace;

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
    public function select($table, Criteria $criteria)
    {
        $matched = [];

        $criteriaData = $criteria->getData();

        //print_r($criteriaData['conditions']);
        foreach ($this->dbArray[$table] as $key => $row) {
            if ($this->passingConditions($criteriaData['conditions'], $row)) {
                $matched[] = $this->dbArray[$table][$key];
            }
        }

        $this->applyListOrder($criteriaData["order"], $matched);
        $this->applyLimit($criteriaData["limit"], $matched);

        return $matched;
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


    private function applyLimit($limit, &$set)
    {
        if ($limit) {
            $set = array_slice($set, $limit[0], $limit[1]);
        }
    }

    private function applyListOrder($order, &$set)
    {

        if (empty($order)) {
            return $set;
        }

        $arguments = [];
        $ordersTmp = [];
        foreach ($order as $entryKey => $orderEntry) {
            foreach ($set as $key => $row) {
                $ordersTmp[$entryKey][$key] = $row[$orderEntry[0]];
            }
            $arguments[] = $ordersTmp[$entryKey];
            $arguments[] = $orderEntry[1] == "ASC" ? SORT_ASC : SORT_DESC;

        }

        $arguments[] = &$set;
        array_multisort(...$arguments);

    }

    private function passingConditions($conditions, $row)
    {

        $conditionsStack = [
            true
        ];
        $operatorsStack = [
            "AND"
        ];

        foreach ($conditions as $condition) {
            if ($condition == "OR" || $condition == "AND") {
                //changing current condition
                $c = count($operatorsStack) - 1;
                $operatorsStack[$c] = $condition;
            } else {
                $condExpression = $condition["condition"];

                if ($condExpression == Criteria::START) {
                    //adding new group on stack
                    $conditionsStack[] = true;
                    $operatorsStack[] = "AND";
                } elseif ($condExpression == Criteria::END) {
                    //removing group from stack - removing current condition ( its no longer valid )
                    array_pop($operatorsStack);
                    //taking result of group validation
                    $closedValue = array_pop($conditionsStack);
                    $c = count($conditionsStack) - 1;
                    //summing group result with last result
                    if (end($operatorsStack) == "AND") {
                        $conditionsStack[$c] = $conditionsStack[$c] && $closedValue;
                    } else {
                        $conditionsStack[$c] = $conditionsStack[$c] || $closedValue;
                    }
                } elseif ($condExpression == Criteria::C_AND_GROUP) {
                    throw new Exception("Not implemented");
                } elseif ($condExpression == Criteria::C_OR_GROUP) {
                    throw new Exception("Not implemented");
                } else {
                    //summing result with currently valid result on stack
                    $c = count($conditionsStack) - 1;
                    if (end($operatorsStack) == "AND") {
                        $conditionsStack[$c] = $conditionsStack[$c] && $this->passingCondition($condition, $row);
                    } else {
                        $conditionsStack[$c] = $conditionsStack[$c] || $this->passingCondition($condition, $row);
                    }
                }
            }

        }


        return $conditionsStack[0];
    }

    private function passingCondition($condition, $row)
    {
        $condExpression = $condition["condition"];
        $condValue = $condition["value"];
        $rowValue = $row[$condition["column"]];

        //TODO enable strong type check
        switch ($condExpression) {
            case Criteria::C_EQUAL:
                if ($condValue === "null") {
                    return is_null($rowValue);
                } else {
                    return $rowValue == $condValue;
                }
                break;
            case Criteria::C_GREATER_EQUAL:
                return $rowValue >= $condValue;
                break;
            case Criteria::C_GREATER_THAN:
                return $rowValue > $condValue;
                break;
            case Criteria::C_LESS_EQUAL:
                return $rowValue <= $condValue;
                break;
            case Criteria::C_LESS_THAN:
                return $rowValue < $condValue;
                break;
            case Criteria::C_IN:
                return in_array($rowValue, $condValue);
                break;
            case Criteria::C_NOT_IN:
                return !in_array($rowValue, $condValue);
                break;
            case Criteria::C_BETWEEN:
                if (is_numeric($rowValue)) {
                    return ($rowValue > $condValue[0] && $rowValue < $condValue[1]);
                } else {
                    return (strcmp($rowValue, $condValue[0]) > 0 && strcmp($rowValue, $condValue[1]) < 0);
                }
                break;
            case Criteria::C_LIKE:
                $pattern = "/" . str_replace(["_", "%"], [".{1}", ".*?"], addslashes($condValue)) . "/ms";
                return preg_match($pattern, $rowValue);
                break;
            case Criteria::C_NOT_LIKE:
                $pattern = "/" . str_replace(["_", "%"], [".{1}", ".*?"], addslashes($condValue)) . "/ms";
                return !preg_match($pattern, $rowValue);
                break;

            default:
                throw new exception("not implemented contidtion: {$condExpression}");
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

function getCalled($depth = 3)
{
    $trace = debug_backtrace();
    for ($i = 0; $i < $depth; $i++) {
        if (!isset($trace[1 + $i]) || !isset($trace[1 + $i]["file"])) {
            break;
        }
        $t = $trace[1 + $i];
        print $t["file"] . ":" . $t["line"] . " (" . "" . ")" . PHP_EOL;
    }

    print PHP_EOL . PHP_EOL;

}

function table($data)
{

    // Find longest string in each column
    $columns = [];
    foreach ($data as $row_key => $row) {
        foreach ($row as $cell_key => $cell) {
            $length = strlen($cell);
            if (empty($columns[$cell_key]) || $columns[$cell_key] < $length) {
                $columns[$cell_key] = $length;
            }
        }
    }

    // Output table, padding columns
    $table = '';
    foreach ($data as $row_key => $row) {
        foreach ($row as $cell_key => $cell) {
            $table .= str_pad($cell, $columns[$cell_key]) . '   ';
        }
        $table .= PHP_EOL;
    }
    return $table;

}


