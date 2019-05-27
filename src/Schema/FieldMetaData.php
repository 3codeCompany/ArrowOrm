<?php
/**
 * Created by PhpStorm.
 * User: artur.kmera
 * Date: 16.07.2018
 * Time: 11:20
 */

namespace Arrow\ORM\Schema;


class FieldMetaData
{

    protected $label;
    protected $options;
    protected $data;

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param mixed $label
     * @return FieldMetaData
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param mixed $options
     * @return FieldMetaData
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return FieldMetaData
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }



}