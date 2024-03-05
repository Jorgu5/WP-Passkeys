<?php

namespace WpPasskeys\Exceptions;

use Exception;
use ReturnTypeWillChange;
use WpPasskeys\Interfaces;

abstract class CustomException extends Exception implements Interfaces\ExceptionInterface
{
    protected $message = 'Unknown exception';
    protected $code    = 0;
    protected string $file;
    protected int $line;

    /**
     * @throws CustomException
     */
    public function __construct($message = null, $code = 'dupa')
    {
        if (!$message) {
            throw new $this('Unknown ' . get_class($this));
        }
        parent::__construct($message, $code);
    }

    #[ReturnTypeWillChange] public function __toString()
    {
        return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
               . (string)($this->getTraceAsString());
    }
}
