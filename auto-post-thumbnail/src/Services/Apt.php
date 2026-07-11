<?php
/**
 * APT service.
 *
 * @package AutoPostThumbnail\Services
 */

namespace AutoPostThumbnail\Services;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Logger;
use AutoPostThumbnail\Settings;
use AutoPostThumbnail\Models\Generate_Result;
use AutoPostThumbnail\Models\Image;
use AutoPostThumbnail\Models\Post_Images;
use AutoPostThumbnail\Services\Image_Generator;
use Exception;
use WP_Error;

/**
 * Class AutoPostThumbnails
 */
class Apt {
	const PLUGIN_SET_META_KEY       = 'apt_thumb';
	const PLUGIN_CREATED_ATTACHMENT = 'apt_created';

	/**
	 * Instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Sources.
	 *
	 * @var string[]
	 */
	public $sources;

	/**
	 * Allowed post types for generation.
	 *
	 * @var string[]
	 */
	public $allowed_generate_post_types;

	/**
	 * Get existing instance or create new one.
	 *
	 * @return Apt
	 */
	public static function instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new self();
		}

		return static::$instance;
	}

	/**
	 * Set sources context
	 *
	 * @param string $context Sources context.
	 *
	 * @return void
	 */
	public function set_sources_context( $context ) {
		$this->sources = apply_filters( 'wapt/sources', $this->sources, $context );
	}

	/**
	 * Process single post to generate the post thumbnail
	 *
	 * @param int    $post_id Post ID.
	 * @param string $method Generation method.
	 * @param bool   $forced Force generation.
	 *
	 * @return array
	 */
	public function process_post( $post_id, $method, $forced = false ) {
		Logger::instance()->info( "--Start processing post ID = {$post_id}" );
		$result = $this->publish_post( $post_id, null, true, $method, $forced );
		Logger::instance()->info( "--End processing post ID = {$post_id}" );
		$thumb_id = $result->thumbnail_id;

		if ( ! $thumb_id ) {
			$result->write_to_log();
		}
		return $result->get_data();
	}

	/**
	 * Set thumbnail for a post and track it as plugin-set.
	 *
	 * @param int $post_id Post ID.
	 * @param int $thumb_id Thumbnail ID.
	 *
	 * @return bool|int
	 */
	private function set_thumbnail( $post_id, $thumb_id ) {
		$result = update_post_meta( $post_id, '_thumbnail_id', $thumb_id );

		if ( $result ) {
			update_post_meta( $post_id, self::PLUGIN_SET_META_KEY, true );
		}

		return $result;
	}

	/**
	 * Replace thumbnail.
	 *
	 * @param int $post_id Post ID.
	 * @param int $thumbnail_id Thumbnail ID.
	 *
	 * @return string|WP_Error
	 */
	public function replace_thumbnail( $post_id, $thumbnail_id ) {
		$status = $this->set_thumbnail( $post_id, $thumbnail_id );

		if ( ! $status ) {
			return new WP_Error( __( 'Failed to replace thumbnail', 'auto-post-thumbnail' ) );
		}

		Logger::instance()->info( "Thumbnail replaced successfully for post ID = {$post_id}" );
		return get_the_post_thumbnail_url( $post_id );
	}

	/**
	 * Get thumbnail id for image
	 *
	 * @param array $image Image data.
	 *
	 * @return bool|int
	 */
	public function get_thumbnail_id( $image ) {
		/* @global wpdb $wpdb WordPress database object. */
		global $wpdb;
		$thumb_id = 0;

		/**
		 * If the image is from the WordPress own media gallery, then it appends the thumbnail id to a css class.
		 * Look for this id in the IMG tag.
		 */
		if ( isset( $image['tag'] ) && ! empty( $image['tag'] ) ) {
			preg_match( '/wp-image-([\d]*)/i', $image['tag'], $thumb_id );

			if ( $thumb_id ) {
				$thumb_id = $thumb_id[1];

				if ( ! get_post( (int) $thumb_id ) ) {
					$thumb_id = false;
				}
			}
		}

		if ( ! $thumb_id ) {
			// If thumb id is not found, try to look for the image in the database.
			if ( isset( $image['url'] ) && ! empty( $image['url'] ) ) {
				$image_url = $image['url'];
				// if the link is a thumbnail, then the regular expression will make the link to the original. removes the file name at the end -150x150
				$image_url = preg_replace( '/-[0-9]{1,}x[0-9]{1,}\./', ' . ', $image_url );
				$thumb_id  = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE ' % " . esc_sql( $image_url ) . " % '" );
			}
		}

		return is_numeric( $thumb_id ) ? $thumb_id : false;
	}


	/**
	 * Find image in post
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	private function find_image_in_post( $post_id ) {
		$images = new Post_Images( $post_id );

		if ( ! $images->is_images() ) {
			return 0;
		}

		foreach ( $images->get_images() as $image ) {
			$thumb_id = $this->get_thumbnail_id( $image );

			if ( $thumb_id ) {
				Logger::instance()->info( "An attachment ({$thumb_id}) was found in the text of the post." );
				$this->set_thumbnail( $post_id, $thumb_id );
				Logger::instance()->info( "Featured image ($thumb_id) is set for post ($post_id)" );
				return $thumb_id;
			}

			$thumb_id = $this->generate_post_thumb( $image['url'], $image['title'], $post_id );
			if ( $thumb_id ) {
				Logger::instance()->info( "An image was found in the text of the post and uploaded to medialibrary ({$thumb_id})." );
				$this->set_thumbnail( $post_id, $thumb_id );
				Logger::instance()->info( "Featured image ($thumb_id) is set for post ($post_id)" );
				return $thumb_id;
			}
		}

		return 0;
	}


	/**
	 * Function to save first image in post as post thumbnail.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an update.
	 * @param string   $method Generation method.
	 * @param bool     $forced Force generation.
	 *
	 * @return Generate_Result|null
	 * @throws Exception If generation fails.
	 */
	public function publish_post( $post_id, $post = null, $update = true, $method = null, $forced = false ) {
		global $wpdb;

		$autoimage  = $method ? $method : Settings::instance()->get( Options_Schema::GENERATION_METHODS );
		$generation = new Generate_Result( $post_id, $autoimage );

		if ( ! $post ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				Logger::instance()->warning( "The post was not found (post ID = {$post_id})" );

				return $generation->result( __( 'The post was not found', 'auto-post-thumbnail' ) );
			}
		}

		if ( 'auto-draft' === $post->post_status ) {
			return null;
		}

		if ( ! $update ) {
			return $generation->result();
		}

		if ( ! $forced ) {
			$_thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( ( $_thumbnail_id && $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE id = '" . esc_sql( $_thumbnail_id ) . "' AND post_type = 'attachment'" ) ) || get_post_meta( $post_id, 'skip_post_thumb', true ) ) {
				Logger::instance()->warning( "The post ({$post_id}) has already been assigned a featured image" );

				return $generation->result( __( 'The post has already been assigned a featured image', 'auto-post-thumbnail' ) );
			}
		}

		$thumb_id = 0;

		switch ( $autoimage ) {
			case 'find':
				$thumb_id = $this->find_image_in_post( $post_id );

				if ( $thumb_id ) {
					return $generation->result( '', $thumb_id );
				}

				return $generation->result( __( 'No images found', 'auto-post-thumbnail' ), $thumb_id );
			case 'both':
				$thumb_id = $this->find_image_in_post( $post_id );
				if ( $thumb_id ) {
					$this->set_thumbnail( $post_id, $thumb_id );
					return $generation->result( '', $thumb_id );
				}

				$thumb_id = $this->generate_and_attachment( $post_id );
				if ( $thumb_id ) {
					$this->set_thumbnail( $post_id, $thumb_id );
					return $generation->result( '', $thumb_id );
				}

				return $generation->result( __( 'No image found or generated', 'auto-post-thumbnail' ), $thumb_id );

			case 'generate':
				$thumb_id = $this->generate_and_attachment( $post_id );
				if ( $thumb_id ) {
					$this->set_thumbnail( $post_id, $thumb_id );
					return $generation->result( '', $thumb_id );
				}

				return $generation->result( __( 'No image generated', 'auto-post-thumbnail' ), $thumb_id );
			case 'use_default':
				$thumb_id = $this->find_image_in_post( $post_id );
				if ( $thumb_id ) {
					$this->set_thumbnail( $post_id, $thumb_id );
					return $generation->result( '', $thumb_id );
				}

				$thumb_id = $this->use_default( $post_id );

				if ( $thumb_id ) {
					$this->set_thumbnail( $post_id, $thumb_id );
					return $generation->result( '', $thumb_id );
				}

				return $generation->result( __( 'No image found or generated', 'auto-post-thumbnail' ), $thumb_id );
			default:
				return $generation->result( __( 'Invalid generation method', 'auto-post-thumbnail' ), $thumb_id );
		}
	}


	/**
	 * Fetch image from URL and generate required thumbnails.
	 *
	 * @param string $image Image URL.
	 * @param string $title Image title.
	 * @param int    $post_id Post ID.
	 *
	 * @return int|null
	 */
	private function generate_post_thumb( $image, $title, $post_id ) {
		// Get the URL now for further processing
		$image_url = $image;
		if ( wp_make_link_relative( $image_url ) === $image_url ) {
			$image_url = home_url( $image_url );
		}

		if ( ! $this->is_safe_remote_url( $image_url ) ) {
			Logger::instance()->debug( "Rejected unsafe image URL ({$image_url})." );

			return null;
		}

		$image_title = $title;

		// Get the file name — use slug + hash for video thumbnails (matching generated images),
		// fall back to extracting from URL.
		if ( ! empty( $image_title ) ) {
			$url_path = wp_parse_url( $image_url, PHP_URL_PATH );
			$ext      = $url_path ? pathinfo( $url_path, PATHINFO_EXTENSION ) : '';
			$ext      = $ext ? $ext : 'jpg';
			$slug     = sanitize_file_name( $image_title );
			$hash     = substr( md5( time() . $image_title ), 0, 5 );
			$filename = "{$slug}_{$post_id}_{$hash}.{$ext}";
		} else {
			$filename = substr( $image_url, ( strrpos( $image_url, '/' ) ) + 1 );
			// Exclude parameters after the filename.
			if ( strrpos( $filename, '?' ) ) {
				$filename = substr( $filename, 0, strrpos( $filename, '?' ) );
			}
		}

		$uploads = wp_upload_dir( current_time( 'mysql' ) );
		if ( false !== $uploads['error'] ) {
			return null;
		}

		// Generate unique file name
		$filename = wp_unique_filename( $uploads['path'], $filename );

		$new_file = $uploads['path'] . "/$filename";
		$ext      = pathinfo( $new_file, PATHINFO_EXTENSION );
		if ( empty( $ext ) ) {
			$ext       = 'jpg';
			$filename .= ".{$ext}";
			$new_file .= ".{$ext}";
		}

		$wp_filetype = wp_check_filetype( $filename );

		$allow_mime_types = [
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/bmp',
			'image/tiff',
			'image/webp',
			'image/avif',
		];

		if ( ( ! $wp_filetype['ext'] || ! in_array( $wp_filetype['type'], $allow_mime_types, true ) ) ) {
			Logger::instance()->debug( "File type ({$wp_filetype['type']}) is not allowed for upload." );

			return null;
		}

		// Move the file to the uploads dir.
		$file_data = $this->get_file_contents( $image_url );

		if ( ! $file_data ) {
			Logger::instance()->debug( "Failed to download the file from the link {$image_url}" );

			return null;
		}

		// @phpcs:disable
		file_put_contents( $new_file, $file_data );
		// @phpcs:enable

		$file_mime = mime_content_type( $new_file );

		if ( ! $file_mime || ! in_array( $file_mime, $allow_mime_types, true ) ) {
			Logger::instance()->debug( "Downloaded file from {$image_url} is not a valid image (detected MIME: {$file_mime})." );

			// @phpcs:disable
			@unlink( $new_file );
			// @phpcs:enable

			return null;
		}

		// Set correct file permissions
		$stat  = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;

		// @phpcs:disable
		@chmod( $new_file, $perms ); 
		// @phpcs:enable

		// Compute the URL
		$url = $uploads['url'] . "/$filename";

		// Construct the attachment array
		$attachment = [
			'post_mime_type' => $file_mime,
			'guid'           => $url,
			'post_parent'    => null,
			'post_title'     => $image_title,
			'post_content'   => '',
		];

		$thumb_id = wp_insert_attachment( $attachment, $new_file, $post_id );
		if ( ! is_wp_error( $thumb_id ) ) {
			require_once ABSPATH . '/wp-admin/includes/image.php';

			// Added fix by misthero as suggested
			wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
			update_attached_file( $thumb_id, $new_file );
			update_post_meta( $thumb_id, self::PLUGIN_CREATED_ATTACHMENT, true );

			return $thumb_id;
		} else {
			Logger::instance()->error( "Failed to add an attachment ({$new_file}) " . $thumb_id->get_error_message() );
		}

		return null;
	}

	/**
	 * Function to fetch the contents of a remote URL.
	 *
	 * @param string $url The URL to fetch.
	 *
	 * @return string|false
	 */
	private function get_file_contents( $url ) {
		if ( ! $this->is_safe_remote_url( $url ) ) {
			return false;
		}

		$response = wp_safe_remote_get(
			$url,
			[
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$contents = wp_remote_retrieve_body( $response );

		return $contents ? $contents : false;
	}

	/**
	 * Generate image with text.
	 *
	 * @param string $text Text to render.
	 * @param string $path_to_save Path to save the image.
	 * @param string $format Image format.
	 * @param int    $width Image width.
	 * @param int    $height Image height.
	 *
	 * @return Image
	 */
	public static function generate_image_with_text( $text, $path_to_save = '', $format = 'jpg', $width = 0, $height = 0 ) {
		$image_generator = new Image_Generator();
		return $image_generator->generate( $text, $path_to_save, $format, $width, $height );
	}

	/**
	 * Generate image with text.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int $thumb_id
	 */
	private function generate_and_attachment( $post_id ) {
		Logger::instance()->info( "Start generate attachment for post ID = {$post_id}" );

		$format = Settings::instance()->get( Options_Schema::IMAGE_FORMAT );
		switch ( $format ) {
			case 'png':
				$extension = 'png';
				$mime_type = 'image/png';
				break;
			case 'jpg':
			case 'jpeg':
			default:
				$extension = 'jpg';
				$mime_type = 'image/jpeg';
				break;
		}
		$post    = get_post( $post_id, 'OBJECT' );
		$uploads = wp_upload_dir( current_time( 'mysql' ) );
		$title   = apply_filters( 'wapt/generate/title', $post->post_title, $post_id );

		// Generate unique file name
		$slug = wp_unique_post_slug( sanitize_title( $title ), $post->ID, $post->post_status, $post->post_type, $post->post_parent );
		// unique hash based on timestamp + post_id
		$timestamp = time() . $post_id;
		$hash      = substr( md5( $timestamp ), 0, 5 );
		$filename  = wp_unique_filename( $uploads['path'], "{$slug}_{$post_id}_{$hash}.{$extension}" );
		$file_path = "{$uploads['path']}/{$filename}";

		Logger::instance()->info( "Generated file path = {$file_path}" );

		// Move the file to the uploads dir
		$image = apply_filters( 'wapt/generate/image', false, $title, $uploads['path'] . "/$filename", $extension );
		if ( ! $image ) {
			$image = apply_filters( 'wapt/generate/image', self::generate_image_with_text( $title, $uploads['path'] . "/$filename", $extension ), $title, $uploads['path'] . "/$filename", $extension );
		}

		$thumb_id = self::insert_attachment( $post, $file_path, $mime_type );

		if ( ! is_wp_error( $thumb_id ) ) {
			Logger::instance()->info( "Successful generate attachment ID = {$thumb_id}" );
			Logger::instance()->info( "End generate attachment for post ID = {$post_id}" );

			return $thumb_id;
		} else {
			Logger::instance()->error( 'Error generate attachment: ' . $thumb_id->get_error_message() );
		}

		return 0;
	}

	/**
	 * Use the default image for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	private function use_default( $post_id ) {
		Logger::instance()->info( "Start set default attachment for post ID = {$post_id}" );

		$thumb_id = Settings::instance()->get( Options_Schema::DEFAULT_BACKGROUND );

		if ( ! is_wp_error( $thumb_id ) ) {
			Logger::instance()->info( "Successful set default attachment ID = {$thumb_id}" );

			return $thumb_id;
		} else {
			Logger::instance()->error( 'Error set default attachment: ' . $thumb_id->get_error_message() );
		}

		return 0;
	}

	/**
	 * Insert WP attachment
	 *
	 * @param \WP_Post|int $post Post object or post ID.
	 * @param string       $file_path Path to file.
	 * @param string       $mime_type MIME type.
	 *
	 * @return int|WP_Error
	 */
	public static function insert_attachment( $post, $file_path, $mime_type = '' ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post, 'OBJECT' );
		}

		if ( ! $post ) {
			return new WP_Error( 'apt_attachment', 'Post not found (insert_attachment)' );
		}

		if ( empty( $mime_type ) ) {
			$mime_type = wp_get_image_mime( $file_path );
			if ( ! $mime_type ) {
				$mime_type = 'image/jpeg';
			}
		}

		$file_url = str_replace( wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $file_path );
		if ( file_exists( $file_path ) ) {
			$attachment = [
				'post_mime_type' => $mime_type,
				'guid'           => $file_url,
				'post_parent'    => $post->ID,
				'post_title'     => $post->post_title,
				'post_content'   => '',
			];

			$thumb_id = wp_insert_attachment( $attachment, $file_path, $post->ID );

			if ( ! is_wp_error( $thumb_id ) ) {
				require_once ABSPATH . '/wp-admin/includes/image.php';

				// Added fix by misthero as suggested
				wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $file_path ) );
				update_attached_file( $thumb_id, $file_path );
				update_post_meta( $thumb_id, self::PLUGIN_CREATED_ATTACHMENT, true );

				return $thumb_id;
			}
		}

		return new WP_Error( 'apt_attachment', 'File not exists (insert_attachment)' );
	}

	/**
	 * Check if the URL is safe to fetch.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	private function is_safe_remote_url( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return false;
		}

		$url = wp_http_validate_url( $url );
		if ( ! $url ) {
			return false;
		}

		return true;
	}
}
