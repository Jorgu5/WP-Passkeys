<?php

namespace WpPasskeys;

use WpPasskeys\Exceptions\CustomException;

class CredentialException extends CustomException
{
    protected $message = 'Credential error';
    protected $code    = 401;

    /**
     * @throws CredentialException|CustomException
     */
    public function __construct($message = null, $code = 0)
    {
        // Use parent constructor but provide default message and code
        if (!$message) {
            $message = $this->message;
            $code = $this->code;
        }

        parent::__construct($message, $code);
    }
}
