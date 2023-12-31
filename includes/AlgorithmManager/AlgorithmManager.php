<?php

/**
 * Algorithm Manager for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

declare(strict_types=1);

namespace WpPasskeys\AlgorithmManager;

use Cose\Algorithm\Algorithm;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\ECDSA\ES256K;
use Cose\Algorithm\Signature\ECDSA\ES384;
use Cose\Algorithm\Signature\ECDSA\ES512;
use Cose\Algorithm\Signature\EdDSA\Ed256;
use Cose\Algorithm\Signature\EdDSA\Ed512;
use Cose\Algorithm\Signature\RSA\PS256;
use Cose\Algorithm\Signature\RSA\PS384;
use Cose\Algorithm\Signature\RSA\PS512;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\RSA\RS384;
use Cose\Algorithm\Signature\RSA\RS512;
use InvalidArgumentException;

class AlgorithmManager implements AlgorithmManagerInterface
{
    private array $algorithms;

    public function init(): Manager
    {
        return Manager::create()->add(
            ES256::create(),
            ES256K::create(),
            ES384::create(),
            ES512::create(),
            RS256::create(),
            RS384::create(),
            RS512::create(),
            PS256::create(),
            PS384::create(),
            PS512::create(),
            Ed256::create(),
            Ed512::create(),
        );
    }

    public function getAlgorithmIdentifiers(): array
    {
        return [
            ES256::identifier()  => -7,  // ECDSA w/ SHA-256
            ES256K::identifier() => -47, // ECDSA w/ SHA-256 with secp256k1 curve
            ES384::identifier()  => -35, // ECDSA w/ SHA-384
            ES512::identifier()  => -36, // ECDSA w/ SHA-512
            RS256::identifier()  => -257, // RSA w/ SHA-256
            RS384::identifier()  => -258, // RSA w/ SHA-384
            RS512::identifier()  => -259, // RSA w/ SHA-512
            PS256::identifier()  => -37, // RSA w/ SHA-256 and PSS
            PS384::identifier()  => -38, // RSA w/ SHA-384 and PSS
            PS512::identifier()  => -39, // RSA w/ SHA-512 and PSS
            Ed256::identifier()  => -8,  // EdDSA w/ Ed25519
            Ed512::identifier()  => -9,   // EdDSA w/ Ed448
        ];
    }

    public function get(int $identifier): Algorithm
    {
        if (! $this->has($identifier)) {
            throw new InvalidArgumentException('Unsupported algorithm');
        }

        return $this->algorithms[$identifier];
    }

    public function has(int $identifier): bool
    {
        // Check if the algorithm exists in the array
        return array_key_exists($identifier, $this->algorithms);
    }
}
