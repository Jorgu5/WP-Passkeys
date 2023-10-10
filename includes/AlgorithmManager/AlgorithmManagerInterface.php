<?php

namespace WpPasskeys\AlgorithmManager;

use Cose\Algorithm\Algorithm;
use Cose\Algorithm\Manager;

interface AlgorithmManagerInterface
{
    public static function init(): Manager;
    public static function has(string $identifier): bool;
    public static function get(string $identifier): Algorithm;
    public static function getAlgorithmIdentifiers(): array;
}