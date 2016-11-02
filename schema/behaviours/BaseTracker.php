<?php
namespace Arrow\ORM;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:58
 * To change this template use File | Settings | File Templates.
 */
abstract class BaseTracker implements ITracker
{
    public function beforeObjectLoad($class, $data)
    {
        // TODO: Implement beforeObjectLoad() method.
    }

    public function afterObjectLoad(PersistentObject $object)
    {
        // TODO: Implement afterObjectLoad() method.
    }

    public function beforeObjectSetLoad(Criteria $criteria)
    {
        // TODO: Implement beforeObjectSetLoad() method.
    }

    public function afterObjectSetLoad(Criteria $criteria, PersistentObject $object)
    {
        // TODO: Implement afterObjectSetLoad() method.
    }

    public function beforeListLoad(Criteria $criteria)
    {
    }

    public function afterListLoad(Criteria $criteria, DataSet $dataset)
    {
    }

    public function beforeObjectCreate(PersistentObject $object)
    {
    }

    public function afterObjectCreate(PersistentObject $object)
    {
    }

    public function beforeObjectDelete(PersistentObject $object)
    {
    }

    public function afterObjectDelete(PersistentObject $object)
    {
    }

    public function beforeObjectUpdate(PersistentObject $object)
    {
    }

    public function afterObjectUpdate(PersistentObject $object)
    {
    }

    public function beforeObjectSave(PersistentObject $object)
    {
    }

    public function afterObjectSave(PersistentObject $object)
    {
    }

    public function fieldModified(PersistentObject $object, $field, $oldValue, $newValue)
    {
    }

    public static function getTracker($class)
    {
        throw new Exception("Must implement 'getTracker' method.");
    }

}
