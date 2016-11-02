<?php
namespace Arrow\ORM;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 13:38
 * To change this template use File | Settings | File Templates.
 */
class MysqlConnector implements IDatabaseConnector
{
    private $datasource;

    public function __construct(Datasource $datasource)
    {
        $this->datasource = $datasource;
    }

    public function select($criteria)
    {
        $conf = $criteria->getConfiguration();
        print_r($conf);
        /*
    $stmt = $this->_db->prepare("");
    $stmt->bindParam(':lat', $lat, PDO::PARAM_STR);
    $stmt->bindParam(':lon', $lon, PDO::PARAM_STR);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':max', $max, PDO::PARAM_INT);
    $stmt->execute();
          */
        $query = "SELECT " . $this->columnsToSQL($conf["fields"]) . " FROM {$conf["table"]} WHERE 1"; //.$this->conditionsToSQL($criteria). $this->groupsToSQL($criteria);
        //$this->datasource->getConnection()->
        print $query;
        exit();
    }

    public function columnsToSQL($fields)
    {
        return implode(",", $fields);
    }
}
