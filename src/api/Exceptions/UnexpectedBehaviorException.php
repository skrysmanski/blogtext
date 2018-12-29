<?php
namespace MSCL\Exceptions;

/**
 * Thrown whenever there some unexpected behavior (a.k.a. "this should never happen").
 */
class UnexpectedBehaviorException extends \Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
