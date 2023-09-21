<?php

namespace WpPasskeys\Exceptions;

use Exception;
use ReturnTypeWillChange;
use WpPasskeys\Interfaces;

abstract class CustomException extends Exception implements Interfaces\ExceptionInterface
{
    protected $message = 'Unknown exception';     // Exception message
    protected $code    = 0;                       // User-defined exception code
    protected string $file;                              // Source filename of exception
    protected int $line;                              // Source line of exception

    /**
     * @throws CustomException
     */
    public function __construct($message = null, $code = 0)
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
