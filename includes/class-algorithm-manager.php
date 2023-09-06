<?php
/**
 * Algorithm Manager for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

declare(strict_types=1);

namespace WpPasskeys;

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
use WpPasskeys\Traits\Singleton;

/**
 * Algorithm Manager for WP Pass Keys.
 */
class Algorithm_Manager {

	use Singleton;

	/**
	 * The algorithm manager instance.
	 *
	 * @var Manager|null
	 */
	private ?Manager $algorithm_manager;

	/**
	 * Constructor for the class.
	 */
	private function __construct() {
		$this->algorithm_manager = Manager::create()->add(
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

	/**
	 * Retrieves the algorithm manager instance.
	 *
	 * @return Manager|null The algorithm manager instance.
	 */
	public function get_algorithm_manager(): ?Manager {
		return $this->algorithm_manager;
	}
}
