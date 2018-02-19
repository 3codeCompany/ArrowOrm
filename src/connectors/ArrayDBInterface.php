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
use function substr;
use function var_dump;

/**
 * Generates MySQL satement using ORM objects
 *
 * This class connects ORM to Mysql databases and generates sql statement from ORM objects (criteria, selector).
 */
class ArrayDBInterface implements DBInferface
{

    /**
     * @var \PDO
     */
    private static $connection;

    /**
     * @param \PDO $connection
     */
    public  function setConnection(\PDO $connection)
    {
        self::$connection = $connection;
    }

    /**
     * Returns rows
     *
     * @param String $table
     * @param Criteria $criteria
     *
     * @return Array
     */
    public  function select($table, $criteria)
    {
        $data = $criteria->getData();
        $joins = "";
        if (isset($data["joins"])) {
            $joins = "";
            $parseColumn = function ($column, $table) {

                if (strpos($column, ":") == false) {
                    if (strpos($column, "raw:") === 0) {
                        return self::$connection->quote(substr($column, 4));
                    } else if ($column[0] == "'") {
                        return self::$connection->quote(trim($column, "'"));
                    }
                    return "`{$table}`.`{$column}`";
                } else {
                    $tmp = explode(":", $column);
                    if ($tmp[0] == "raw") {
                        return self::$connection->quote(substr($column, 4));
                    } else {
                        return "`" . $tmp[0] . "`" . ".`" . $tmp[1] . "`";
                    }
                }
            };
            foreach ($data["joins"] as $j) {

                $tmp = [];
                foreach ($j["on"] as $field => $foreignField) {
                    $tmp[] = $parseColumn($field, $table) . " = " . $parseColumn($foreignField, $j["as"]);
                }
                $on = implode(" and ", $tmp);


                $joins .= "\n " . ($j["type"] == JoinCriteria::J_OUTER ? '' : $j["type"]) . " JOIN `" . $j["class"]::getTable() . "` as `" . $j["as"] . "` ON ( " . $on . " " . $j["customCondition"] . " )";
            }

        }
        if (isset($data["customJoins"])) {
            foreach ($data["customJoins"] as $j) {
                $joins .= "\n" . $j["queryFragment"];
            }
        }
        /*        if($table == "shop_allegro_auctions") {
                    $q = "SELECT " . ($criteria->isAggregated() ? 'SQL_CALC_FOUND_ROWS ' : '') . self::columnsToSQL($criteria) . " FROM $table $joins\n WHERE " . self::conditionsToSQL($criteria) . self::groupsToSQL($table, $criteria);
                    \ADebug::log($q);
                }*/

        return "SELECT " . ($criteria->isAggregated() ? 'SQL_CALC_FOUND_ROWS ' : '') . self::columnsToSQL($criteria) . " FROM $table $joins\n WHERE " . self::conditionsToSQL($criteria) . self::groupsToSQL($table, $criteria);

    }

    /**
     * Inserts new row into database
     *
     * @param String $table
     * @param Array $arrData
     *
     * @return int (id of object inserted into table)
     */
    public  function insert($table, $data)
    {
        $query = "";
        if (empty($data)) {
            $query = "INSERT INTO $table () VALUES ()";
        } else {
            $query = "INSERT INTO $table (`" . implode("`, `", array_keys($data)) . "`) ";
            $query .= "\n VALUES ( ";

            $first = true;
            foreach ($data as $str) {
                if (!$first) {
                    $query .= ", ";
                }

                if (is_null($str)) {
                    $query .= "NULL";
                } else {

                    $query .= (is_int($str) || is_float($str)) ? $str : self::$connection->quote($str);

                }

                $first = false;
            }
            $query .= " )";
        }


        return $query;
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
    public  function update($table, array $data, $criteria)
    {


        $query = "UPDATE $table SET ";
        $first = true;

        foreach ($data as $column => $value) {
            $query .= (($first) ? '' : ', ') . "`$column`" . '=';

            if (is_null($value)) {
                $query .= "NULL";
            } else {
                $query .= (is_int($value) || is_float($value)) ? $value : self::$connection->quote($value);
            }

            $first = false;
        }

        $query .= " WHERE " . self::conditionsToSQL($criteria);

        if (isset($_REQUEST["arrTest"])) {
            print $query . "<br />";
        }

        return $query;
    }

    /**
     * Deletes rows specified by Criteria
     *
     * @param String $table
     * @param Criteria $criteria
     */
    public  function delete($table, $criteria)
    {
        $query = "DELETE FROM $table WHERE " . self::conditionsToSQL($criteria);
        return $query;
    }

    private  function functionToSQL($column, $function, $data)
    {
        switch ($function) {
            case Criteria::F_DAY:
                return "DAY({$column})";
            case Criteria::F_MONTH:
                return "MONTH({$column})";
            case Criteria::F_YEAR:
                return "YEAR({$column})";
            case Criteria::F_DATE:
                return "DATE({$column})";
        }
        return $column;
    }

    private  function valueToSQL($value, $condition, $function)
    {
        switch ($function) {
            case Criteria::F_DAY:
                return ($value instanceof \DateTime) ? $value->format("d") : $value;
            case Criteria::F_MONTH:
                return ($value instanceof \DateTime) ? $value->format("m") : $value;
            case Criteria::F_YEAR:
                return ($value instanceof \DateTime) ? $value->format("Y") : $value;
            case Criteria::F_DATE:
                return ($value instanceof \DateTime) ? $value->format("Y-m-d") : $value;
        }
        return $value;
    }

    private  function getSqlValue($value)
    {

        if (is_null($value)) {
            return "NULL";
        }

        //todo problem with text columns when numeric value searched
        //eg login=123 result is 123 and 123fa etc.
        /*if (is_numeric($value)) {
            return $value;
        }*/

        return self::$connection->quote($value);

    }

    /**
     * Creates sql condition
     *
     * @param Criteria $criteria
     *
     * @return String
     */
    public  function conditionsToSQL(Criteria $criteria, $aliases = array())
    {


        //$aliases
        $table = false;
        $model = $criteria->getModel();
        $table = $model::getTable();


        $criteriaData = $criteria->getData();
        $conditionString = "";

        if (isset($criteriaData['conditions'])) {
            foreach ($criteriaData['conditions'] as $cond) {

                $conditionString .= "\n\t";

                /*if(empty($cond["column"]))
                    continue;*/

                if (is_string($cond)) {
                    $conditionString .= " $cond ";
                    continue;
                }

                $condition = $cond['condition'];


                if ($cond['column'] && $cond['column'][0] == "'") {
                    $column = trim($cond['column'], "'");
                } elseif (strpos($cond['column'], "raw:") === 0) {
                    $column = substr($cond['column'], 4);
                } else {

                    $column = "`" . $cond['column'] . "`";


                    if ($table != false && $condition != Criteria::C_AND_GROUP && $condition != Criteria::C_OR_GROUP && $condition != Criteria::START && $condition != Criteria::END) {
                        if (isset($aliases[$table])) {
                            $column = $aliases[$table]["alias"] . '.' . $column;
                        } else {
                            if (strpos($column, ":") == false) {
                                $column = "{$model::getTable()}.{$column}";
                            } else {
                                $tmp = explode(":", $column);
                                $column = $tmp[0] . "`" . ".`" . $tmp[1];
                            }
                        }
                    } else {
                        if (strpos($column, ":") == false) {
                            $column = "{$model::getTable()}.{$column}";
                        } else {
                            $tmp = explode(":", $column);
                            $column = $tmp[0] . "`" . ".`" . $tmp[1];
                        }
                    }

                    //if function passed
                    if (isset($cond["function"]) && $cond["function"]) {
                        $column = self::functionToSQL($column, $cond['function'], $cond["functionData"]);
                    }
                }

                $value = self::valueToSQL($cond['value'], $condition, isset($cond["function"]) ? $cond["function"] : null);
                if ($condition != Criteria::C_CUSTOM && $condition != Criteria::START) {
                    if (!is_array($value)) {
                        $value = self::getSqlValue($value);
                    } else {
                        foreach ($value as &$el) {
                            $el = self::getSqlValue($el);
                        }
                    }
                }


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
        }

        if (empty($conditionString)) {
            $conditionString = '1';
        }

        return $conditionString;
    }

    public  function columnsToSQL($criterias, $aliases = false)
    {

        if (!($criterias instanceof JoinCriteria)) {
            $criterias = array($criterias);
        }

        //print_r($criterias);
        $columns = false;
        $first = true;

        foreach ($criterias as $criteria) {
            $criteriaData = $criteria->getData();
            $class = $criteria->getModel();
            $tableName = $class::getTable();
            $prefix = "";

            //lets say custom joins dont need extra code
            $joined = isset($criteriaData["joins"]) || isset($criteriaData["customJoins"]);

            if (!isset($criteriaData['columns'])) {
                continue;
            }

            foreach ($criteriaData['columns'] as $col) {


                if (strpos($col['column'], "raw:") === 0) {
                    $tmp = substr($col['column'], 4);
                } elseif ($col['column'][0] == "(" || $col['custom'] == true) {
                    $tmp = $col['column'];
                } elseif ($col['column'][0] == "'") {
                    $tmp = trim($col['column'], "'");
                } elseif ($joined && strpos($col['column'], ":") !== false) {
                    $_tmp = explode(":", $col['column']);
                    $tmp = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
                } else {
                    $tmp = "{$tableName}.{$col['column']}";
                }

                //exit();
                if ($aliases && isset($aliases[$tableName])) {
                    $tableName = $aliases[$tableName]["alias"]; //str_replace(array('::','[',']'),array('_','_',''),$tableName);
                }
                //$tmp = '';
                if (!empty($col['aggregate'])) {
                    $agdistinct = "";
                    $tmp = $col['aggregate'] . "($agdistinct " . $tmp . ")";
                }

                if ($first) {
                    $columns = "\t" . $tmp . ' AS `' . $prefix . $col['alias'] . '`';
                    $first = false;
                } else {
                    $columns .= ",\n\t" . $tmp . ' AS `' . $prefix . $col['alias'] . '`';
                }
            }

            if (isset($criteriaData["joins"])) {
                foreach ($criteriaData["joins"] as $j) {
                    foreach ($j["fields"] as $field) {
                        $columns .= ",\n\t`" . $j["as"] . '`.`' . $field . '` as `' . $j["as"] . ':' . $field . '`';
                    }
                }
            }

            if (isset($criteriaData["customJoins"])) {
                foreach ($criteriaData["customJoins"] as $j) {
                    foreach ($j["fields"] as $field) {
                        $columns .= ",\n\t`" . $j["as"] . '`.`' . $field . '` as `' . $j["as"] . ':' . $field . '`';
                    }
                }
            }
        }
        return " \n\t" . $columns . "\n";
    }

    public  function groupsToSQL($tableName, $criterias, $aliases = false)
    {
        // table alliases -  array of tables whose columns have to added under special aliases (ad also many times)
        $groupBy = '';
        $orderBy = ''; //zmiana z powodu prorytetów w joincriterii
        $limitBy = '';

        if (!($criterias instanceof JoinCriteria)) {
            $criterias = array($criterias);
        }

        foreach ($criterias as $criteria) {

            $group_empty = false;
            $criteriaData = $criteria->getData();
            $className = $criteria->getModel();

            if ($aliases && isset($aliases[$tableName])) {

                if (strpos($className, ':') !== false) {
                    $className = explode('[', $className);
                    $className = end($className);
                    $className = str_replace(array('::', ']'), array('_', ''), $className);
                }
                if (isset($aliases[$tableName][$className])) {
                    $tableName = $aliases[$tableName][$className];
                } else {
                    $tableName = $aliases[$tableName]["alias"];
                }
            }

            if ($criteria->isGroupBy()) {
                if (empty($groupBy)) {
                    $groupBy = "\n GROUP BY \n\t";
                    $group_empty = true;
                }
                $tmp = array();
                foreach ($criteriaData['group'] as $group) {

                    if (strpos($group, "raw:") === 0) {
                        $tmp[] = substr($group, 4);
                    } elseif ($group[0] == "'") {
                        $tmp[] = "'" . trim($group, "'") . "'";
                    } else {
                        if (strpos($group, ":") == false) {
                            $tmp[] = "{$tableName}.{$group}";
                        } else {
                            $_tmp = explode(":", $group);
                            $tmp[] = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
                        }
                    }
                }
                if (!$group_empty) {
                    $groupBy .= ", ";
                }
                $groupBy .= implode(", \n\t", $tmp);
            }

            if (isset($criteriaData['order']) && !empty($criteriaData['order'])) {
                $ord = array();

                foreach ($criteriaData['order'] as $order) {
                    $tmp = '';
                    if ($order[0] == "RAND()" || $order[0] == "RAND") {
                        $tmp = "RAND()";
                    } else {
                        if ($order[0][0] == "'") {
                            $tmp = trim($order[0], "'");

                        } elseif (strpos($order[0], "raw:") === 0) {
                            $tmp = self::$connection->quote(substr($order[0], 4));
                        } else {

                            if (strpos($order[0], ":") == false) {
                                $alias = false;
                                //sprawdzanie czy nie sortujemy wg aliasu
                                foreach ($criteriaData['columns'] as $col) {
                                    if ($col["alias"] == $order[0]) {
                                        $tmp = "`{$order[0]}`";
                                        $alias = true;
                                    }
                                }
                                if (!$alias) {
                                    $tmp = "{$tableName}.{$order[0]}";
                                }
                            } else {
                                $_tmp = explode(":", $order[0]);
                                $tmp = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
                            }
                        }

                        $tmp .= " " . $order[1];

                    }

                    if (!isset($order[2]) || $order[2] === '') {
                        $ord[] = $tmp;

                        //to może psuć w przypadku kiedy będą łaczone riorytety i niepriorytety (ale to nie powinno sie nigdy zdażyc)
                    } else {

                        $ord[$order[2]] = $tmp;
                    }
                }
                if (!empty($ord)) {
                    ksort($ord);
                    $orderBy = implode(", \n\t", $ord);
                    $orderBy = "\n ORDER BY \n\t" . $orderBy;
                }
            }
            if (isset($criteriaData['limit']) && !empty($criteriaData['limit'])) {
                $limitBy .= "\n LIMIT {$criteriaData['limit'][0]}, {$criteriaData['limit'][1]}";
            }
        }


        return $groupBy . $orderBy . $limitBy;
    }


    public function getSynchronizer() : AbstractSynchronizer
    {
        return new MysqlSynchronizer(self::$connection);
    }

}
