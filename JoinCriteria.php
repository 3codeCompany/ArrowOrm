<?php namespace Arrow\ORM;


class JoinCriteria extends Criteria implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var Criteria[]
     */
    private $criteriaSet = array();
    /**
     * @var Criteria
     */
    private $baseCriteria = null;
    /**
     * @var string
     */
    public $baseClass = null;

    private $resultMode = JoinedDataSet::MODE_FLATTEN;


    private $joinType = Criteria::J_LEFT;
    private $ignoredRelations = array();
    private $joinOnConditions = array();
    private $virtualLinks = array();

    private $orderPriority = 0;

    public function __construct($criteriaSet)
    {
        foreach ($criteriaSet as $key => &$criteria) {
            if (is_string($criteria)) {
                $criteria = new Criteria($criteria);
            }
            $class = explode('.', $criteria->getModel());
            $class = end($class);
            $this->criteriaSet[$class] = $criteriaSet[$key];
            if ($this->baseCriteria == null) {

                $this->baseCriteria = $this->criteriaSet[$class];
                $this->baseClass = $class;
                $this->mainModel = $class;
            }
        }
    }

    public function join($class, $type = self::J_LEFT, $relations = array())
    {
        if (!is_array($class)) {
            $class = array($class);
        }

        if (!empty($relations)) {
            if (!is_array($relations)) {
                $relations = array($relations);
            }
            foreach ($relations as $baseField => $foreignField) {
                $this->addRelation(new JoinRelation($this->mainModel, $baseField, reset($class), $foreignField));
            }
        }

        foreach ($class as $c) {

            $criteria = new Criteria($c);
            $criteria->mainModel = $c;

            $this->addCriteria($criteria);
        }

        $this->setJoinType($type);

        return $this;
    }

    public function addCriteria($criteria)
    {
        if (isset($this->criteriaSet[$criteria->getModel()])) {
            throw new Exception("[OrmJoinCriteria::addCriteria] Criteria exist.");
        }
        $this->criteriaSet[$criteria->getModel()] = $criteria;
    }

    public function getCriteria($model)
    {
        return $this->criteriaSet[$model];
    }

    public function offsetExists($offset)
    {
        return isset($this->criteriaSet[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->criteriaSet[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->criteriaSet[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->criteriaSet[$offset]);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->criteriaSet);
    }


    public function countClasses()
    {
        return count($this->criteriaSet);
    }

    public function setEmptyList()
    {
        foreach ($this->criteriaSet as $key => $criteria) {
            $criteria->setEmptyList();
        }
        return $this;
    }

    public function setJoinType($joinType)
    {
        $this->joinType = $joinType;
        return $this;
    }


    public function getJoinType()
    {
        return $this->joinType;
    }

    public function setLimit($offset, $lenght)
    {
        $this->baseCriteria->setLimit($offset, $lenght);
    }

    public function clearLimit()
    {
        $this->baseCriteria->clearLimit();
    }

    public function setColumns($arrColumns, $class = false)
    {
        $this->baseCriteria->setColumns(array());
        foreach($this->criteriaSet as $c)
            $c->setColumns(array());


        if( !$class  )
            $this->baseCriteria->setColumns($arrColumns);
        else{
            $this->findCriteria($class)->setColumns($arrColumns);
        }
        return $this;
    }

    /**
     * Return list of connections between classes declared in this joinCriteria
     *
     * @return unknown_type
     */
    public function getAvailableRelations()
    {
        $classes = array();
//        $this->setModel(reset($this->criteriaSet)->getModel());
        foreach ($this->criteriaSet as $obj) {
            $classes[] = $obj->getModel();
        }
        $related = array();

        foreach ($classes as $cls) {
            foreach ($cls::getForeignKeys() as $relClass => $ref) {
                $related[$cls][$relClass] = $relClass;
                $related[$relClass][$cls] = $cls;
            }
        }

        // _add virtual relations
        foreach ($this->virtualLinks as $kvl => $vvl) {
            foreach ($vvl as $ksl => $vsl) {
                $related[$kvl][$ksl] = $ksl;
                $related[$ksl][$kvl] = $kvl;
            }
        }

        foreach ($this->ignoredRelations as $rel) {
            if (isset($related[$rel['base']][$rel['rel']])) {
                unset($related[$rel['base']][$rel['rel']]);
            }
            if (isset($related[$rel['rel']][$rel['base']])) {
                unset($related[$rel['rel']][$rel['base']]);
            }
        }

        return $related;
    }

    public function ignoreRelation($baseClass, $foreignClass)
    {
        $this->ignoredRelations[] = array('base' => $baseClass, 'rel' => $foreignClass);
    }

    public function getBaseClass()
    {
        return $this->baseClass;
    }

    private function decodeColumn($col)
    {
        $pattern = '/(.*?)(\[(.*)\])?:(.*)/';
        preg_match($pattern, $col, $matches);
        if (!empty($matches[3])) {
            if (strpos($matches[3], '::') === false) {
                $mainClass = $this->baseClass;
                $matches[3] = '[' . $mainClass . '::' . $matches[3] . ']';
            } else {
                $matches[3] = '[' . $matches[3] . ']';
            }
        }

        if (empty($matches)) {
            //column from base class
            return array($col);
        }
        $data = array($matches[1] . $matches[3], $matches[4]);
        //todo hack for namespaces and short names , futuer => use aliases
        if (strpos($data[0], "\\") === false) {
            foreach ($this->criteriaSet as $class => $crit) {
                if (strpos($class, "\\" . $data[0]) !== false) {
                    $data[0] = $class;
                }
            }
        }

        /* print "<pre>";
         print_r($data);
         print_r( array_keys($this->criteriaSet) );

         exit();*/

        return $data;
    }

    
    public function addColumn($column, $alias = false, $aggregate = false, $raw = false)
    {
        $column = $this->decodeColumn($column);


        if (count($column) == 1) {
            $this->baseCriteria->addColumn($column[0], $alias, $aggregate, $raw);
        } else {
            $this->findCriteria($column[0])->addColumn($column[1], $alias, $aggregate, $raw);
        }
        if ($aggregate !== "") {
            foreach ($this->criteriaSet as $criteria) {
                $criteria->aggregates = true;
            }
            $this->aggregates = true;
        }

        return $this;
    }


    public function addCondition($column, $value, $condition = self::C_EQUAL, $function = null, $functionData = array())
    {
        $column = $this->decodeColumn($column);
        if (count($column) == 1) {
            $this->baseCriteria->addCondition($column[0], $value, $condition, $function, $functionData);
        } else {
            $this->findCriteria($column[0])->addCondition($column[1], $value, $condition, $function, $functionData);
        }
    }

    public function _or($class=false)
    {
        $this->findCriteria($class)->addConnector("OR");
        return $this;
    }


    public function addSearchCondition($columns, $value, $condition = self::C_LIKE)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }

        $groups = array();

        foreach($columns as $c){
            $column = $this->decodeColumn($c);
            if (count($column) == 1) {
                $groups["__BASE__"][] = $column[0];
            } else {
                $groups[$column[0]][] = $column[1];
            }
        }

        foreach($groups as $group =>$columns){
            if($group == "__BASE__")
                $this->baseCriteria->addSearchCondition($columns,$value,$condition);
            else
                $this->findCriteria($group)->addSearchCondition($columns,$value,$condition);
        }

        return $this;
    }


    public function addGroupBy($column)
    {
        $column = $this->decodeColumn($column);
        if (count($column) == 1) {
            $this->baseCriteria->addGroupBy($column[0]);
        } else {
            $this->findCriteria($column[0])->addGroupBy($column[1]);
        }
    }

    public function order($column, $orderType = self::O_ASC, $order_priority = ''){
        $this->addOrderBy($column,$orderType, $order_priority);

        return $this;
    }

    public function addOrderBy($column, $orderType = self::O_ASC, $order_priority = '')
    {
        //order priority jest tylko do użytku wewnętrzenego
        $column = $this->decodeColumn($column);
        $priority = $this->orderPriority + 1;
        $this->orderPriority = $priority;
        if (count($column) == 1) {
            $this->baseCriteria->addOrderBy($column[0], $orderType, $priority);
        } else {
            $this->findCriteria($column[0])->addOrderBy($column[1], $orderType, $priority);
        }
    }

    public function removeOrder()
    {
        foreach ($this->criteriaSet as $criteria) {
            $criteria->removeOrder();
        }
    }

    public function startGroup( $class = false )
    {
        $this->findCriteria($class)->startGroup("AND");
        return $this;
    }

    public function endGroup($class = false)
    {
        $this->findCriteria($class)->endGroup();
        return $this;
    }

    public function setDistinct()
    {
        $this->baseCriteria->setDistinct();
    }

    public function findCriteria($class)
    {
        if($class == false)
            return $this->baseCriteria;

        if (strpos($class, '[') !== false) {
            //there is inner class in this name
            $baseClass = substr($class, 0, strpos($class, '['));
            $innerClass = substr($class, strpos($class, '[') + 1, -1);
        }

        if (isset($this->criteriaSet[$class])) {
            return $this->criteriaSet[$class];
        } else {

            if (isset($this->criteriaSet["\\" . $class])) {
                return $this->criteriaSet["\\" . $class];
            }

            //generate additional criteria (for multi joins only)
            if (isset($baseClass) && isset($this->criteriaSet[$baseClass])) {
                $this->criteriaSet[$class] = clone $this->criteriaSet[$baseClass];
                return $this->criteriaSet[$class];
            }


        }
        throw new Exception(array("msg" => "[JoinCriteria] Criteria with class '{$class}' not found", "criterias" => array_keys($this->criteriaSet)));
    }

    public function getModel()
    {
        return $this->baseCriteria->getModel();
    }

    public function __clone()
    {
        $this->baseCriteria = false;
        foreach ($this->criteriaSet as $key => $value) {
            $this->criteriaSet[$key] = clone $value;
            if ($this->baseCriteria === false) {
                $this->baseCriteria = $this->criteriaSet[$key];
            }
        }
    }

    public function addJoinOnCondition($base_class, $join_class, $condition)
    {
        $this->joinOnConditions[$base_class][$join_class][] = $condition;
        $this->joinOnConditions[$join_class][$base_class][] = $condition;
    }

    public function getJoinOnConditions($base_class, $join_class)
    {
        if (isset($this->joinOnConditions[$base_class][$join_class])) {
            return $this->joinOnConditions[$base_class][$join_class];
        }
        return array();
    }

    public function removeCondition($column, $value = null, $condition_type = null)
    {
        return $this->baseCriteria->removeCondition($column, $value, $condition_type);
    }

    /*checks whether condition exists in criteria
    * set $value or $condiiton_type to null to ignore it
    *
    */
    public function conditionExists($column, $value = null, $condition_type = null)
    {
        return $this->baseCriteria->conditionExists($column, $value, $condition_type);
    }

    public function addCustomCondition($str, $tables = array())
    {
        return $this->baseCriteria->addCustomCondition($str, $tables);
    }

    public function isGroupBy()
    {
        return $this->baseCriteria->isGroupBy();
    }

    public function addRelation(JoinRelation $relation)
    {
        $this->addVirtualRelation($relation->baseClass, $relation->baseField, $relation->joinClass, $relation->joinField);
    }

    public function addVirtualRelation($base_class, $base_field, $join_class, $join_field)
    {
        $this->virtualLinks[$base_class][$join_class][] = array("base_field" => $base_field, "join_field" => $join_field);
        return $this;
    }

    public function getVirtualRelations()
    {
        return $this->virtualLinks;
    }

    public function getBaseCriteria()
    {
        return $this->baseCriteria;
    }

    public function count()
    {
        if ($this->resultMode == JoinedDataSet::MODE_FLATTEN) {
            return parent::count();
        }

        $c = clone $this;
        $c->setColumns(array());
        $c->addColumn("id", "tmp", "count");
        $c->addGroupBy("id");

        if ($this->joinType == Criteria::J_LEFT) {

        } elseif ($this->joinType == Criteria::J_OUTER) {
            foreach ($this->criteriaSet as $criteria) {
                $criteria->c("id", null, Criteria::C_NOT_EQUAL);
            }
        }
        $result = PersistentFactory::getByCriteria($c);

        return count($result);

    }

    public function setResultMode($resultMode)
    {
        $this->resultMode = $resultMode;
        return $this;
    }

    public function getResultMode()
    {
        return $this->resultMode;
    }

    public function sum($field)
    {
        throw new Exception("Not implemented");
    }

}

?>