<?php
/**
 * Base route.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Routes;

use AutoPostThumbnail\Modules\Api;
use WP_REST_Response;

/**
 * Abstract base route class.
 *
 * @package AutoPostThumbnail
 */
abstract class Base_Route {
	/**
	 * Get the namespace for the route.
	 *
	 * @return string
	 */
	abstract public function get_namespace();

	/**
	 * Get the routes for the route.
	 *
	 * @return array
	 */
	abstract public function get_routes();

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( $this->get_routes() as $route_data ) {
			register_rest_route( Api::NAMESPACE, sprintf( '%s/%s', $this->get_namespace(), $route_data['route'] ), $route_data );
		}
	}

	/**
	 * Check if the user has permission to manage options.
	 *
	 * @return bool
	 */
	final public function manage_options_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message The error message.
	 * @param int    $code The error code.

	 * @return WP_REST_Response
	 */
	final public function error_response( $message, $code = 400 ) {
		return new WP_REST_Response(
			[
				'message' => $message,
			],
			$code
		);
	}


	/**
	 * Create a success response.
	 *
	 * @param array $data The data to return.
	 * @param bool  $success Whether the response is successful.
	 *
	 * @return WP_REST_Response
	 */
	final public function success_response( $data, $success = true ) {
		return new WP_REST_Response(
			[
				'data'    => $data,
				'success' => $success,
			],
			200
		);
	}
}
