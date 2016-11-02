<?php namespace Arrow\ORM;
/**
 * Resolvet mismatch
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 *
 * @todo       implement
 */
class ResolvedMismatch
{

    /**
     * Resolvet time
     *
     * @var int
     */
    public $timestamp;

    /**
     * For db it is place to store executed sql code
     *
     * @var string
     */
    public $additionalData;

    /**
     * Abstract mismatch object
     *
     * @var AbstractMismatch
     */
    public $mismatch;

    /**
     * State ( true = success, false = failure )
     *
     * @var unknown_type
     */
    public $success = false;


}

?>