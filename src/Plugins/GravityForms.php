<?php
/**
 * Gravity Forms Plugin.
 *
 * @package Junaidbhura\Composer\WPProPlugins\Plugins
 */

namespace Junaidbhura\Composer\WPProPlugins\Plugins;

use Composer\Semver\Semver;
use Exception;
use Junaidbhura\Composer\WPProPlugins\Http;
use UnexpectedValueException;

/**
 * GravityForms class.
 */
class GravityForms extends AbstractPlugin {

	/**
	 * GravityForms constructor.
	 *
	 * @param string $version
	 * @param string $slug
	 */
	public function __construct( $version = '', $slug = 'gravityforms' ) {
		parent::__construct( $version, $slug );
	}

	/**
	 * Get the download URL for this plugin.
	 *
	 * @throws UnexpectedValueException If the response is invalid or versions do not match.
	 * @return string
	 */
	public function getDownloadUrl() {
		$http = new Http();

		$api_query = array(
			'op'   => 'get_plugin',
			'slug' => $this->slug,
			'key'  => getenv( 'GRAVITY_FORMS_KEY' ),
		);

		$api_url = 'https://gravityapi.com/wp-content/plugins/gravitymanager/api.php';

		try {
			$response = $http->get( $api_url, $api_query );
		} catch ( Exception $e ) {
			$details = [];

			$error = $e->getMessage();
			if ( $error ) {
				$details[] = 'HTTP Error: ' . $error;
			}

			$message = sprintf(
				'Could not query API for package %s. Please try again later.',
				$this->getPackageName()
			);

			if ( $details ) {
				$message .= PHP_EOL . PHP_EOL . implode( PHP_EOL . PHP_EOL, $details );
			}

			throw new UnexpectedValueException( $message );
		}

		// Catch any throwables from objects in their unserialization handlers.
		// Composer itself handles converting PHP notices into exceptions.
		try {
			/**
			 * @todo When the Composer plugin drops support for PHP 5,
			 *    use the `unserialize()` function's `allowed_classes` option,
			 *    introduced in PHP 7, to disallow all classes.
			 */
			$data = unserialize( $response );

			if ( ! is_array( $data ) ) {
				throw new UnexpectedValueException(
					'unserialize(): Expected a data structure'
				);
			}
		} catch ( Exception $e ) {
			$details = [
				$e->getMessage(),
			];

			$response_length = mb_strlen( $response );
			if ( $response_length > 0 ) {
				$details[] = '    ' . mb_substr( $response, 0, 100 ) . ( $response_length > 100 ? '...' : '' );
			}

			$message = sprintf(
				'Expected a data structure from API for package %s.',
				$this->getPackageName()
			);

			$message .= PHP_EOL . PHP_EOL . implode( PHP_EOL . PHP_EOL, $details );

			throw new UnexpectedValueException( $message );
		}

		$candidates = array(
			array( 'version', 'download_url' ),
			array( 'version_latest', 'download_url_latest' ),
		);
		foreach ( $candidates as $candidate ) {
			list( $version_key, $download_key ) = $candidate;

			try {
				return $this->findDownloadUrl( $data, $version_key, $download_key );
			} catch ( UnexpectedValueException $e ) {
				// throw the last exception after the loop
			}
		}

		throw $e;
	}

	/**
	 * @param  array<string, mixed> $response     The EDD API response.
	 * @param  string               $version_key  The API field key that holds the version.
	 * @param  string               $download_key The API field key that holds the download URL.
	 * @throws UnexpectedValueException If the response is invalid or versions do not match.
	 * @return string
	 */
	protected function findDownloadUrl( array $response, $version_key, $download_key ) {
		$version      = array_key_exists( $version_key, $response ) ? $response[ $version_key ] : null;
		$download_url = array_key_exists( $download_key, $response ) ? $response[ $download_key ] : null;

		if ( false === $version ) {
			// If FALSE, the package does not exist / is not available
			throw new UnexpectedValueException( sprintf(
				'Could not find a matching package for %s. Check the package spelling and that the package is supported',
				'junaidbhura/' . $this->slug
			) );
		}

		if ( empty( $download_url ) || ! is_string( $download_url ) ) {
			throw new UnexpectedValueException( sprintf(
				'Expected a valid download URL from API for package %s',
				'junaidbhura/' . $this->slug
			) );
		}

		if ( empty( $version ) || ! is_scalar( $version ) ) {
			throw new UnexpectedValueException( sprintf(
				'Expected a valid download version number from API for package %s.',
				'junaidbhura/' . $this->slug
			) );
		}

		if ( ! Semver::satisfies( (string) $version, $this->version ) ) {
			throw new UnexpectedValueException( sprintf(
				'Expected download version from API (%s) to match installed version (%s) of package %s.',
				$version,
				$this->version,
				'junaidbhura/' . $this->slug
			) );
		}

		return str_replace( 'http://', 'https://', $download_url );
	}

}
