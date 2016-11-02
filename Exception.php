<?php namespace
Arrow\ORM;

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

/**
 * Exception thrown by ORM Library classes.
 *
 * This exception is returned if any unhandled error is encountered during operation of Orm Library.
 */
class Exception extends \Exception
{

    /**
     * Constructor.
     *
     * @param mixed   $data - String or array containing information (description, parameter values, etc.) about exception
     * @param integer $code - code of exception (for catch blocks)
     */
    public function __construct($data, $code = 0)
    {
        if (is_array($data)) {
            $data = print_r($data, 1);
        }
        parent::__construct($data, $code);

    }
}

?>