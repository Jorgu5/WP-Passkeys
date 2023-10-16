<?php

namespace WpPasskeys\Exceptions;

use Exception;

class CredentialException extends CustomException
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}