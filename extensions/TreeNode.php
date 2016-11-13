<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 31.10.13
 * Time: 23:25
 */

namespace Arrow\ORM\Extensions;


use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\PersistentFactory;

trait TreeNode {

    public static $EXTENSION_TREE_PARENT_ID = "parent_id";
    public static $EXTENSION_TREE_PARENT_DEPTH = "depth";
    public static $EXTENSION_TREE_SORT = "sort";

    /**
     * @return TreeNode
     */
    public static function getRoot()
    {
        $class = static::getClass();
        $root = Criteria::query($class)->c(self::$EXTENSION_TREE_PARENT_DEPTH, 0)->findFirst();

        if ($root == null) {
            $root = $class::create([
                "name"      => "Root",
                "depth"     => 0,
                "parent_id" => 0
            ]);


            //PersistentFactory::save($root, false, true);
        }
        return $root;
    }

    public function updateTreeSorting()
    {
        $i = 0;

        $root = self::getRoot();
        $root->setValue('sort', 0);
        $root->setValue('depth', 0);
        $root->save();
        $children = $root->getAllChildren();
        foreach ($children as $child) {
            $i++;
            $child->setValue(self::$EXTENSION_TREE_SORT, $i);
            $child->setValue(self::$EXTENSION_TREE_PARENT_DEPTH, count($child->getAncestors()));
            PersistentFactory::save($child, false);
        }
    }


    public function moveUp()
    {
        $thisSort = $this[self::$EXTENSION_TREE_SORT];

        $class = static::getClass();
        $prev = Criteria::query($class)
            ->c(self::$EXTENSION_TREE_SORT, $thisSort, Criteria::C_LESS_THAN)
            ->order(self::$EXTENSION_TREE_SORT, Criteria::O_DESC)
            ->c(self::$EXTENSION_TREE_PARENT_DEPTH, $this[self::$EXTENSION_TREE_PARENT_DEPTH] )
            ->c(self::$EXTENSION_TREE_PARENT_ID, $this[self::$EXTENSION_TREE_PARENT_ID] )
            ->findFirst();
        if(!$prev)
            return;

        $prevSort = $prev[self::$EXTENSION_TREE_SORT];

        $prev[self::$EXTENSION_TREE_SORT] = $thisSort;
        $this[self::$EXTENSION_TREE_SORT] = $prevSort;

        PersistentFactory::save($prev, false);
        $this->save();
        return $this;
    }

    public function moveDown()
    {
        $thisSort = $this[self::$EXTENSION_TREE_SORT];

        $class = static::getClass();
        $prev = Criteria::query($class)
            ->c(self::$EXTENSION_TREE_SORT, $thisSort, Criteria::C_GREATER_THAN)
            ->order(self::$EXTENSION_TREE_SORT, Criteria::O_ASC)
            ->c(self::$EXTENSION_TREE_PARENT_DEPTH, $this[self::$EXTENSION_TREE_PARENT_DEPTH] )
            ->c(self::$EXTENSION_TREE_PARENT_ID, $this[self::$EXTENSION_TREE_PARENT_ID] )
            ->findFirst();
        if(!$prev)
            return;

        $prevSort = $prev[self::$EXTENSION_TREE_SORT];

        $prev[self::$EXTENSION_TREE_SORT] = $thisSort;
        $this[self::$EXTENSION_TREE_SORT] = $prevSort;

        PersistentFactory::save($prev, false);
        $this->save();
        return $this;
    }




    public function getParent()
    {
        return Criteria::query(self::getClass())->findByKey($this->data[self::$EXTENSION_TREE_PARENT_ID]);
    }

    public function hasChildren($fields = false)
    {
        return Criteria::query(self::getClass())->c(self::$EXTENSION_TREE_PARENT_ID, $this->data['id'])->count() > 0;
    }

    /**
     * @param bool $fields
     * @return TreeNode[]
     */
    public function getChildren($fields = false)
    {
        return Criteria::query(self::getClass())
            ->order(self::$EXTENSION_TREE_SORT)
            ->c(self::$EXTENSION_TREE_PARENT_ID, $this->data['id'])
            ->find();
    }

    public function getAllChildren($idOnly = false)
    {
        $tmp = array();
        $children = Criteria::query(self::getClass())->c(self::$EXTENSION_TREE_PARENT_ID, $this->data['id'])->order(self::$EXTENSION_TREE_SORT)->find();
        foreach ($children as $child) {
            $tmp = array_merge($tmp, array($idOnly ? $child->getPKey() : $child), $child->getAllChildren($idOnly));
        }

        return $tmp;
    }

    public function getPath($nameCol = '', $separator = "/", $startDepth = 0)
    {
        $path = array();
        foreach ($this->getAncestors() as $parent) {
            if ($parent->getDepth() >= $startDepth) {
                if (!empty($nameCol)) {
                    $path[] = $parent->getValue($nameCol);
                } else {
                    $path[] = $parent->getPKey();
                }
            }
        }
        return implode($separator, $path);
    }

    /**
     * @param bool $id_only
     *
     * @return PeristentObject
     */
    public function getAncestors($id_only = false)
    {
        $parent = $this->getParent();

        $tmp = array();
        while ($parent) {
            $tmp[] = $parent;
            $parent = $parent->getParent();

        }
        return array_reverse($tmp);
    }

    public function getDepth()
    {
        return $this->data[self::$EXTENSION_TREE_PARENT_DEPTH];
    }

    public function nodeDelete()
    {
        foreach ($this->getAllChildren() as $child) {
            $child->delete();
        }

        $this->delete();

    }
} 