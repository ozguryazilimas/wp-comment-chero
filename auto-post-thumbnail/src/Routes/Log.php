<?php
/**
 * Log routes handler.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Routes;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Logger;
use AutoPostThumbnail\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Log routes handler.
 *
 * @package AutoPostThumbnail
 */
class Log extends Base_Route {
	/**
	 * Get the namespace for the route.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return 'log';
	}
	/**
	 * Get routes.
	 *
	 * @inheritDoc Base_Route::get_routes()
	 */
	public function get_routes() {
		return [
			[
				'route'               => 'get',
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_log' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'delete',
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_log' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'export',
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_logs' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'generation',
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'clear_generation_log' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
		];
	}

	/**
	 * Export the logs.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|void
	 */
	public function export_logs( WP_REST_Request $request ) {
		$export   = Logger::instance()->get_export();
		$prepared = $export->prepare();

		if ( ! $prepared ) {
			return new WP_REST_Response( __( 'Could not prepare the log for export. Please refresh the page and try again.', 'auto-post-thumbnail' ), 500 );
		}

		$export->download();
	}

	/**
	 * Get the log.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_log( WP_REST_Request $request ) {

		$log      = Logger::instance()->as_array();
		$log_size = Logger::instance()->get_total_size();

		if ( empty( $log ) ) {
			return $this->success_response(
				[
					'log'     => [],
					'logSize' => 0,
				]
			);
		}

		return $this->success_response(
			[
				'log'     => $log,
				'logSize' => $log_size,
			]
		);
	}

	/**
	 * Delete the log.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_log( WP_REST_Request $request ) {
		Logger::instance()->clean_up();

		return $this->success_response( [ 'message' => __( 'Log cleaned up', 'auto-post-thumbnail' ) ] );
	}

	/**
	 * Clear the generation log.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function clear_generation_log( WP_REST_Request $request ) {
		Settings::instance()->update_option( Options_Schema::GENERATION_LOG, [] );

		return $this->success_response( [ 'message' => __( 'Generation log cleared', 'auto-post-thumbnail' ) ] );
	}
}
