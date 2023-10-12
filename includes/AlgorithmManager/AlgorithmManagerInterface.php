<?php

namespace WpPasskeys\AlgorithmManager;

use Cose\Algorithm\Algorithm;
use Cose\Algorithm\Manager;

/**
 * Algorithm Manager for WP Pass Keys.
 */
interface AlgorithmManagerInterface
{
    /**
     * Constructor for the class.
     */
    public function init(): Manager;

    /**
     * Retrieves the algorithm manager instance.
     *
     * @return array The algorithm manager instance.
     */
    public function getAlgorithmIdentifiers(): array;

    /**
     * @param int $identifier
     *
     * @return Algorithm
     */
    public function get(int $identifier): Algorithm;

    /**
     * @param int $identifier
     *
     * @return bool
     */
    public function has(int $identifier): bool;
}
