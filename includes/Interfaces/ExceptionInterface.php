<?php

namespace WpPasskeys\Interfaces;

interface ExceptionInterface
{
    public function getMessage();                 // Exception message
    public function getCode();                    // User-defined Exception code
    public function getFile();                    // Source filename
    public function getLine();                    // Source line
    public function getTrace();
    public function getTraceAsString();           // Formated string of trace
    public function __toString();                 // formated string for display
    public function __construct($message = null, $code = 0);
}
