<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 31.10.13
 * Time: 23:25
 */

namespace Arrow\ORM\Extensions;


use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\PersistentFactory;

trait Sortable {

    public static $EXTENSION_SORTABLE_FIELD = "sort";

    public function updateSorting(){
        $class = static::getClass();
        $find = Criteria::query($class)
            ->c(self::$EXTENSION_SORTABLE_FIELD, [null,0], Criteria::C_IN)
            ->find();


        foreach($find as $el){
            $el[self::$EXTENSION_SORTABLE_FIELD] = $el->getPKey();
            $el->save();
        }
    }


    public function moveUp( Criteria $additionalCriteria = null )
    {
        $this->updateSorting();

        $thisSort = $this[self::$EXTENSION_SORTABLE_FIELD];
        if(!$thisSort)
            $thisSort = $this->getPKey();

        $class = static::getClass();
        $criteria = $additionalCriteria !== null ? $additionalCriteria: Criteria::query($class);
        $prev = $criteria
            ->c(self::$EXTENSION_SORTABLE_FIELD, $thisSort, Criteria::C_LESS_THAN)
            ->order(self::$EXTENSION_SORTABLE_FIELD, Criteria::O_DESC)
            ->findFirst();
        if(!$prev)
            return;

        $prevSort = $prev[self::$EXTENSION_SORTABLE_FIELD];

        $prev[self::$EXTENSION_SORTABLE_FIELD] = $thisSort;
        $this[self::$EXTENSION_SORTABLE_FIELD] = $prevSort;

        PersistentFactory::save($prev, false);
        $this->save();
        return $this;
    }

    public function moveDown(Criteria $additionalCriteria = null)
    {

        $this->updateSorting();

        $thisSort = $this[self::$EXTENSION_SORTABLE_FIELD];
        if(!$thisSort)
            $thisSort = $this->getPKey();

        $class = static::getClass();
        $criteria = $additionalCriteria !== null ? $additionalCriteria : Criteria::query($class);
        $prev = $criteria
            ->c(self::$EXTENSION_SORTABLE_FIELD, $thisSort, Criteria::C_GREATER_THAN)
            ->order(self::$EXTENSION_SORTABLE_FIELD, Criteria::O_ASC)
            ->findFirst();
        if(!$prev)
            return;

        $prevSort = $prev[self::$EXTENSION_SORTABLE_FIELD];

        $prev[self::$EXTENSION_SORTABLE_FIELD] = $thisSort;
        $this[self::$EXTENSION_SORTABLE_FIELD] = $prevSort;

        PersistentFactory::save($prev, false);
        $this->save();
        return $this;
    }

}