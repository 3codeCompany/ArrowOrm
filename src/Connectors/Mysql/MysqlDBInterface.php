<?php

namespace Arrow\ORM\Connectors\Mysql;

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

use ADebug;
use Arrow\ORM\DB\DBInterface;
use Arrow\ORM\Exception;
use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\JoinCriteria;
use Arrow\ORM\Schema\AbstractSynchronizer;
use Psr\Log\LoggerInterface;
use function substr;
use function var_dump;

/**
 * Generates MySQL satement using ORM objects
 *
 * This class connects ORM to Mysql databases and generates sql statement from ORM objects (criteria, selector).
 */
class MysqlDBInterface implements DBInterface
{
    /**
     * @var \PDO
     */
    private $connection;

    /** @var LoggerInterface */
    private $logger;

    public static $joinSeparator = ":";

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getDB()
    {
        //$this->logger && $this->logger->info("Pobieram", $this->dbArray);
        return $this->connection;
    }

    /**
     * Returns rows
     *
     * @param String $table
     * @param Criteria $criteria
     *
     * @return Array
     */
    public function select(string $table, Criteria $criteria)
    {
        $data = $criteria->getData();
        self::$joinSeparator = $data["joinSeparator"];
        $joins = "";
        if (isset($data["joins"])) {
            $joins = "";
            $parseColumn = function ($column, $table) {
                if (strpos($column, self::$joinSeparator) == false) {
                    if ($column[0] == "'") {
                        return $this->connection->quote(trim($column, "'"));
                    }
                    return "`{$table}`.`{$column}`";
                } elseif (strpos($column, "raw:") === 0) {
                    return $this->connection->quote(substr($column, 4));
                } else {
                    $tmp = explode(self::$joinSeparator, $column);
                    return "`" . $tmp[0] . "`" . ".`" . $tmp[1] . "`";
                }
            };
            foreach ($data["joins"] as $j) {
                $tmp = [];
                foreach ($j["on"] as $field => $foreignField) {
                    $tmp[] = $parseColumn($field, $table) . " = " . $parseColumn($foreignField, $j["as"]);
                }
                $on = implode(" and ", $tmp);

                $joins .=
                    "\n " .
                    ($j["type"] == Criteria::J_OUTER ? "" : $j["type"]) .
                    " JOIN `" .
                    $j["class"]::getTable() .
                    "` as `" .
                    $j["as"] .
                    "` ON ( " .
                    $on .
                    " " .
                    $j["customCondition"] .
                    " )";
            }
        }
        if (isset($data["customJoins"])) {
            foreach ($data["customJoins"] as $j) {
                $joins .= "\n" . $j["queryFragment"];
            }
        }

        $q =
            "SELECT " .
            //($criteria->isAggregated() ? 'SQL_CALC_FOUND_ROWS ' : '') .
            $this->columnsToSQL($criteria) .
            "\nFROM $table $joins" .
            "\nWHERE " .
            $this->conditionsToSQL($criteria) .
            "\n" .
            $this->groupsToSQL($table, $criteria) .
            "\n" .
            $this->orderToSql($table, $criteria) .
            "\n" .
            $this->limitToSql($criteria);

        print_r($q);


        return $this->connection->query($q);
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
                    $query .= is_int($str) || is_float($str) ? $str : $this->connection->quote($str);
                }

                $first = false;
            }
            $query .= " )";
        }

        $this->connection->exec($query);
        return $this->connection->lastInsertId();
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
    public function update(string $table, array $data, Criteria $criteria)
    {
        $query = "UPDATE $table SET ";
        $first = true;

        foreach ($data as $column => $value) {
            $query .= ($first ? "" : ", ") . "`$column`" . "=";

            if (is_null($value)) {
                $query .= "NULL";
            } else {
                $query .= is_int($value) || is_float($value) ? $value : $this->connection->quote($value);
            }

            $first = false;
        }

        $query .= " WHERE " . $this->conditionsToSQL($criteria);

        if (isset($_REQUEST["arrTest"])) {
            print $query . "<br />";
        }

        if ($this->logger) {
            $this->logger->info($query);
        }

        return $this->connection->exec($query);
    }

    /**
     * Deletes rows specified by Criteria
     *
     * @param String $table
     * @param Criteria $criteria
     */
    public function delete(string $table, Criteria $criteria)
    {
        $query = "DELETE FROM $table WHERE " . $this->conditionsToSQL($criteria);
        return $this->connection->exec($query);
    }

    private static function functionToSQL($column, $function, $data)
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

    private static function valueToSQL($value, $condition, $function)
    {
        switch ($function) {
            case Criteria::F_DAY:
                return $value instanceof \DateTime ? $value->format("d") : $value;
            case Criteria::F_MONTH:
                return $value instanceof \DateTime ? $value->format("m") : $value;
            case Criteria::F_YEAR:
                return $value instanceof \DateTime ? $value->format("Y") : $value;
            case Criteria::F_DATE:
                return $value instanceof \DateTime ? $value->format("Y-m-d") : $value;
        }
        return $value;
    }

    private function getSqlValue($value)
    {
        if (is_null($value)) {
            return "NULL";
        }

        //todo problem with text columns when numeric value searched
        //eg login=123 result is 123 and 123fa etc.
        /*if (is_numeric($value)) {
            return $value;
        }*/

        return $this->connection->quote($value);
    }

    /**
     * Creates sql condition
     *
     * @param Criteria $criteria
     *
     * @return String
     */
    public function conditionsToSQL(Criteria $criteria)
    {
        //$aliases
        $table = false;
        $model = $criteria->getModel();
        if ($model) {
            $table = $model::getTable();
        }

        $criteriaData = $criteria->getData();
        $conditionString = "";

        self::$joinSeparator = $criteriaData["joinSeparator"];

        if (isset($criteriaData["conditions"])) {
            foreach ($criteriaData["conditions"] as $cond) {
                $conditionString .= "\n\t";

                /*if(empty($cond["column"]))
                 continue;*/

                if (is_string($cond)) {
                    $conditionString .= " $cond ";
                    continue;
                }

                $condition = $cond["condition"];

                if ($cond["column"] && $cond["column"][0] == "'") {
                    $column = trim($cond["column"], "'");
                } elseif (strpos($cond["column"], "raw:") === 0) {
                    $column = substr($cond["column"], 4);
                } else {
                    $column = "`" . $cond["column"] . "`";

                    if (
                        $table != false &&
                        $condition != Criteria::C_AND_GROUP &&
                        $condition != Criteria::C_OR_GROUP &&
                        $condition != Criteria::START &&
                        $condition != Criteria::END
                    ) {
                        if (isset($aliases[$table])) {
                            $column = $aliases[$table]["alias"] . "." . $column;
                        } else {
                            if (strpos($column, ":") == false) {
                                $column = "{$table}.{$column}";
                            } else {
                                $tmp = explode(":", $column);
                                $column = $tmp[0] . "`" . ".`" . $tmp[1];
                            }
                        }
                    } else {
                        if (!$table) {
                        } elseif (strpos($column, ":") == false) {
                            $column = "{$table}.{$column}";
                        } else {
                            $tmp = explode(":", $column);
                            $column = $tmp[0] . "`" . ".`" . $tmp[1];
                        }
                    }

                    //if function passed
                    if (isset($cond["function"]) && $cond["function"]) {
                        $column = $this->functionToSQL($column, $cond["function"], $cond["functionData"]);
                    }
                }

                $value = $this->valueToSQL($cond["value"], $condition, isset($cond["function"]) ? $cond["function"] : null);
                if ($condition != Criteria::C_CUSTOM && $condition != Criteria::START) {
                    if (!is_array($value)) {
                        $value = $this->getSqlValue($value);
                    } else {
                        foreach ($value as &$el) {
                            $el = $this->getSqlValue($el);
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
                        $valuesIn = [];
                        $null = false;
                        foreach ($value as $addVal) {
                            if ($addVal === "NULL") {
                                $null = true;
                            } else {
                                if (trim($addVal) !== "") {
                                    $valuesIn[] = $addVal;
                                }
                            }
                        }
                        if (!empty($valuesIn)) {
                            $addCondition = $column . " IN (" . implode(", ", $valuesIn) . ")";
                        } else {
                            $addCondition = " 0 ";
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
                            if ($addVal === "NULL") {
                                $null = true;
                            } else {
                                if (trim($addVal) !== "") {
                                    $valuesIn[] = $addVal;
                                }
                            }
                        }
                        if (!empty($valuesIn)) {
                            $addCondition = $column . " NOT IN (" . implode(", ", $valuesIn) . ")";
                        } else {
                            $addCondition = " 1 ";
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
                        $conditionString .=
                            /* (($index>0)?" $value ":""). */
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
            $conditionString = "1";
        }

        return $conditionString;
    }

    public function columnsToSQL(Criteria $criteria)
    {
        $columns = "";
        $first = true;

        $criteriaData = $criteria->getData();
        $class = $criteria->getModel();
        $tableName = $class::getTable();

        self::$joinSeparator = $criteriaData["joinSeparator"];

        $joined = isset($criteriaData["joins"]);

        foreach ($criteriaData["columns"] as $col) {
            $raw = false;
            if (strpos($col["column"], "raw:") === 0) {
                $tmp = substr($col["column"], 4);
                $raw = true;
            } elseif ($col["column"][0] == "(" || $col["custom"] == true) {
                $tmp = $col["column"];
            } elseif ($col["column"][0] == "'") {
                $tmp = trim($col["column"], "'");
            } elseif ($joined && strpos($col["column"], self::$joinSeparator) !== false) {
                $_tmp = explode(self::$joinSeparator, $col["column"]);
                $tmp = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
            } else {
                $tmp = "{$tableName}.{$col["column"]}";
            }

            if (!empty($col["aggregate"])) {
                $agdistinct = "";
                $tmp = $col["aggregate"] . "($agdistinct " . $tmp . ")";
            }

            $columns .= ($first ? "\t" : ",\n\t") . ($raw ? $tmp : $tmp . " AS `" . $col["alias"] . "`");
            $first = false;
        }

        //print "-----------" . PHP_EOL . PHP_EOL;
        if ($joined) {
            foreach ($criteriaData["joins"] as $j) {
                foreach ($j["fields"] as $field) {
                    $columns .= ",\n\t`" . $j["as"] . "`.`" . $field . "` as `" . $j["as"] . self::$joinSeparator . $field . "`";
                }
            }
            //print_r($criteriaData["joins"]);
        }

        //print PHP_EOL . self::$joinSeparator . PHP_EOL;
        //print_r($criteriaData["columns"]);

        //print_r($columns);

        return " \n\t" . $columns . "\n";
    }

    public function groupsToSQL($tableName, $criteria, $aliases = false)
    {
        // table alliases -  array of tables whose columns have to added under special aliases (ad also many times)
        $groupBy = "";

        $group_empty = false;
        $criteriaData = $criteria->getData();
        $className = $criteria->getModel();

        self::$joinSeparator = $criteriaData["joinSeparator"];

        if ($aliases && isset($aliases[$tableName])) {
            if (strpos($className, ":") !== false) {
                $className = explode("[", $className);
                $className = end($className);
                $className = str_replace(["::", "]"], ["_", ""], $className);
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
            $tmp = [];
            foreach ($criteriaData["group"] as $group) {
                if (strpos($group, "raw:") === 0) {
                    $tmp[] = substr($group, 4);
                } elseif ($group[0] == "'") {
                    $tmp[] = "'" . trim($group, "'") . "'";
                } else {
                    if (strpos($group, self::$joinSeparator) == false) {
                        $tmp[] = "{$tableName}.{$group}";
                    } else {
                        $_tmp = explode(self::$joinSeparator, $group);
                        $tmp[] = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
                    }
                }
            }
            if (!$group_empty) {
                $groupBy .= ", ";
            }
            $groupBy .= implode(", \n\t", $tmp);
        }

        return $groupBy;
    }

    private function orderToSql($tableName, Criteria $criteria)
    {
        $criteriaData = $criteria->getData();

        self::$joinSeparator = $criteriaData["joinSeparator"];
        $orderBy = "";
        if (isset($criteriaData["order"]) && !empty($criteriaData["order"])) {
            $ord = [];

            foreach ($criteriaData["order"] as $order) {
                $tmp = "";
                if ($order[0] == "RAND()" || $order[0] == "RAND") {
                    $tmp = "RAND()";
                } else {
                    if ($order[0][0] == "'") {
                        $tmp = trim($order[0], "'");
                    } elseif (strpos($order[0], "raw:") === 0) {
                        // !!!important raw str is not escaped
                        $tmp = substr($order[0], 4);
                    } else {
                        if (strpos($order[0], self::$joinSeparator) == false) {
                            $alias = false;
                            //sprawdzanie czy nie sortujemy wg aliasu
                            foreach ($criteriaData["columns"] as $col) {
                                if ($col["alias"] == $order[0]) {
                                    $tmp = "`{$order[0]}`";
                                    $alias = true;
                                }
                            }
                            if (!$alias) {
                                $tmp = "{$tableName}.{$order[0]}";
                            }
                        } else {
                            $_tmp = explode(self::$joinSeparator, $order[0]);
                            $tmp = "`" . $_tmp[0] . "`" . ".`" . $_tmp[1] . "`";
                        }
                    }

                    $tmp .= " " . $order[1];
                }

                if (!isset($order[2]) || $order[2] === "") {
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

        return $orderBy;
    }

    private function limitToSql(Criteria $criteria)
    {
        $criteriaData = $criteria->getData();
        if (isset($criteriaData["limit"]) && !empty($criteriaData["limit"])) {
            return "\n LIMIT {$criteriaData["limit"][0]}, {$criteriaData["limit"][1]}";
        } else {
            return "";
        }
    }

    public function getSynchronizer(): AbstractSynchronizer
    {
        return new MysqlSynchronizer($this->connection);
    }

    public function getQueryParts(Criteria $criteria)
    {
        $criteriaData = $criteria->getData();
        $columns = implode(",", array_keys($criteriaData["columns"]));
        return [
            "columns" => $columns,
            "conditions" => $this->conditionsToSQL($criteria),
            "order" => $this->orderToSql("", $criteria),
            "limit" => $this->limitToSql($criteria),
        ];
    }

    public function applyCriteriaToQuery($query, Criteria $criteria): string
    {
        /*print_r($query);

        print_r($criteria);*/
        $criteriaData = $criteria->getData();
        //print_r($criteriaData["columns"]);

        unset($criteriaData["columns"][""]);
        $columns = implode(",", array_keys($criteriaData["columns"]));
        $query = str_replace("{columns}", $columns, $query);
        $query = str_replace("{conditions}", $this->conditionsToSQL($criteria), $query);
        $orderSql = str_replace("ORDER BY", "", $this->orderToSql("", $criteria));
        if (empty($orderSql)) {
            $orderSql = 1;
        }

        $query = str_replace("{order}", $orderSql, $query);
        $query = str_replace("{limit}", $this->limitToSql($criteria), $query);
        $query = str_replace("{groupBy}", $this->groupsToSQL("", $criteria), $query);

        $query = preg_replace("/\`(.+?)`\.`(.+?)\`/", "$1.$2", $query);
        $query = preg_replace("/\`(.+?)\.(.+?)\`/", "$1.$2", $query);

        /*{conditions}
         {order} {limit}*/

        return $query;
    }

    public function bulkInsert($model, $data)
    {
        if (empty($data)) {
            return;
        }

        $query = "\n\nALTER TABLE `{$model::getTable()}` DISABLE KEYS;\n INSERT INTO {$model::getTable()} (`" . implode("`, `", array_keys($data[0])) . "`) ";
        $query .= "\n VALUES  ";

        foreach ($data as $index => $row) {
            if ($index > 0) {
                $query .= ",";
            }
            $query .= "\n(";

            $first = true;
            foreach ($row as $str) {
                if (!$first) {
                    $query .= ", ";
                }

                if (is_null($str)) {
                    $query .= "NULL";
                } else {
                    $query .= is_int($str) || is_float($str) ? $str : $this->connection->quote($str);
                }

                $first = false;
            }
            $query .= " ) ";
        }

        $query .= ";\nALTER TABLE `{$model::getTable()}` ENABLE KEYS;";

        $this->connection->exec($query);
    }
}
