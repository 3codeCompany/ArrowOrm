<?php

namespace Arrow\ORM\Persistent;

use Arrow\ORM\DB\DB;
use Arrow\ORM\DB\DBManager;
use Arrow\ORM\Exception;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:38
 * To change this template use File | Settings | File Templates.
 */
class PersistentFactory
{

    private static $globalListeners = array();


    public static function registerTracker(ITracker $tracker)
    {
        self::$globalListeners[] = $tracker;
    }

    private static function dispatchEvent($event, $object)
    {
        $object->$event($object);
        foreach ($object::getTrackers() as $tracker) {
            $tracker = $tracker::getTracker($object::getClass());
            $tracker->$event($object);
        }

        foreach (self::$globalListeners as $tracker) {
            $tracker = $tracker::getTracker($object::getClass());
            $tracker->$event($object);
        }
    }

    public static function delete(PersistentObject $object)
    {
        //before delete event
        self::dispatchEvent('beforeObjectDelete', $object);

        //only delete if object exists in DB
        if ($object->getPKey() !== null) {
            DBManager::getDefaultRepository()->delete($object);

        }
        //after delete
        self::dispatchEvent('afterObjectDelete', $object);

        return true;
    }

    public static function save(PersistentObject $object, $fireSaveEvents = true, $forceCreate = false)
    {

        if ($object->getPKey() !== null && !$object->isModified()) {
            return false;
        }

        $pk = $object::getPKField();
        if ($object->getPKey() !== null && !$forceCreate) { // object already in DB
            //before save event
            if ($fireSaveEvents) {
                self::dispatchEvent('beforeObjectSave', $object);
            }

            //save action
            $criteria = new Criteria(get_class($object));
            $criteria->addCondition($pk, $object->getValue($pk));

            //remove join values
            $data = $object->getData();
            foreach ($data as $key => $val) {
                if (strpos($key, ":") !== false)
                    unset($data[$key]);
            }

            DBManager::getDefaultRepository()->update($data, $criteria);

            //after save event
            if ($fireSaveEvents) {
                self::dispatchEvent('afterObjectSave', $object);
            }

            //TODO zsynchonizować obiekt z bazą danych po updejcie
        } else { // new object to be added to DB
            //before create event
            self::dispatchEvent('beforeObjectCreate', $object);

            //before save event
            if ($fireSaveEvents) {
                self::dispatchEvent('beforeObjectSave', $object);
            }

            //checking are required values exist in initial data
            $data = $object->getData();
            foreach ($object::getRequiredFields() as $field) {
                if (!array_key_exists($field, $data)) {
                    throw new Exception(array('msg' => '[ArrowOrmPersistent] Required value ' . $field . ' not deliverd to create function.', "values" => $data));
                }
            }
            //create action
            $id = DBManager::getDefaultRepository()->insert($object);
            $object[$pk] = $id;

            //synchronize object with database
            $dbData = self::getByKey($id, $object::getClass())->getData();

            $object->setValues($dbData);

            //after create event
            self::dispatchEvent('afterObjectCreate', $object);

            //after save event
            if ($fireSaveEvents) {
                self::dispatchEvent('afterObjectSave', $object);
            }

        }
        //no new modifications
        $object->setModified(false);
        return true;
    }

    /**
     * @static
     *
     * @param $key
     * @param $model
     *
     * @return PersistentObject
     */
    public static function getByKey($key, $model)
    {
        $criteria = new Criteria($model);
        $criteria->addCondition($model::getPKField(), $key);
        $list = self::getByCriteria($criteria);
        $obj = $list->fetch();
        if (empty($obj)) {
            return null;
        }
        self::dispatchEvent('afterObjectLoad', $obj);
        return $obj;
    }

    /**
     * Returns array of objects of given class (Read from DB).
     *
     * If you want to return only part of objects stored in database use Criteria object.
     *
     * @param Criteria $criteria
     * @param String $class
     *
     * @return DataSet
     */
    public static function getByCriteria(Criteria $criteria)
    {
        $class = $criteria->getModel();

        foreach ($class::getTrackers() as $tracker) {
            $tracker::getTracker($class)->beforeListLoad($criteria);
        }

        //get by criteria using joins
        if ($criteria instanceof JoinCriteria) {
            return DBManager::getDefaultRepository()->join($criteria, $criteria->isAggregated());
        } else {
            return DBManager::getDefaultRepository()->select($criteria, $criteria->isAggregated());
        }
    }

}
