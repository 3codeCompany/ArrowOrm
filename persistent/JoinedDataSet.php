<?php

namespace Arrow\ORM;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 03.11.12
 * Time: 00:25
 * To change this template use File | Settings | File Templates.
 */
class JoinedDataSet extends DataSet
{
    const MODE_FLATTEN = 0;
    const MODE_AGGREGATE = 1;
    protected $mode;

    public function __construct($class, \PDOStatement $result, $mode = self::MODE_FLATTEN)
    {
        parent::__construct($class, $result, false);
        $this->mode = $mode;
        if ($mode == self::MODE_AGGREGATE) {
            $this->prepareAggregatedList();
        }

    }

    protected function getNextRow($fetchType)
    {
        $row = $this->result->fetch(\PDO::FETCH_ASSOC);
        if (empty ($row)) {
            return false;
        }

        //$row = $this->valuesFilter($row);

        if ($fetchType == self::AS_ARRAY || $this->simple) {
            return $row;
        }

        $parts = $this->fieldFilter($row);
        $object = $this->initiateObject($parts["object"]);
        $object->setJoinedDataMode(JoinedDataSet::MODE_FLATTEN);
        $object->setJoinedData($parts["joined"]);
        return $object;
    }

    private function fieldFilter($data)
    {
        $ret = array("object" => array(), "joined" => array());
        $keys = array_keys($data);
        foreach ($keys as $index => $key) {
            if (strpos($key, $this->class . ":") === 0) {
                $_key = str_replace($this->class . ":", "", $key);
                $ret["object"][$_key] = $data[$key];
            } else {
                $ret["joined"][$key] = $data[$key];
            }
        }
        return $ret;
    }

    private function prepareAggregatedList()
    {
        $list = array();
        $class = $this->class;
        $pk = $class::getPKField();

        while ($row = $this->result->fetch(\PDO::FETCH_ASSOC)) {
            //$row = $this->valuesFilter($row);
            $parts = $this->fieldFilter($row);

            if (isset($list[$parts["object"][$pk]])) {
                $alreadyJoined = $list[$parts["object"][$pk]]->getJoinedData();
                $allNull = true;
                foreach ($parts["joined"] as $joined) {
                    if ($joined != null) {
                        $allNull = false;
                    }
                }
                if (!$allNull) {
                    $list[$parts["object"][$pk]]->setJoinedData(array_merge($alreadyJoined, array($parts["joined"])));
                }
            } else {
                $object = $this->initiateObject($parts["object"]);
                $allNull = true;
                foreach ($parts["joined"] as $joined) {
                    if ($joined != null) {
                        $allNull = false;
                    }
                }
                if (!$allNull) {
                    $object->setJoinedData(array($parts["joined"]));
                }

                $object->setJoinedDataMode(JoinedDataSet::MODE_AGGREGATE);
                $list[$parts["object"][$pk]] = $object;
            }

        }
        $this->mappedArray = array_values($list);
        $this->count = count($this->mappedArray);
    }


}
