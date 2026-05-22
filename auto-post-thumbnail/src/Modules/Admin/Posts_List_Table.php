<?php
/**
 * Posts list table module.
 *
 * @package AutoPostThumbnail
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This file reads redirect query parameters, not form submissions.

namespace AutoPostThumbnail\Modules\Admin;

use AutoPostThumbnail\Asset;
use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Modules\Api;
use AutoPostThumbnail\Services\Apt;
use AutoPostThumbnail\Settings;
use AutoPostThumbnail\Modules\Base;

/**
 * Posts list table module.
 */
class Posts_List_Table extends Base {
	/**
	 * Column insertion index.
	 *
	 * @var int
	 */
	private $column_insertion_index = 4;

	const BULK_ACTION_GENERATE   = 'apt_generate_thumb';
	const BULK_ACTION_DELETE     = 'apt_delete_thumb';
	const BULK_ACTION_ADD_IMAGES = 'apt_add_images';

	/**
	 * Run hooks.
	 *
	 * @return void
	 */
	public function run_hooks() {
		// Add image column in Posts table.
		add_filter( 'manage_post_posts_columns', [ $this, 'add_image_column' ], 4 );
		add_action( 'manage_post_posts_custom_column', [ $this, 'image_column_content' ], 5, 2 );

		// Add filters on the Posts table.
		add_action( 'restrict_manage_posts', [ $this, 'add_filter_dropdown' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_posts' ], 10, 1 );
		add_filter( 'views_edit-post', [ $this, 'add_filter_link' ], 10, 1 );

		// Add bulk actions on the Posts table.
		add_filter( 'bulk_actions-edit-post', [ $this, 'add_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-post', [ $this, 'bulk_action_generate_handler' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'apt_bulk_action_admin_notice' ] );

		// Enqueue script.
		add_action( 'admin_enqueue_scripts', [ $this, 'legacy_enqueue_assets' ] );
	}

	/**
	 * Function for adding "image" column in Posts table.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function add_image_column( $columns ) {
		$pro = defined( 'WAPT_PRO_PATH' ) ? '' : ' <sup class="apt-sup-pro">(PRO)<sup>';

		$new_columns = [ 'apt-image' => __( 'Image', 'auto-post-thumbnail' ) . $pro ];

		return array_slice( $columns, 0, $this->column_insertion_index ) + $new_columns + array_slice( $columns, $this->column_insertion_index );
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function legacy_enqueue_assets( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		add_thickbox();
		wp_enqueue_media();

		( new Asset( 'post-list', true ) )
			->load_associated_css()
			->localize(
				'APTData',
				[
					'apiNamespace' => Api::NAMESPACE,
					'apiNonce'     => wp_create_nonce( 'wp_rest' ),
					'apiRoot'      => rest_url(),
					'upsellURL'    => esc_url( tsdk_translate_link( tsdk_utmify( 'https://themeisle.com/plugins/auto-featured-image/upgrade', 'replace:campaign', 'apt' ) ) ),
				]
			)
			->enqueue();
	}

	/**
	 * Function to filling "image" column in Posts table.
	 *
	 * @param string $colname Column name.
	 * @param int    $post_id Post ID.
	 */
	public function image_column_content( $colname, $post_id ) {
		if ( 'apt-image' !== $colname ) {
			return;
		}
		printf(
			'<div class="apt-image-container" data-thumbnail-url="%s" data-post-id="%d" data-thumbnail-id="%d"></div>',
			esc_url( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ),
			esc_attr( (string) $post_id ),
			esc_attr( (string) get_post_thumbnail_id( $post_id ) )
		);
	}

	/**
	 * Add filter on the Posts list tables.
	 *
	 * @return void
	 */
	public function add_filter_dropdown() {
		$screen = get_current_screen();

		if ( empty( $screen ) || 'post' !== $screen->post_type ) {
			return;
		}

		if ( ! isset( $_GET['apt_is_image'] ) ) {
			return;
		}

		$apt_is_image = sanitize_text_field( $_GET['apt_is_image'] );

		$options = [
			'-1' => esc_html__( 'Featured Image', 'auto-post-thumbnail' ),
			'1'  => esc_html__( 'With image', 'auto-post-thumbnail' ),
			'0'  => esc_html__( 'Without image', 'auto-post-thumbnail' ),
		];

		echo '<select name="apt_is_image">';

		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( (string) $value ) . '" ';
			if ( '-1' !== (string) $value ) {
				echo selected( (string) $value, $apt_is_image, false );
			}
			echo '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Filter the Posts list tables.
	 *
	 * @param \WP_Query $query The query object.
	 *
	 * @return void
	 */
	public function filter_posts( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$screen = get_current_screen();

		if (
			empty( $screen->post_type ) ||
			'post' !== $screen->post_type ||
			'edit-post' !== $screen->id ||
			! isset( $_GET['apt_is_image'] )
		) {
			return;
		}

		$filter = sanitize_text_field( $_GET['apt_is_image'] );

		if ( '-1' === $filter ) {
			return;
		}
		if ( '1' === $filter ) {
			$compare = 'EXISTS';
		} else {
			$compare = 'NOT EXISTS';
		}

		$query->set(
			'meta_query',
			[
				[
					'key'     => '_thumbnail_id',
					'compare' => $compare,
				],
			]
		);
	}

	/**
	 * Add filter on the Posts list tables.
	 *
	 * @param array $views Views.
	 *
	 * @return array
	 */
	public function add_filter_link( $views ) {
		if ( ! is_array( $views ) ) {
			return $views;
		}

		$q = add_query_arg(
			[
				'apt_is_image' => '0',
				'post_type'    => 'post',
			],
			'edit.php'
		);

		$is_current = isset( $_GET['apt_is_image'] ) && sanitize_text_field( $_GET['apt_is_image'] ) === '0' ? 'current' : '';

		$attributes = [
			'href'              => esc_url( $q ),
			'class'             => $is_current ? 'current' : '',
			'data-apt-is-image' => '0',
		];

		$attributes = array_map(
			function ( $key, $value ) {
				return $key . '="' . esc_attr( $value ) . '"';
			},
			array_keys( $attributes ),
			$attributes
		);

		$views['apt_filter'] = '<a ' . implode( ' ', $attributes ) . '>' . esc_html__( 'Without featured image', 'auto-post-thumbnail' ) . '</a>';

		return $views;
	}

	/**
	 * Register bulk option for posts
	 *
	 * @param array $bulk_actions Registered bulk actions.
	 *
	 * @return array
	 */
	public function add_bulk_actions( $bulk_actions ) {
		if ( ! is_array( $bulk_actions ) ) {
			return $bulk_actions;
		}

		$bulk_actions[ self::BULK_ACTION_GENERATE ]   = __( 'Generate featured image', 'auto-post-thumbnail' );
		$bulk_actions[ self::BULK_ACTION_DELETE ]     = __( 'Unset featured image', 'auto-post-thumbnail' );
		$bulk_actions[ self::BULK_ACTION_ADD_IMAGES ] = __( 'Upload post images', 'auto-post-thumbnail' );

		return $bulk_actions;
	}

	/**
	 * Handler of bulk option for posts
	 *
	 * @param string $redirect_to Redirect to.
	 * @param string $action Action.
	 * @param array  $post_ids Post IDs.
	 *
	 * @return string
	 */
	public function bulk_action_generate_handler( $redirect_to, $action, $post_ids ) {
		if ( ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			switch ( $action ) {
				case self::BULK_ACTION_ADD_IMAGES:
					do_action( 'wapt/upload_and_replace_post_images', $post_id );
					break;
				case self::BULK_ACTION_GENERATE:
					Apt::instance()->publish_post( $post_id, null, true, Settings::instance()->get( Options_Schema::GENERATION_METHODS ) );
					break;
				case self::BULK_ACTION_DELETE:
					delete_post_thumbnail( $post_id );
					delete_post_meta( $post_id, Apt::PLUGIN_SET_META_KEY );
					break;
				default:
					return $redirect_to;
			}
		}

		$redirect_to = add_query_arg(
			[
				'apt_bulk_action' => count( $post_ids ),
			],
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Admin notice after bulk action
	 */
	public function apt_bulk_action_admin_notice() {
		if ( ! isset( $_GET['apt_bulk_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['apt_bulk_action'] );

		if ( empty( $action ) ) {
			return;
		}

		$data = intval( $action );
		$msg  = __( 'Processed posts', 'auto-post-thumbnail' ) . ': ' . $data;
		echo '<div id="message" class="updated"><p>' . wp_kses_post( $msg ) . '</p></div>';
	}
}
