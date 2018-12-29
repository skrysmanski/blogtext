<?php
namespace MSCL\Exceptions;


/**
 * Thrown when an operation couldn't be executed because some pre-conditions didn't match.
 */
class InvalidOperationException extends \Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
