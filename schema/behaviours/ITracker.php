<?php
namespace Arrow\ORM\Schema\Behaviours;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:30
 * To change this template use File | Settings | File Templates.
 */
interface ITracker
{
    public static function getTracker($class);

    public function beforeListLoad(Criteria $criteria);

    public function afterListLoad(Criteria $criteria, DataSet $dataset);

    public function beforeObjectLoad($class, $data);

    public function afterObjectLoad(PersistentObject $object);

    public function beforeObjectSetLoad(Criteria $criteria);

    public function afterObjectSetLoad(Criteria $criteria, PersistentObject $object);

    public function beforeObjectCreate(PersistentObject $object);

    public function afterObjectCreate(PersistentObject $object);

    public function beforeObjectDelete(PersistentObject $object);

    public function afterObjectDelete(PersistentObject $object);

    public function beforeObjectUpdate(PersistentObject $object);

    public function afterObjectUpdate(PersistentObject $object);

    public function beforeObjectSave(PersistentObject $object);

    public function afterObjectSave(PersistentObject $object);

    public function fieldModified(PersistentObject $object, $field, $oldValue, $newValue);

}
