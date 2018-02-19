<?php namespace Arrow\ORM\Persistent;


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
use Arrow\ORM\DB\DB;
use Arrow\ORM\Exception;

/**
 * Delivers API for selecting rows.
 *
 * Provides functions which allow to generate conditions used to describe rows
 * to be requested from DB by Class Persistent.
 */
class Criteria
{
    const C_EQUAL = '=='; /*     * <Equality condition (SQL: = ) */
    const C_NOT_EQUAL = '!='; /*     * <Inequality condition (SQL: != ) */
    const C_GREATER_EQUAL = '>='; /*     * <Greater or equal condition (SQL: >= ) */
    const C_GREATER_THAN = '>'; /*     * <Greater than condition (SQL: > ) */
    const C_LESS_EQUAL = '<='; /*     * <Less or equal condition (SQL: <= ) */
    const C_LESS_THAN = '<'; /*     * <Greater than condition (SQL: < ) */

    const C_IN = 'IN'; /*     * <In operator (SQL: IN) <br /> Note: this operator requires $value to be an array */
    const C_NOT_IN = 'NOT IN'; /*     * <Not In operator (SQL: NOT IN)<br /> Note: this operator requires $value to be an array */
    const C_BETWEEN = 'BETWEEN'; /*     * <Between operator (SQL: BETWEEN) <br /> Note: this operator requires $value to be 2 element array */

    const C_LIKE = 'LIKE'; /*     * <Like operator (SQL: LIKE) */
    const C_NOT_LIKE = 'NOT LIKE'; /*     * <Not Like operator (SQL: NOT LIKE) */

    const C_BIT_OR = '|'; /*     * <Bit OR */
    const C_BIT_AND = '&'; /*     * <Bit AND */
    const C_BIT_XOR = '^'; /*     * <Bit XOR */

    const C_OR_GROUP = 'OR'; /*     * <Start nested OR group */
    const C_AND_GROUP = 'AND'; /*     * <Start nested AND group */

    const C_CUSTOM = 'CUSTOM'; /*     * Custom condition */

    const START = 'START'; /* Start nested group (internal use only) */
    const END = 'END'; /* End nested group (internal use only) */

    const O_DESC = "DESC"; /*     * <Descending order */
    const O_ASC = "ASC"; /*     * <Ascending order */
    const O_RAND = "RAND"; /*     * <Random order */

    const A_COUNT = "COUNT"; /*     * <Aggregate: count */
    /**  */
    const A_MIN = "MIN"; /*     * <Aggregate: min */
    const A_MAX = "MAX"; /*     * <Aggregate: max */
    const A_SUM = "SUM"; /*     * <Aggregate: sum */
    const A_AVG = "AVG"; /*     * <Aggregate: avg */

    const F_DATE = "DATE"; /*     * <Date:  */
    const F_YEAR = "YEAR"; /*     * <Date: Year */
    const F_MONTH = "MONTH"; /*     * <Date: Month */
    const F_DAY = "DAY"; /*     * <Date: Day */


    const J_LEFT = "LEFT";
    const J_OUTER = "OUTER";

    /**
     * Array of conditions
     *
     * @var Array
     */
    private $data = array("group");
    /**
     * List of nested groups (AND/OR)
     *
     * @var Array
     */
    private $groups = array(self::C_AND_GROUP);
    /**
     * Information whether this is first or last condition in group
     * required for correct nesting of conditions
     *
     * @var Boolean
     */
    protected $firstlast = true;
    protected $aggregates = false;
    protected $mainModel;
    protected $mainModelFields;
    protected $mainModelPKField;




    /**
     * Constructor.
     *
     * @param $model
     * @param $data
     *
     * @return
     */
    public function __construct($model)
    {
        $this->mainModel = $model;
        $this->mainModelFields = $model::getFields();
        $this->mainModelPKField = $model::getPKField();

        foreach ($this->mainModelFields as $field) {
            $this->data['columns'][$field] = ['column' => $field, 'alias' => $field, 'aggregate' => false, 'custom' => false];
        }

    }


    /**
     *
     * @param string $model
     *
     * @return Criteria
     */
    public static function query($model)
    {
        if (!class_exists($model)) {
            throw new Exception("Class `$model` not exists");
        }

        $criteria = new static($model);
        $criteria->mainModel = $model;
        return $criteria;
    }


    public function _join($class, array $on, $as = false, $fields = null, $type = self::J_LEFT, $customCondition = false)
    {
        $as = $as?$as:$class;
        $fields = $fields?$fields:$class::getFields();

        $this->data["joins"][$as] = [
                "class" => $class,
                "as" => $as,
                "on" => $on,
                "type" => $type,
                "fields" => $fields,
                "customCondition" => $customCondition
        ];
        return $this;
    }

    public function customJoin( $alias, $fields, $queryFragment)
    {
        $this->data["customJoins"][] = [
            "as" => $alias,
            "fields" => $fields,
            "queryFragment" => $queryFragment
        ];
        return $this;
    }

    /**
     *
     * @param String $column
     * @param Mixed $value
     * @param String $condition
     *
     * @return static
     */
    public function c($column, $value, $condition = self::C_EQUAL, $function = null, $functionData = array())
    {
        $this->addCondition($column, $value, $condition, $function, $functionData);
        return $this;
    }


    public function cOR($column, $value, $condition = self::C_EQUAL, $function = null, $functionData = array())
    {
        $this->_or();
        $this->addCondition($column, $value, $condition, $function, $functionData);
        return $this;
    }

    public function _or()
    {
        $this->addConnector("OR");
        return $this;
    }

    protected function addConnector($type)
    {
        $this->data['conditions'][] = $type;
    }


    /**
     *
     * @param type $column
     * @param type $orderType
     * @param type $order_priority
     *
     * @return static
     */
    public function order($column, $orderType = self::O_ASC, $order_priority = '')
    {
        $this->addOrderBy($column, $orderType, $order_priority);
        return $this;
    }


    public function count()
    {
        //todo ładniej obudować
        if( $this->isAggregated()  || !empty($this->data['group'])){
            return DB::getDB()->query("SELECT FOUND_ROWS()")->fetchColumn();
        }

        return $this->getOneValue("id", "count");
    }

    public function sum($field)
    {
        return $this->getOneValue($field, "sum");
    }

    public function getOneValue($field, $function)
    {
        $c = clone $this;
        $c->setColumns(array());
        $c->removeOrder();
        $c->setLimit(0, 1);
        $c->addColumn($field, "tmp", $function);
        $result = PersistentFactory::getByCriteria($c)->fetch();
        if($result === null)
            return 0;
        return reset($result);
    }


    //todo przy joinach nie chce działać
    public function setColumns($arrColumns)
    {
        $this->data['columns'] = array();

        foreach ($arrColumns as $key => $column) {
            if (!is_numeric($key)) {
                $this->addColumn($key, $column);
            } else {
                $this->addColumn($column);
            }
        }

        /*if(empty($arrColumns))
            $this->addColumn($this->mainModelPKField);*/

        return $this;
    }

    /**
     *
     * @param type $offset
     * @param type $lenght
     *
     * @return self
     */
    public function limit($offset, $lenght)
    {
        $this->setLimit($offset, $lenght);
        return $this;
    }

    public function findAsFieldArray($field, $fieldToIndex = null)
    {
        $class = $this->mainModel;
        $columns = [$field];
        if($fieldToIndex && $fieldToIndex !== true)
            $columns[] = $fieldToIndex;

        $this->setColumns($columns);
        $result = $this->find();
        //Włączyć po ujednoliceniu
        //$result->setCacheEnabled(false);
        $tmp = array();
        while ($row = $result->fetch()) {
            if ($fieldToIndex) {
                if ($fieldToIndex === true)
                    $tmp[$row->getPKey()] = $row[$field];
                else
                    $tmp[$row[$fieldToIndex]] = $row[$field];
            } else {
                $tmp[] = $row[$field];
            }
        }
        return $tmp;
    }

    /**
     * @param bool $fieldAsIndex If no $fieldToIndex specyfied used Pkey
     * @param bool $fieldToIndex
     *
     * @return DataSet
     */
    public function find($fieldAsIndex = false, $fieldToIndex = null)
    {
        $result = PersistentFactory::getByCriteria($this);

        if (!$fieldAsIndex) {
            return $result;
        } else {
            $tmp = array();
            if($fieldToIndex == null)
                foreach ($result as $r)
                    $tmp[$r->getPKey()] = $r;
            else
                foreach ($result as $r)
                    $tmp[$r[$fieldToIndex]] = $r;

            return $tmp;
        }
    }


    public function findFirst()
    {
        return $this->limit(0, 1)->find()->fetch();
    }

    public function findByKey($key)
    {
        if (empty($key)) {
            return false;
        }
        return PersistentFactory::getByKey($key, $this->mainModel);
    }


    /**
     * Add single condition to Criteria object
     *
     * @param $column
     * @param $value
     * @param $condition
     *
     * @return
     */
    public function addCondition($column, $value, $condition = self::C_EQUAL, $function = null, $functionData = array())
    {
        if ($condition == self::END) {
            $this->firstlast = true;
            $value = NULL;
            $this->groups = array_slice($this->groups, 0, count($this->groups) - 1);
        }

        if (count($this->groups) && $this->firstlast == false) { //_add AND/OR between conditions
            $last = end($this->data['conditions']);
            if (!is_string($last)) {
                $this->addConnector(end($this->groups));
            }
        }

        /*$last = end($this->data['conditions']);
        if( $last && $last["value"] != null )*/

        $this->data['conditions'][] = array(
            'column' => $column,
            'value' => $value,
            'condition' => $condition,
            'function' => $function,
            'functionData' => $functionData
        );

        if ($condition == self::START) {
            $this->firstlast = true;
            $this->groups[] = $value;
        } else {
            $this->firstlast = false;
        }

    }

    public function addSearchCondition($columns, $value, $condition = self::C_LIKE)
    {

        if (!is_array($columns)) {
            $columns = array($columns);
        }
        $this->startGroup();
        foreach ($columns as $index => $column) {
            if ($index != 0) {
                $this->_or()->c($column, $value, $condition);
            } else {
                $this->c($column, $value, $condition);
            }
        }
        $this->endGroup();

        return $this;
    }

    /**
     * Custom condition (in pure SQL)
     *
     * @param string $value - condition
     * @param        array  (string) - tables which are in condition
     *
     * @return
     */
    public function addCustomCondition($value, $tables = array())
    {
        if (count($this->groups) && $this->firstlast == false) //_add AND/OR between conditions
        $this->addConnector(end($this->groups));

        $this->data['conditions'][] = array('column' => '', 'value' => $value, 'condition' => 'CUSTOM', 'tables' => $tables);
        $this->firstlast = false;

        return $this;

    }

    /**
     * Add/chenge name of single column in Criteria object
     *
     * @note By default Criteria includes list of all fields stored in configuration of model with witch it will be used.
     * Call setEmptyList() in order to override this.
     *
     * @param $column    - name of column to _add/change
     * @param $alias     - index under which this column will be returned
     * @param $aggregate - aggergete function to be used with this column use one of A_* constant here
     *
     * @return
     */
    public function addColumn($column, $alias = false, $aggregate = false, $custom = false)
    {
        if (!in_array($column, $this->mainModelFields) ) {
            if(strpos($column,":") != false || $column[0] == "("){
                $this->data['columns'][$alias ? $alias : $column] = ['column' => $column, 'alias' => $alias ? $alias : $column, 'aggregate' => $aggregate, "custom" => false];
            }else{
                /*
                    jeśli zewnętrzna kontrolka chce dodać kolumnę to trzeba sprawdzić również aliasy już dodane w criteri wczesniej ale
                    nie znajdujące się na liście kolumn, jeśli występuje to nic nie robimy ( bo kolumna już jest dodana ale z zewnątrz przyszedł request aliasu
                    dzieje się tak dlatego że kontrolki mogą nie rozpoznawać czy posługują się aliasem czy faktyczną nazwą kolumny
                */
                foreach( $this->data["columns"] as $col )
                    if($col["alias"] == $column)
                        return $this;
            }
        }

        $this->data['columns'][$alias ? $alias : $column] = ['column' => $column, 'alias' => $alias ? $alias : $column, 'aggregate' => $aggregate, 'custom' => $custom ];


        if (!empty($alias))
            $this->removeColumn($column);

        if (!empty($aggregate)) {
            $this->aggregates = true;
        }

        return $this;
    }



    /**
     * Remove single column from criteria column list.
     *
     * @note Several colums separated by commas (,) can be suppled to this function.
     *
     * @param $columns
     *
     * @return
     */
    public function removeColumn($columns)
    {
        //\Arrow\Logger::log("[ArrowCriteria] Column(s) removed from selection; $columns",\Arrow\Logger::EL_INFO);
        $data = explode(',', $columns);
        foreach ($data as $column) {
            $column = trim($column);
            if (!empty($column) && isset($this->data['columns'][$column])) {
                unset($this->data['columns'][$column]);
            }
        }
    }

    /**
     *
     * @param $column
     *
     * @return $this
     */
    public function addGroupBy($column)
    {
        //\Arrow\Logger::log("[ArrowCriteria] Group by \"{$column}\" added to selection; ",\Arrow\Logger::EL_INFO);
        $this->data['group'][] = $column;
        return $this;
    }

    //---------------------------------------------------------------------------------------------------------	
    public function addOrderBy($column, $orderType = self::O_ASC, $order_priority = '')
    {
        $orderType = strtoupper($orderType);
        if(strpos($column,",") !== false){
            $tmp = explode("," ,$column);
            foreach($tmp as $column)
                $this->data['order'][] = array($column, $orderType, $order_priority);
        }else{
            $this->data['order'][] = array($column, $orderType, $order_priority);
        }

    }

    public function removeOrder()
    {
        $this->data['order'] = array();
        return $this;
    }

    //---------------------------------------------------------------------------------------------------------
    public function setLimit($offset, $lenght)
    {
        //\Arrow\Logger::log("[ArrowCriteria] Limit set to: \"{$offset} {$lenght}\" ",\Arrow\Logger::EL_INFO);
        $this->data['limit'] = array($offset, $lenght);
    }

    public function clearLimit()
    {
        //\Arrow\Logger::log("[ArrowCriteria] Limit set to: \"{$offset} {$lenght}\" ",\Arrow\Logger::EL_INFO);
        unset($this->data['limit']);
        return $this;
    }

    public function startGroup()
    {
        $this->addCondition("", "AND", self::START);

        return $this;
    }

    //---------------------------------------------------------------------------------------------------------
    public function endGroup()
    {
        //$this->groups = array_slice($this->groups,0,count($this->groups)-1);
        //$this->firstlast = true;
        // przeniesione do addCondition
        $this->addCondition("", NULL, Criteria::END);

        return $this;
        //\Arrow\Logger::log("[Criteria] Nested group finished",\Arrow\Logger::EL_INFO);
    }


    //---------------------------------------------------------------------------------------------------------
    public function getModel()
    {
        return $this->mainModel;
    }

    //---------------------------------------------------------------------------------------------------------
    public function getData()
    {
        if ($this->aggregates == false) {
            if (!isset($this->data['columns'][$this->mainModelPKField])) {
                $this->addColumn($this->mainModelPKField);
            }
        }

        return $this->data;
    }

    public function isAggregated()
    {
        return $this->aggregates;
    }

    public function removeConditionByOffset($offset)
    {
        unset($this->data['conditions'][$offset]);
        if (isset($this->data['conditions'][$offset + 1])) {
            unset($this->data['conditions'][$offset + 1]);
        }
        return $this;
    }


    /* checks whether condition exists in criteria 
     * set $value or $condiiton_type to null to ignore it
     * 
     */

    /*public function conditionExists($column, $value = null, $condition_type = null)
    {
        if (isset($this->data['conditions']))
            $conditions = $this->data['conditions'];
        else
            $conditions = array();
        foreach ($conditions as $key => $condition) {
            if ($condition['column'] == $column && ($value == null || $condition['value'] == $value) && ($condition_type == null || $condition['condition'] == $condition_type)) {
                return true;
            }
        }
        return false;
    }*/

    public function isGroupBy()
    {
        return !empty($this->data['group']);
    }

    public function removeGrouping()
    {
        $this->data['group'] = array();
        return $this;
    }

    public function stringify(){

        //return print_r($this->data, 1);
        return json_encode($this->data);
        //return "criteria string";
    }

}

?>