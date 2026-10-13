<?php 
/**
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\Media\Exceptions;

use \Exception;

class ImageException extends Exception
{
    public function __toString(): string
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}";
    }
}
