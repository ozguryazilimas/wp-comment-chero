<?php
/**
 * Class YARPP_Shortcode
 * Adds the YARPP shortcode.
 *
 * @package        YARPP
 * @since          5.4.0
 */
class YARPP_Shortcode {
	public function register() {
		add_shortcode(
			'yarpp',
			array( $this, 'render' )
		);
	}

	/**
	 * @param array $atts see https://wordpress.org/plugins/yet-another-related-posts-plugin/#installation for acceptable arguments
	 *
	 * @return string
	 */
	public function render( $atts ) {
		/** @global $yarpp YARPP */
		global $yarpp;
		// don't use shortcode_atts() as it's DRYer to all the validation in YARPP::display_related()
		// but do use the same filter as shortcode_atts, with all the same parameters as before the backward-compatibility
		$atts = apply_filters(
			'shortcode_atts_yarpp',
			(array) $atts,
			$atts,
			array(
				'reference_id' => null,
				'template'     => null,
				'limit'        => null,
				'recent'       => null,
			),
			'yarpp'
		);
		$atts = array_map(function( $item ) {
			$trimmed_value = trim($item);
			// check for the strings "true" and "false" to mean boolean true and false
			if ( is_string($trimmed_value) ) {
				$lower_trimmed_value = strtolower($trimmed_value);
				if ( $lower_trimmed_value === 'true' ) {
					$trimmed_value = true;
				} elseif ( $lower_trimmed_value === 'false' ) {
					$trimmed_value = false;
				}
			}

			return $trimmed_value;
		},
			$atts
		);
		// Hardcoded the domain name because it should not be editable by the user.
		$atts['domain'] = 'shortcode';
		$post           = get_post( isset($atts['reference_id']) ? (int) $atts['reference_id'] : null );
		unset($atts['reference_id']);
		if ( $post instanceof WP_Post ) {
			return $yarpp->display_related(
				$post->ID,
				$atts,
				false
			);
		} else {
			return '<!-- YARPP shortcode called but no reference post found. It was probably called outside "the loop" or the reference_id provided was invalid.-->';
		}

	}
}
